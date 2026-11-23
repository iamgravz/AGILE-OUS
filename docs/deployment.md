# Going live

The steps to put AGILE on a server for real use, in order. Each step says how to check it worked. The security reasons behind them are in [security-review.md](security-review.md#production-checklist).

## 1. Server

- PHP 8.2+, Composer 2 and MySQL 8 or MariaDB 10.6+, with the extensions in the [README](../README.md#requirements).
- Apache with `mod_rewrite`, plus `mod_deflate` and `mod_headers` for compression and caching (`public/.htaccess` uses them when they are on).
- HTTPS. The session cookie is marked Secure when `APP_URL` starts with `https://`.

## 2. Files

Copy the project, then run `composer install --no-dev --optimize-autoloader`. Do not copy:

- `.env` from a laptop (make a new one, step 3)
- `storage/uploads`, `storage/credentials`, `storage/mail`, `storage/backups`, `storage/logs` from a laptop: they hold demo or test files. Keep the empty folders.
- `tests/`, `phpunit.xml` and `.phpunit.result.cache` are not needed on the server.

Point the web server's document root at `public/` only (see the virtual host in the README). **Check:** `https://your-site/.env` and `https://your-site/storage/` give 403 or 404.

## 3. `.env`

Start from `.env.example` and change these:

| Setting | Live value |
|---|---|
| `APP_ENV` | `production`. Turns off demo users, demo login, the style guide and `--fresh`. |
| `APP_DEBUG` | `false` |
| `APP_URL` | The `https://` address, with no trailing slash. Email links and QR codes use it. |
| `ORG_CONTACT_EMAIL` | AGILE's real address. Also set the same one in Administration › Settings. |
| `DB_USER`, `DB_PASS` | A database user for `agile_ams` only, not `root`, with a strong password. |
| `ENCRYPTION_KEY` | A new key from `php jobs/generate_key.php`. Keep a copy somewhere safe and never change it once data exists: welfare records cannot be read without it. |
| `MAIL_DRIVER` | `smtp`, with the `SMTP_*` and `MAIL_FROM_*` values. |
| `MAIL_LOGO_URL` | Optional: a public `https://` PNG if the email banner should come from the site. |
| `DEMO_BULK` | `false` (ignored in production anyway). |
| `AI_PROVIDER` | `none`, unless AGILE has agreed to send eligibility documents to the provider. |

## 4. Database

**New database (recommended):**

```
php database/setup.php
php database/report.php
```

With `APP_ENV=production`, setup creates the tables and reference data only, with no demo people. The report should end with three 0s.

**A database that was used for testing:** back it up, then remove the demo data.

```
php database/remove_demo.php          # lists what would be removed; changes nothing
php database/remove_demo.php --yes    # backs up, then removes it
```

It removes every `@agile.test` account and applicant and what belongs to them. Real records stay: where a real record only names a demo account as the one who approved or decided it, that name is cleared. If a demo account wrote something on a real record that cannot be left without a name, such as an evaluation of a real applicant, it stops and lists it, and nothing is removed until that is sorted out in the app. Files are moved to `storage/removed-demo/`, never deleted.

## 5. The first MSW Committee Head

The MSW Committee Head approves every other account, so create that account first:

```
php jobs/create_user.php their.email@iskolarngbayan.pup.edu.ph First Last msw_head 2021-00000-OU-0
php jobs/send_email_queue.php
```

They get an email to set their password. They then add the other officers in Administration › Users; members come in through approved applications and `/register`.

## 6. Scheduled jobs

Set up every job in [scheduling.md](scheduling.md), with crontab on Linux or Task Scheduler on Windows. At least `send_email_queue.php` must run, or no email goes out. **Check:** Administration › System health shows each job's last run.

## 7. Backups

`backup_database.php` runs daily. Copy `storage/backups`, `storage/uploads`, `storage/credentials` and the `ENCRYPTION_KEY` off the server regularly. How to restore is in [restore.md](restore.md).

## 8. Last checks

- [ ] Run `php jobs/preflight.php` on the server. It checks the settings in step 3, demo data, a real MSW Committee Head, migrations, folders and PHP. It must end with "Ready to go live." (exit code 0).
- [ ] Log in as the MSW Committee Head and open Administration › System health: no warnings.
- [ ] No `@agile.test` users: `php database/remove_demo.php` says "There is no demo data".
- [ ] Check that a real email arrives (the set-password email from step 5 is one) and looks right in Gmail.
- [ ] Apply once as a guest and track it with the code, then reject that test application.
- [ ] Ask AGILE's adviser to review the Privacy Notice, and replace the sample mission and vision in Settings.
- [ ] Remove `jobs/test_notify.php` from the server (it already refuses to run in production).
