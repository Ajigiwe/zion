#!/usr/bin/env bash
# ==============================================================================
# Zion Groups of Companies - Sync & Deploy Pipeline
#
#   bash sync_deploy.sh
#
# Workflow:
#   1. Preps the payment gateway for Paystack keys (validates/prompts/injects)
#   2. Pulls latest changes from Git (origin/main or DEPLOY_BRANCH)
#   3. Syncs code into live web root (preserves .env, logs, and user uploads)
#   4. Re-establishes runtime storage folders with secure permissions
#   5. Runs pre-flight diagnostics (tools/doctor.php)
#
# Overrides:
#   PAYSTACK_PUBLIC_KEY=pk_live_... PAYSTACK_SECRET_KEY=sk_live_... bash sync_deploy.sh
#   DEPLOY_BRANCH=main DEPLOY_TARGET=/var/www/html bash sync_deploy.sh
# ==============================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
BRANCH="${DEPLOY_BRANCH:-main}"
REPO="${DEPLOY_REPO:-https://github.com/Ajigiwe/zion.git}"
TARGET="${DEPLOY_TARGET:-}"

step() { printf '\n\033[1;36m==>\033[0m \033[1m%s\033[0m\n' "$*"; }
note() { printf '    \033[0;32m✓\033[0m %s\n' "$*"; }
warn() { printf '    \033[0;33m!\033[0m %s\n' "$*"; }
die()  { printf '\n\033[1;31m!! ERROR:\033[0m %s\n\n' "$*" >&2; exit 1; }

