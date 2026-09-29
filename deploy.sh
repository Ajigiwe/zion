#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Zion Groups - deploy script (cPanel Terminal / SSH).
#
#   bash deploy.sh      update the checkout, sync it into the web root,
#                       restore runtime folders, seed .env once, run doctor
#
# Two layouts are supported:
#   - checkout == web root (public_html): git update is the deploy
#   - cPanel Git Version Control default (~/repositories/NAME): the code is
#     copied into $HOME/public_html afterwards (rsync), .env/uploads kept
#
# First-time setup (repo not cloned yet) is in README.md.
#
# Overridables:   DEPLOY_REPO=... DEPLOY_BRANCH=... DEPLOY_TARGET=/path bash deploy.sh
# ---------------------------------------------------------------------------
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
BRANCH="${DEPLOY_BRANCH:-main}"
REPO="${DEPLOY_REPO:-https://github.com/Ajigiwe/zion.git}"

step() { printf '\n== %s\n' "$*"; }
note() { printf '   %s\n' "$*"; }
die()  { printf '\n!! %s\n' "$*" >&2; exit 1; }

cd "$ROOT"
command -v git >/dev/null 2>&1 || die "git not found - cPanel > Terminal should provide it ('which git')"

# ------------------------------------------------------------------ fetch ---
step "Fetching origin/$BRANCH ($ROOT)"
if ! git fetch -q origin "$BRANCH" 2>/dev/null; then
    die "fetch failed.
   remote : $(git remote get-url origin 2>/dev/null || echo "$REPO")
   - public repo and no network? retry.
   - private repo: run 'git pull' once interactively (stores credentials),
     or use a deploy key / access token."
fi

# ----------------------------------------------------------------- update ---
BEFORE="$(git rev-parse --short HEAD 2>/dev/null || echo '(new)')"
step "Updating to origin/$BRANCH"
if ! git checkout -q -B "$BRANCH" "origin/$BRANCH"; then
    die "checkout blocked by local files (cPanel placeholder like index.html?).
   Remove the file(s) git listed above, then re-run: bash deploy.sh"
fi
AFTER="$(git rev-parse --short HEAD)"
note "$BEFORE -> $AFTER  $(git log -1 --pretty=%s)"
note "files: $(git ls-files | wc -l | tr -d ' ') tracked"

# ----------------------------------------------------------------- target ---
TARGET="${DEPLOY_TARGET:-}"
if [ -z "$TARGET" ]; then
    case "$ROOT" in
        */repositories/*) TARGET="$HOME/public_html" ;;
        *)                TARGET="$ROOT" ;;
    esac
fi

if [ "$TARGET" != "$ROOT" ]; then
    step "Syncing $ROOT -> $TARGET"
    command -v rsync >/dev/null 2>&1 || die "rsync not found (needed to copy the checkout into the web root)"
    mkdir -p "$TARGET/storage/logs" "$TARGET/storage/uploads"
    # --delete removes stale files; .env, uploads and logs are excluded so
    # they survive every deploy. storage/uploads/.htaccess is force-included.
    rsync -a --delete \
        --include 'storage/uploads/.htaccess' \
        --exclude 'storage/uploads/*' \
        --exclude 'storage/logs/*' \
        --exclude '.git' \
        --exclude '.env' \
        ./ "$TARGET/"
    note "site files synced (checkout stays outside the web root)"
else
    TARGET="$ROOT"
    note "checkout is the web root - nothing to copy"
fi

# -------------------------------------------------------- runtime folders ---
step "Runtime folders"
mkdir -p "$TARGET/storage/logs" "$TARGET/storage/uploads"
chmod 755 "$TARGET/storage" "$TARGET/storage/logs" "$TARGET/storage/uploads" 2>/dev/null \
    || note "chmod not allowed here (suPHP usually does not need it)"

# ------------------------------------------------------------------- .env ---
if [ ! -f "$TARGET/.env" ]; then
    cp "$TARGET/.env.example" "$TARGET/.env"
    chmod 600 "$TARGET/.env" 2>/dev/null || true
    note "CREATED $TARGET/.env from .env.example - edit it now:"
    note "  DB_HOST/DB_NAME/DB_USER/DB_PASS, APP_URL, APP_ENV=production, APP_DEBUG=false"
else
    note "$TARGET/.env present (deploy never overwrites it)"
fi

# -------------------------------------------------------------- pre-flight ---
if command -v php >/dev/null 2>&1; then
    step "Pre-flight (tools/doctor.php)"
    if ! php "$TARGET/tools/doctor.php"; then
        die "pre-flight reported FAIL rows - fix them, then run: bash deploy.sh"
    fi
else
    step "Pre-flight skipped"
    note "php CLI missing - run the checks in the browser: /tools/doctor.php"
fi

# ---------------------------------------------------------------- summary ---
step "Deployed"
note "commit : $AFTER  ($(git log -1 --pretty=%ad --date=short))"
note "branch : $BRANCH"
note "target : $TARGET"
if [ -f "$TARGET/.env" ]; then
    envv="$(grep -E '^APP_ENV='   "$TARGET/.env" | tail -n1 | cut -d= -f2- | tr -d '\r')"
    dbg="$(grep -E '^APP_DEBUG='  "$TARGET/.env" | tail -n1 | cut -d= -f2- | tr -d '\r')"
    note "env    : APP_ENV=${envv:-<unset>}  APP_DEBUG=${dbg:-<unset>}"
    if [ "${envv:-development}" != "production" ] || [ "${dbg:-true}" != "false" ]; then
        note "reminder: set APP_ENV=production and APP_DEBUG=false for launch"
    fi
fi
note "re-run any time with: bash deploy.sh"
