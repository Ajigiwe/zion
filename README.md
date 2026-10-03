# zion

**Zion Groups of Companies** - e-commerce storefront (catalogue, cart, wishlist,
checkout, auth) plus a full admin panel. PHP 8.2 + MySQL/MariaDB, no framework,
no build step: Tailwind via CDN, plain SQL through PDO.

## Requirements

- PHP **8.2+** with `pdo_mysql`, `mbstring`, `fileinfo`, `session`, `filter`
- MySQL 8 / MariaDB 10.4+
- Apache with `mod_rewrite` (`.htaccess` is provided; `mod_headers` recommended)
- Write access to `storage/logs` and `storage/uploads`
- Optional: `.user.ini` raises upload limits on CGI/FPM hosts

## Local quick start

```powershell
copy .env.example .env
# create a database, then import schema.sql and seed.sql (phpMyAdmin or mysql.exe)
php -S 127.0.0.1:8080
```

Open http://127.0.0.1:8080

| Role     | Email                                | Password     |
| -------- | ------------------------------------ | ------------ |
| Admin    | admin@ziongroups.com.gh              | `Admin123!`  |
| Customer | kwame.mensah@ziongroups.com.gh        | `Customer123!` |

## First-time server setup (cPanel shared hosting)

The git checkout lives **in the web root** (`public_html`): the app's root is
the public root, and `.htaccess` keeps `.git`, `.env`, logs and `tools/` off the
web (verified: all 403).

**1. Get the code** - pick one:

- cPanel > *Git Version Control* > New Repository: URL
  `https://github.com/Ajigiwe/zion.git`, checkout path `public_html`, click
  *Update* - or Terminal, if `public_html` is empty:

  ```bash
  cd ~ && git clone https://github.com/Ajigiwe/zion.git public_html
  ```

- Non-empty `public_html` (cPanel placeholder files in the way):

  ```bash
  cd ~/public_html
  rm -f index.html                      # cPanel placeholder, if present
  git init
  git remote add origin https://github.com/Ajigiwe/zion.git
  git fetch origin main
  git reset --hard origin/main
  ```

- cPanel's **default** layout (checkout in `~/repositories/NAME` instead of
  the web root) works too: `.cpanel.yml` copies the code into `public_html`
  when you click **Deploy HEAD Commit**, and `deploy.sh` detects that layout
  automatically.

**2. Configure once:**

```bash
cd ~/public_html
# cPanel > MySQL Databases: create DB + user, add user to DB
cp .env.example .env        # then edit: DB_*, APP_URL=https://yourdomain,
                            # APP_ENV=production, APP_DEBUG=false
```

3. Import `schema.sql`, then `seed.sql`, via phpMyAdmin into that database.
4. `bash deploy.sh` - pulls, recreates `storage/*`, seeds `.env` only if
   missing, runs the pre-flight check. Fix any FAIL rows it prints and re-run.
5. After the certificate works: uncomment the HTTPS block at the bottom of
   `.htaccess`.
6. Schedule cPanel backups (or a nightly `mysqldump` cron job).

## Deploying changes

```bash
# on your machine
git add -A && git commit -m "..." && git push

# on the server (cPanel Terminal or SSH)
cd ~/public_html && bash sync_deploy.sh
```

`sync_deploy.sh`:

- **Preps Payment Gateway**: validates and prompts/injects `PAYSTACK_PUBLIC_KEY` & `PAYSTACK_SECRET_KEY` into `.env` before live activation (supports `pk_live_...` / `sk_live_...` or test keys).
- `git fetch` + fast-forward to `origin/main` (prints `old -> new` commit),
- syncs code cleanly into the live web root (`rsync`),
- re-creates `storage/logs` + `storage/uploads` with 755 permissions,
- creates/maintains `.env` with 600 permissions,
- runs `php tools/doctor.php` pre-flight diagnostics (verifying DB, tables, permissions, and Paystack status).

You can also pass Paystack keys directly in command line:
```bash
PAYSTACK_PUBLIC_KEY=pk_live_xxx PAYSTACK_SECRET_KEY=sk_live_xxx bash sync_deploy.sh
```

**Rules of the road**

- Never edit files on the server - change them here and push, or the next
  deploy discards the edit (the server is not an editor).
- Private repo? Run `git pull` once interactively so credentials get stored,
  or add a deploy key; `deploy.sh` prints a hint if the fetch fails.
- Deploy another branch with `DEPLOY_BRANCH=staging bash deploy.sh`.

### No shell? cPanel Git Version Control (no SSH needed)

If the host has shell access disabled, the same loop runs inside cPanel:

1. Push from your machine.
2. cPanel -> *Git Version Control* -> your repository -> **Update from Remote**
   (pulls the new commit into `~/repositories/NAME` - code only, not live yet).
3. Click **Deploy HEAD Commit** - runs the `.cpanel.yml` tasks: copy the
   checkout into `public_html` with `tar` (never touching `.env`, uploads or
   logs), then `php tools/doctor.php`. The deployment turns red if any check
   fails.
4. Or verify in the browser: `/tools/doctor.php`.

`deploy.sh` and the Deploy button do the same job; use whichever the host
allows. The Deploy button only needs `tar` (present on every host);
`deploy.sh` needs `rsync`, which Git Bash and most shells provide.

## Checks and tests

```powershell
php tools/doctor.php                          # pre-flight (exit 0 = ready)
node --check assets\*.js                      # JS syntax after asset edits
```

The same check runs in the browser at `/tools/doctor.php` (read-only, prints no
secrets; hide it by removing the `tools/doctor.php` exception in `.htaccess`).

Regression sweep and CDP test suites live outside the repo (dev machine only).

## Live preview through a tunnel

`tools/tunnel.ps1` runs the app behind an ngrok HTTPS URL
(`up` / `down` / `url` / `watch`); the current URL is echoed in
`storage/logs/tunnel.url`.

## Repository notes

- `storage/logs/*` and `storage/uploads/*` are gitignored; the folders ship
  with `.gitkeep` so they exist after a fresh clone.
- `.htaccess` denies every dot-path (`.git`, `.env`, `.user.ini`, editor dirs)
  except `.well-known`, denies `*.log`/`*.pid`, SQL dumps, and all of `tools/`
  except `tools/doctor.php`; only `storage/uploads` is publicly served, with
  script extensions blocked there by `storage/uploads/.htaccess`.
- `.env` is never committed - keep secrets there, not in the repo.
