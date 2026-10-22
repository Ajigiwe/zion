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

This repository is **public**, so nothing secret may ever be written into it -
credentials, keys or `.env` values are readable by anyone and stay in git
history forever. The seed's accounts are therefore created with random
passwords that exist nowhere; choose your own straight after importing:

```bash
php tools/set_admin_password.php admin@ziongroups.com.gh
php tools/set_admin_password.php kwame.mensah@ziongroups.com.gh
```

| Role     | Email                                |
| -------- | ------------------------------------ |
| Admin    | admin@ziongroups.com.gh              |
| Customer | kwame.mensah@ziongroups.com.gh       |

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
5. HTTPS: the redirect at the bottom of `.htaccess` is **enabled** (verified
   2026-10-21 — trusted certificate, apex and www both serving the app). Only
   comment it out again if the certificate lapses, and re-verify before ever
   turning it back on:
   ```bash
   curl -sI https://yourdomain.com | head -3   # expect HTTP/1.1 200
   curl -sI http://yourdomain.com  | head -3   # expect 301 + Location: https
   ```
   `localhost` / `127.0.0.1` are exempt, so local Apache still works over HTTP.
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

### Browser deploys (optional)

`sync_deploy.php` (and its alias `deploy.php`) can also run from the browser -
the page it prints lets you pull, see the commit diff and set Paystack keys.
Because that endpoint executes `git` and rewrites `.env`, it requires a secret
from `.env`:

```bash
php -r "echo bin2hex(random_bytes(32));"   # generate one
# then add to .env:   DEPLOY_TOKEN=<that value>
```

Open `/sync_deploy.php?token=<DEPLOY_TOKEN>`; the form carries the token from
there on. With no `DEPLOY_TOKEN` set the endpoint answers **403** and CLI
deploys are the only way in - which is the right default for a shop. A browser
can only ever deploy the canonical repository, never an arbitrary one.

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

## Telegram intake (product batches)

Clients send a spreadsheet and product photos to a Telegram bot; the bot stages
them and the admin panel matches the photos onto the sheet rows before
anything goes live.

1. Create a bot with `@BotFather` and copy its token.
2. Add to `.env` (server and local):

       TG_BOT_TOKEN=<token from BotFather>
       TG_WEBHOOK_SECRET=<php -r "echo bin2hex(random_bytes(16));">
       TG_ALLOWED_CHAT_IDS=<your numeric chat id, ask @userinfobot>

3. Register the webhook: `php tools/tg_set_webhook.php` (uses `APP_URL`).
   Check delivery/errors later with `php tools/tg_webhook_info.php`.
4. The client chats with the bot: sends the `.csv` or `.xlsx` sheet, then the
   photos - ideally **one album per product, captioned with the product name**
   (the caption drives matching; a SKU in the caption matches even better).
   `/status` shows what is staged, `/clear` throws the batch away.

   When captions are impractical (forwarding a WhatsApp batch from a phone,
   where every share is one product at a time), use the phone flow: save the
   whole batch to the phone gallery in one go, then send it from Telegram as
   a **single captionless album**. A captionless album is split back into
   individual photos and matched against the sheet rows **in send order**, so
   the sheet row order should follow the photo order. The admin review screen
   shows every thumbnail, so a misalignment is visible before commit. An album
   *with* a caption keeps meaning one product with a gallery.
5. Admin - **Telegram Intake**: review the match (by SKU, caption or send
   order; leftovers attach by hand), then *Send to Waiting Room* and commit as
   usual in **Bulk Products**. The bot never publishes anything by itself -
   every commit stays behind the admin login and CSRF.

Local development needs no Telegram: `php tools/tg_simulate_update.php
sheet path/batch.csv` and `... photo path/img.jpg --caption "Aura Bodysuit"`
feed the same dispatch the webhook uses, with files from disk.

The tables `intake_batches` / `intake_items` are created lazily on first use
(and ship in `schema.sql` for fresh installs). Photos are validated exactly
like `admin/upload.php` and land in `storage/uploads/`.

## Checks and tests

```powershell
php tools/doctor.php                          # pre-flight (exit 0 = ready)
node --check assets\*.js                      # JS syntax after asset edits
```

The same check runs in the browser at `/tools/doctor.php` (read-only, prints no
secrets; hide it by removing the `tools/doctor.php` exception in `.htaccess`).

### Smoke suites (security + stock)

```powershell
php tools/smoke.php        # 45 command-level checks
php tools/smoke_http.php   # 44 end-to-end checks over HTTP
```

Both provision a throwaway `<DB_NAME>_smoke` from `schema.sql` + `seed.sql` and
**drop it again when they finish** (`--keep` retains it for inspection), so the
application's own database is never touched. `smoke_http.php` additionally
starts and stops its own pair of PHP servers - 127.0.0.1:8098 with
`APP_ENV=development` (the simulated Paystack flow is allowed) and
127.0.0.1:8097 with `APP_ENV=production` (the same flow must be refused while
cash on delivery still works) - so there is nothing to run by hand and nothing
left listening afterwards. The account they sign in as is given a random
password for the duration of the run, because the seed no longer ships one.

They cover sign-in throttling, `FOR UPDATE` stock reservation and rollback, cart
clamping, the abandoned-order sweeper, Paystack amount/verification guards,
production's demo-order refusal, order-confirmation IDOR, checkout CSRF,
oversell refusal and the Telegram intake pipeline (staging, caption/SKU/order
matching, the xlsx reader, discard, webhook secret/whitelist gating). Exit code
0 means all 89 passed. Run them one at a time - they share the database name -
and note they are CLI-only.

Other regression/CDP suites live outside the repo (dev machine only).

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