# Determine destination target path
if [ -z "$TARGET" ]; then
    case "$ROOT" in
        */repositories/*) TARGET="$HOME/public_html" ;;
        *)                TARGET="$ROOT" ;;
    esac
fi

# ------------------------------------------------------------------------------
# 1. PREP PAYMENT GATEWAY (PAYSTACK KEYS)
# ------------------------------------------------------------------------------
step "Preparing Payment Gateway (Paystack Keys)"

ENV_FILE="$TARGET/.env"
if [ ! -f "$ENV_FILE" ] && [ -f "$ROOT/.env" ]; then
    ENV_FILE="$ROOT/.env"
fi

get_env_val() {
    local key="$1"
    local file="${2:-$ENV_FILE}"
    if [ -f "$file" ]; then
        grep -E "^${key}=" "$file" 2>/dev/null | tail -n1 | cut -d= -f2- | tr -d '\r"' | tr -d "'"
    fi
}

set_env_val() {
    local key="$1"
    local val="$2"
    local file="$3"
    if [ ! -f "$file" ]; then
        if [ -f "$ROOT/.env.example" ]; then
            cp "$ROOT/.env.example" "$file"
        else
            touch "$file"
        fi
    fi

    if grep -qE "^${key}=" "$file" 2>/dev/null; then
        # Replace existing key safely
        local tmp
        tmp="$(mktemp)"
        awk -v k="$key" -v v="$val" 'BEGIN{FS=OFS="="} $1==k {$2=v; found=1} {print} END{if(!found) print k,v}' "$file" > "$tmp"
        mv "$tmp" "$file"
    else
        echo "${key}=${val}" >> "$file"
    fi
}

CURRENT_PUB="${PAYSTACK_PUBLIC_KEY:-$(get_env_val 'PAYSTACK_PUBLIC_KEY')}"
CURRENT_SEC="${PAYSTACK_SECRET_KEY:-$(get_env_val 'PAYSTACK_SECRET_KEY')}"

# If interactive shell and keys are unset, prompt the operator
if [ -t 0 ] && [ -z "$CURRENT_PUB" ] && [ -z "${SKIP_PAYSTACK_PROMPT:-}" ]; then
    printf '   Paystack Public Key (e.g. pk_live_... / pk_test_... [Enter to skip]): '
    read -r input_pub || input_pub=""
    if [ -n "$input_pub" ]; then
        CURRENT_PUB="$input_pub"
    fi
fi

if [ -t 0 ] && [ -z "$CURRENT_SEC" ] && [ -z "${SKIP_PAYSTACK_PROMPT:-}" ]; then
    printf '   Paystack Secret Key (e.g. sk_live_... / sk_test_... [Enter to skip]): '
    read -r input_sec || input_sec=""
    if [ -n "$input_sec" ]; then
        CURRENT_SEC="$input_sec"
    fi
fi

# Validate key format
if [ -n "$CURRENT_PUB" ] || [ -n "$CURRENT_SEC" ]; then
    if [[ "$CURRENT_PUB" =~ ^pk_live_ ]] && [[ "$CURRENT_SEC" =~ ^sk_live_ ]]; then
        note "Paystack Mode: LIVE (Production keys verified)"
    elif [[ "$CURRENT_PUB" =~ ^pk_test_ ]] && [[ "$CURRENT_SEC" =~ ^sk_test_ ]]; then
        note "Paystack Mode: TEST (Sandbox keys verified)"
    else
        warn "Paystack keys present but have mixed/custom prefix (Public: ${CURRENT_PUB:0:7}..., Secret: ${CURRENT_SEC:0:7}...)"
    fi
else
    warn "Paystack keys not set (checkout will operate in simulation/demo mode)"
fi

# ------------------------------------------------------------------------------
# 2. GIT FETCH & PULL LATEST CHANGES
# ------------------------------------------------------------------------------
step "Pulling latest changes from Git ($BRANCH)"
cd "$ROOT"
command -v git >/dev/null 2>&1 || die "git command not found. Ensure git is installed and in PATH."

if ! git fetch -q origin "$BRANCH" 2>/dev/null; then
    die "git fetch failed for origin/$BRANCH. Verify network connection and repo permissions."
fi

BEFORE="$(git rev-parse --short HEAD 2>/dev/null || echo '(initial)')"
if ! git checkout -q -B "$BRANCH" "origin/$BRANCH"; then
    die "git checkout was blocked by local uncommitted file changes in $ROOT."
fi
AFTER="$(git rev-parse --short HEAD)"
note "Git updated: $BEFORE -> $AFTER ($(git log -1 --pretty=%s))"

# ------------------------------------------------------------------------------
# 3. SYNC TO LIVE WEB ROOT
# ------------------------------------------------------------------------------
step "Deploying to Live Target ($TARGET)"
mkdir -p "$TARGET/storage/logs" "$TARGET/storage/uploads"

if [ "$TARGET" != "$ROOT" ]; then
    command -v rsync >/dev/null 2>&1 || die "rsync command is required to sync to $TARGET."
    rsync -a --delete \
        --include 'storage/uploads/.htaccess' \
        --exclude 'storage/uploads/*' \
        --exclude 'storage/logs/*' \
        --exclude '.git' \
        --exclude '.env' \
        ./ "$TARGET/"
    note "Files synced to live directory ($TARGET)"
else
    note "Checkout directory is already the web root ($TARGET)"
fi

# Ensure .env exists in target and inject verified Paystack keys
if [ ! -f "$TARGET/.env" ]; then
    cp "$ROOT/.env.example" "$TARGET/.env"
    chmod 600 "$TARGET/.env" 2>/dev/null || true
    note "Created .env from .env.example"
fi

if [ -n "$CURRENT_PUB" ]; then
    set_env_val 'PAYSTACK_PUBLIC_KEY' "$CURRENT_PUB" "$TARGET/.env"
fi
if [ -n "$CURRENT_SEC" ]; then
    set_env_val 'PAYSTACK_SECRET_KEY' "$CURRENT_SEC" "$TARGET/.env"
fi
chmod 600 "$TARGET/.env" 2>/dev/null || true
note ".env permissions secured (600)"

# ------------------------------------------------------------------------------
# 4. RUNTIME STORAGE & PERMISSIONS
# ------------------------------------------------------------------------------
step "Configuring Runtime Permissions"
mkdir -p "$TARGET/storage/logs" "$TARGET/storage/uploads"
chmod 755 "$TARGET/storage" "$TARGET/storage/logs" "$TARGET/storage/uploads" 2>/dev/null || true
note "Storage directories ready: storage/logs & storage/uploads"

# ------------------------------------------------------------------------------
# 5. PRE-FLIGHT SYSTEM & HEALTH CHECKS
# ------------------------------------------------------------------------------
step "Running Pre-Flight Health Checks (tools/doctor.php)"
if command -v php >/dev/null 2>&1; then
    if ! php "$TARGET/tools/doctor.php"; then
        die "Pre-flight checks reported failures. Correct the errors above and re-run."
    fi
else
    warn "PHP CLI not available in current shell. Verify in browser via /tools/doctor.php"
fi

# ------------------------------------------------------------------------------
# SUMMARY
# ------------------------------------------------------------------------------
step "Deployment Completed Successfully"
note "Live Target  : $TARGET"
note "Git Revision : $AFTER ($(git log -1 --pretty=%ad --date=short))"
note "Git Branch   : $BRANCH"
if [ -n "$CURRENT_PUB" ]; then
    note "Paystack Key : ${CURRENT_PUB:0:8}..."
else
    note "Paystack Key : (simulation mode)"
fi
note "Next Step    : Test the live storefront at your domain."
