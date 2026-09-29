<#
.SYNOPSIS
  Zion Groups - remote viewing tunnel (ngrok).

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File tools\tunnel.ps1 up     # start server + tunnel, print URL
  powershell -ExecutionPolicy Bypass -File tools\tunnel.ps1 url    # print the current URL
  powershell -ExecutionPolicy Bypass -File tools\tunnel.ps1 watch  # background watchdog: auto-restarts a dead tunnel
  powershell -ExecutionPolicy Bypass -File tools\tunnel.ps1 down   # stop watchdog + tunnel (server keeps running)

The current URL is always mirrored to storage\logs\tunnel.url.
ngrok's authtoken lives in %LOCALAPPDATA%\ngrok\ngrok.yml (never in the repo).
#>
param([ValidateSet('up', 'down', 'url', 'watch')][string]$Command = 'up')

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$tmp  = Join-Path $env:TEMP 'opencode'
$ngrok = Join-Path $tmp 'ngrok.exe'
$ngrokLog = Join-Path $tmp 'ngrok.log'
$ngrokErr = Join-Path $tmp 'ngrok.err.log'
$stateFile  = Join-Path $root 'storage\logs\tunnel.state'
$urlFile    = Join-Path $root 'storage\logs\tunnel.url'
$watchPid   = Join-Path $root 'storage\logs\tunnel-watch.pid'
$watchLog   = Join-Path $root 'storage\logs\tunnel-watch.log'
$PHP_PORT = 8080

function Stop-Watch {
    if (Test-Path $watchPid) {
        try {
            $wp = [int](Get-Content $watchPid -Raw)
            if (Get-Process -Id $wp -ErrorAction SilentlyContinue) { Stop-Process -Id $wp -Force; "stopped watchdog (PID $wp)" }
        } catch { }
        Remove-Item $watchPid -Force -ErrorAction SilentlyContinue
    }
}

function Stop-Tunnel {
    if (Test-Path $stateFile) {
        try {
            $st = Get-Content $stateFile -Raw | ConvertFrom-Json
            if ($st.pid -and (Get-Process -Id $st.pid -ErrorAction SilentlyContinue)) {
                Stop-Process -Id $st.pid -Force; "stopped tunnel process (PID $($st.pid))"
            }
        } catch { }
        Remove-Item $stateFile -Force -ErrorAction SilentlyContinue
    }
    Get-Process ngrok, cloudflared -ErrorAction SilentlyContinue | ForEach-Object {
        try { Stop-Process -Id $_.Id -Force -ErrorAction Stop; "stopped $($_.ProcessName) (PID $($_.Id))" } catch { }
    }
}

function Read-Log([string]$f) {
    if (-not (Test-Path $f)) { return '' }
    try {
        $fs = [System.IO.File]::Open($f, 'Open', 'Read', 'ReadWrite')
        $sr = New-Object System.IO.StreamReader($fs)
        $t = $sr.ReadToEnd()
        $sr.Close()
        return $t
    } catch { return '' }
}

function Get-NgrokUrl {
    try {
        $t = Invoke-RestMethod -Uri 'http://127.0.0.1:4040/api/tunnels' -TimeoutSec 3
        $https = @($t.tunnels | Where-Object { $_.public_url -like 'https://*' } | Select-Object -First 1)
        if ($https.Count) { return $https[0].public_url }
    } catch { }
    return $null
}

function Test-TunnelUrl([string]$u) {
    try {
        $r = Invoke-WebRequest -Uri "$u/" -UseBasicParsing -TimeoutSec 15 -Headers @{ 'ngrok-skip-browser-warning' = '1' }
        return ($r.StatusCode -eq 200)
    } catch { return $false }
}

if ($Command -eq 'down') { Stop-Watch; Stop-Tunnel; Remove-Item $urlFile -Force -ErrorAction SilentlyContinue; exit 0 }

if ($Command -eq 'url') {
    if (Test-Path $urlFile) { (Get-Content $urlFile -Raw).Trim(); exit 0 }
    Write-Error 'tunnel is not running - run: tunnel.ps1 up'; exit 1
}

