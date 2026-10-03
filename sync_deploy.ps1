# ==============================================================================
# Zion Groups of Companies - PowerShell Sync & Deploy Pipeline (Windows / Local)
#
#   .\sync_deploy.ps1
#
# Overrides:
#   $env:PAYSTACK_PUBLIC_KEY="pk_live_..."
#   $env:PAYSTACK_SECRET_KEY="sk_live_..."
#   .\sync_deploy.ps1
# ==============================================================================
[CmdletBinding()]
param(
    [string]$Branch = "main",
    [string]$Target = "",
    [switch]$SkipPaystackPrompt
)

$ErrorActionPreference = "Stop"
$Root = $PSScriptRoot

function Step-Msg($msg) { Write-Host "`n==> $msg" -ForegroundColor Cyan }
function Note-Msg($msg) { Write-Host "    [OK] $msg" -ForegroundColor Green }
function Warn-Msg($msg) { Write-Host "    [!]  $msg" -ForegroundColor Yellow }

if ([string]::IsNullOrWhiteSpace($Target)) {
    $Target = $Root
}

# ------------------------------------------------------------------------------
# 1. PREP PAYMENT GATEWAY (PAYSTACK KEYS)
# ------------------------------------------------------------------------------
Step-Msg "Preparing Payment Gateway (Paystack Keys)"

$envFile = Join-Path $Target ".env"
if (-not (Test-Path $envFile) -and (Test-Path (Join-Path $Root ".env"))) {
    $envFile = Join-Path $Root ".env"
}

function Get-EnvValue($key, $path) {
    if (Test-Path $path) {
        $line = Get-Content $path | Where-Object { $_ -match "^$key=" } | Select-Object -Last 1
        if ($line) {
            return ($line -replace "^$key=", "").Trim('"', "'", " ")
        }
    }
    return ""
}

function Set-EnvValue($key, $val, $path) {
    if (-not (Test-Path $path)) {
        $example = Join-Path $Root ".env.example"
        if (Test-Path $example) {
            Copy-Item $example $path
        } else {
            New-Item -ItemType File -Path $path -Force | Out-Null
        }
    }

    $lines = Get-Content $path
    $found = $false
    $newLines = @()
    foreach ($l in $lines) {
        if ($l -match "^$key=") {
            $newLines += "$key=$val"
            $found = $true
        } else {
            $newLines += $l
        }
    }
    if (-not $found) {
        $newLines += "$key=$val"
    }
    Set-Content -Path $path -Value $newLines
}

$currentPub = if ($env:PAYSTACK_PUBLIC_KEY) { $env:PAYSTACK_PUBLIC_KEY } else { Get-EnvValue "PAYSTACK_PUBLIC_KEY" $envFile }
$currentSec = if ($env:PAYSTACK_SECRET_KEY) { $env:PAYSTACK_SECRET_KEY } else { Get-EnvValue "PAYSTACK_SECRET_KEY" $envFile }

if ([string]::IsNullOrWhiteSpace($currentPub) -and -not $SkipPaystackPrompt) {
    $inPub = Read-Host "   Paystack Public Key (e.g. pk_live_... / pk_test_... [Enter to skip])"
    if (-not [string]::IsNullOrWhiteSpace($inPub)) {
        $currentPub = $inPub.Trim()
    }
}

if ([string]::IsNullOrWhiteSpace($currentSec) -and -not $SkipPaystackPrompt) {
    $inSec = Read-Host "   Paystack Secret Key (e.g. sk_live_... / sk_test_... [Enter to skip])"
    if (-not [string]::IsNullOrWhiteSpace($inSec)) {
        $currentSec = $inSec.Trim()
    }
}

if (-not [string]::IsNullOrWhiteSpace($currentPub) -or -not [string]::IsNullOrWhiteSpace($currentSec)) {
    if ($currentPub -match "^pk_live_" -and $currentSec -match "^sk_live_") {
        Note-Msg "Paystack Mode: LIVE (Production keys verified)"
    } elseif ($currentPub -match "^pk_test_" -and $currentSec -match "^sk_test_") {
        Note-Msg "Paystack Mode: TEST (Sandbox keys verified)"
    } else {
        Warn-Msg "Paystack keys present with custom prefix (Public: $($currentPub.Substring(0,[Math]::Min(7,$currentPub.Length)))...)"
    }
} else {
    Warn-Msg "Paystack keys not set (checkout will operate in simulation/demo mode)"
}

# ------------------------------------------------------------------------------
# 2. GIT FETCH & PULL LATEST CHANGES
# ------------------------------------------------------------------------------
Step-Msg "Pulling latest changes from Git ($Branch)"
Set-Location $Root

git fetch origin $Branch
$before = (git rev-parse --short HEAD)
git checkout -B $Branch "origin/$Branch"
$after = (git rev-parse --short HEAD)
Note-Msg "Git updated: $before -> $after"

# ------------------------------------------------------------------------------
# 3. SYNC TO LIVE TARGET
# ------------------------------------------------------------------------------
Step-Msg "Deploying to Target ($Target)"
New-Item -ItemType Directory -Path (Join-Path $Target "storage/logs") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $Target "storage/uploads") -Force | Out-Null

if ($Target -ne $Root) {
    robocopy $Root $Target /E /XD .git storage\logs storage\uploads /XF .env
    Note-Msg "Files synced to live directory ($Target)"
} else {
    Note-Msg "Checkout directory is already target ($Target)"
}

$destEnv = Join-Path $Target ".env"
if (-not (Test-Path $destEnv)) {
    Copy-Item (Join-Path $Root ".env.example") $destEnv
    Note-Msg "Created .env from .env.example"
}

if (-not [string]::IsNullOrWhiteSpace($currentPub)) {
    Set-EnvValue "PAYSTACK_PUBLIC_KEY" $currentPub $destEnv
}
if (-not [string]::IsNullOrWhiteSpace($currentSec)) {
    Set-EnvValue "PAYSTACK_SECRET_KEY" $currentSec $destEnv
}

# ------------------------------------------------------------------------------
# 4. PRE-FLIGHT HEALTH CHECKS
# ------------------------------------------------------------------------------
Step-Msg "Running Pre-Flight Health Checks (tools/doctor.php)"
$doctorPath = Join-Path $Target "tools/doctor.php"
if (Get-Command php -ErrorAction SilentlyContinue) {
    php $doctorPath
} else {
    Warn-Msg "PHP CLI not available in PATH. Test in browser via /tools/doctor.php"
}

Step-Msg "Deployment Completed Successfully"
