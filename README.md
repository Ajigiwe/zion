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

## Deploying to cPanel (shared hosting)

1. **Get the code up** - either cPanel > *Git Version Control* (clone this repo)
   or upload a zip via *File Manager* and extract into `public_html` (or a
   subfolder - the app derives its base path automatically).
2. **Create the database** - *MySQL Databases*: create DB + user, add the user
   to the DB, note the (often prefixed) names.
3. **Configure** - copy `.env.example` to `.env` and set:
   - `APP_ENV=production`, `APP_DEBUG=false`
   - `APP_URL=https://yourdomain` (no trailing slash)
   - `DB_HOST=localhost` plus the `DB_NAME` / `DB_USER` / `DB_PASS` from step 2
   - Paystack keys and SMTP (`MAIL_MAILER=smtp`) when you are ready to go live
4. **Import the data** - phpMyAdmin > Import: `schema.sql`, then `seed.sql`.
5. **Permissions** - `chmod 755 storage storage/logs storage/uploads`
   (use 775 if the check below still fails).
6. **Pre-flight check** - in cPanel *Terminal*: `php tools/doctor.php`
   (or open `/tools/doctor.php` in the browser, then **delete that file**).
   Every row must say PASS; exit code must be 0.
7. **HTTPS** - once the certificate works, uncomment the HTTPS block at the
   bottom of `.htaccess`.
8. **Backups** - schedule cPanel backups or a nightly `mysqldump` cron job.

## Checks and tests

```powershell
php tools/doctor.php                          # server pre-flight (exit 0 = ready)
node --check assets\*.js                      # JS syntax after asset edits
```

Regression sweep and CDP test suites live outside the repo (dev machine only).

## Live preview through a tunnel

`tools/tunnel.ps1` runs the app behind an ngrok HTTPS URL
(`up` / `down` / `url` / `watch`); the current URL is echoed in
`storage/logs/tunnel.url`.

## Repository notes

- `storage/logs/*` and `storage/uploads/*` are gitignored; the folders ship
  with `.gitkeep` so they exist after a fresh clone.
- `.htaccess` denies `.env`, logs, `*.pid`, SQL dumps and `tools/` (the single
  exception is `tools/doctor.php`); only `storage/uploads` is publicly served,
  and script extensions are blocked there by `storage/uploads/.htaccess`.
- `.env` is never committed - keep secrets there, not in the repo.