# --------------------------------------------------------- 'watch' (loop) --
if ($Command -eq 'watch') {
    if (Test-Path $watchPid) {
        try {
            $wp = [int](Get-Content $watchPid -Raw)
            if (Get-Process -Id $wp -ErrorAction SilentlyContinue) { "watchdog already running (PID $wp)"; exit 0 }
        } catch { }
    }
    Set-Content -Path $watchPid -Value $PID
    $note = { param($m) "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')  $m" | Add-Content -Path $watchLog }
    & $note "watchdog started (PID $PID)"
    $fail = 0
    $restartAt = Get-Date
    while ($true) {
        $u = $null; $alive = $false
        if (Test-Path $urlFile) { $u = (Get-Content $urlFile -Raw).Trim() }
        if (Test-Path $stateFile) {
            try { $st = Get-Content $stateFile -Raw | ConvertFrom-Json; $alive = $null -ne (Get-Process -Id $st.pid -ErrorAction SilentlyContinue) } catch { }
        }
        $ok = ($u -and $alive -and (Test-TunnelUrl $u))
        if ($ok) { $fail = 0 }
        elseif ((Get-Date) - $restartAt -lt [TimeSpan]::FromSeconds(150)) {
            & $note "probe failed during startup grace (url=$u alive=$alive)"
        }
        else {
            $fail++
            & $note "probe failed ($fail/3) url=$u alive=$alive"
            if ($fail -ge 3) {
                & $note 'restarting tunnel...'
                try { $out = (& $PSCommandPath up 2>&1 | Out-String) } catch { $out = $_.Exception.Message }
                & $note (($out -split "`n" | Where-Object { $_ -match 'TUNNEL URL|STATUS|error' } | ForEach-Object { $_.Trim() }) -join ' | ')
                $fail = 0
                $restartAt = Get-Date
            }
        }
        Start-Sleep -Seconds 40
    }
}

# ------------------------------------------------------------------- 'up' --
if (-not (Test-Path $ngrok)) {
    New-Item -ItemType Directory -Force -Path $tmp | Out-Null
    "downloading ngrok..."
    $zip = Join-Path $tmp 'ngrok.zip'
    Invoke-WebRequest -Uri 'https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-windows-amd64.zip' -OutFile $zip -UseBasicParsing
    Expand-Archive -Path $zip -DestinationPath $tmp -Force
    Remove-Item $zip -Force
}

$listening = Get-NetTCPConnection -LocalPort $PHP_PORT -State Listen -ErrorAction SilentlyContinue
if (-not $listening) {
    "starting dev server on 127.0.0.1:$PHP_PORT..."
    Start-Process -FilePath 'php' -ArgumentList @('-S', "127.0.0.1:$PHP_PORT", '-t', $root) -WorkingDirectory $root -WindowStyle Hidden
    for ($i = 0; $i -lt 30; $i++) {
        Start-Sleep -Milliseconds 400
        if (Get-NetTCPConnection -LocalPort $PHP_PORT -State Listen -ErrorAction SilentlyContinue) { break }
    }
    if (-not (Get-NetTCPConnection -LocalPort $PHP_PORT -State Listen -ErrorAction SilentlyContinue)) {
        Write-Error "php -S did not start on port $PHP_PORT"; exit 1
    }
} else {
    "dev server already listening on 127.0.0.1:$PHP_PORT"
}

Stop-Tunnel
Remove-Item $ngrokLog, $ngrokErr -Force -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Force -Path (Split-Path $stateFile) | Out-Null

"starting ngrok tunnel..."
$p = Start-Process -FilePath $ngrok -ArgumentList @('http', "$PHP_PORT", '--log', 'stdout', '--log-format', 'logfmt') `
        -RedirectStandardOutput $ngrokLog -RedirectStandardError $ngrokErr -WindowStyle Hidden -PassThru

$url = $null
for ($i = 0; $i -lt 60 -and -not $url; $i++) {
    Start-Sleep -Milliseconds 700
    if ($p.HasExited) { break }
    $url = Get-NgrokUrl
    if ($i % 5 -eq 4) { Write-Host -NoNewline '.' }
}
Write-Host ''

if (-not $url) {
    $diag = (Read-Log $ngrokLog) + (Read-Log $ngrokErr)
    Write-Error ("ngrok did not report a URL. Log tail: " + (($diag -split "`n" | Select-Object -Last 6) -join ' | '))
    exit 1
}

@{ pid = $p.Id; url = $url; started = (Get-Date).ToString('o') } | ConvertTo-Json | Set-Content -Path $stateFile -Encoding UTF8
Set-Content -Path $urlFile -Value $url -Encoding UTF8

$ok = if (Test-TunnelUrl $url) { 'storefront OK' } else { 'URL minted (probe pending - browsers must click through the ngrok notice once)' }

""
"  TUNNEL URL : $url"
"  STATUS     : $ok"
"  admin      : $url/admin/login.php   (admin@ziongroups.com.gh / Admin123!)"
"  watch log   : storage\logs\tunnel-watch.log"
"  stop with   : powershell -ExecutionPolicy Bypass -File tools\tunnel.ps1 down"
""
