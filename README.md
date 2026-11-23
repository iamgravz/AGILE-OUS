# AGILE Membership and Student Welfare Management System

Web application for the Academic Guild of IT Learners and Experts (AGILE), the BSIT student organization of the PUP Open University System. The full specification is in [docs/PROJECT_CONTEXT.md](docs/PROJECT_CONTEXT.md), and build progress is in [docs/PROGRESS.md](docs/PROGRESS.md).

## What it does

- **Public portal:** about AGILE, committees, news, events (members register), publications, open positions, Ask for Help, tracking by code.
- **Membership:** apply without an account (email code), the questions of AGILE's registration form, documents with version history, decisions by the Committee Head (BR-20), member records, ID and certificate requests, member verification by the QR on the ID, candidacy for elected offices.
- **Recruitment:** HR requests, vacancies, interviews with AGILE's question bank, the MSW interviewer's assessment, the Applicant profile, appointments.
- **Student welfare:** confidential, encrypted cases with categories, priority, referrals, follow-ups and closure.
- **Eligibility screening** of officers each semester.
- **Communications:** email templates and drafts, in-app notifications with preferences, content publishing, event attendance, service requests.
- **Administration:** six roles with a tested permission matrix, dashboards, search, reports (PDF/CSV), audit log, settings, backups and document archiving.

## Requirements

- PHP 8.2 or newer with the `fileinfo`, `gd`, `mbstring`, `openssl`, `pdo_mysql` and `zip` extensions. `exif` is recommended, so sideways phone photos are straightened
- `upload_max_filesize` and `post_max_size` in `php.ini` of at least 6M, because uploads can be up to 5 MB
- Composer 2
- MySQL 8 or MariaDB 10.6+ (used from the database step onward)

## Install

```
composer install
cp .env.example .env
```

On Windows Command Prompt or PowerShell, use `copy .env.example .env`. Then edit `.env` for your machine, including your MySQL password in `DB_PASS`.

## Database

```
php database/setup.php
```

This creates the database if it is missing, applies `database/schema.sql`, seeds the data, and prints a report. Use `--fresh` to delete the database and rebuild it; this is refused when `APP_ENV=production`. To print the report again, run `php database/report.php`.

To upgrade an existing database, run the same command without `--fresh`: it keeps your data and applies `database/migrations/`.

Outside production, this also creates sample users with the password `Password123!`. Set `SAMPLE_BULK=true` in `.env` first to add 100 more sample people at every stage, for trying lists, filters and reports. See [docs/database.md](docs/database.md) for the table reference.

## Run

**PHP built-in server**

```
php -S localhost:8000 -t public
```

Open http://localhost:8000.

**XAMPP virtual host**

Point a virtual host at the `public` folder, which is the only folder that should be reachable from the web. `mod_rewrite` must be on and the folder must allow `.htaccess` overrides:

```apache
<VirtualHost *:80>
    ServerName agile.test
    DocumentRoot "C:/xampp/htdocs/agile/public"
    <Directory "C:/xampp/htdocs/agile/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Add `127.0.0.1 agile.test` to your hosts file and set `APP_URL=http://agile.test` in `.env`.

## Tests

```
composer test
```

This drops and rebuilds its own databases, `agile_ams_test` and `agile_ams_test_http_test`, and uses a temporary folder for files, so your data and `storage/` are not touched. It sends no email. `docs/test-cases.md` lists every test case, with manual steps for the ones a person has to check.

## Email and background jobs

Modules queue email through `NotificationService`, and `jobs/send_email_queue.php` sends it. Schedule that job every 5 minutes with Cron or Windows Task Scheduler.

With `MAIL_DRIVER=log`, emails are written to `storage/mail/` as `.html` files instead of being sent. To try it:

```
php jobs/test_notify.php you@example.com
php jobs/send_email_queue.php
```

Job activity is logged to `storage/logs/jobs.log`.

## Encryption key

Run `php jobs/generate_key.php` once and put the output in `ENCRYPTION_KEY` in `.env`. Keep a safe copy: encrypted welfare data cannot be read without this key.

## Going live

Follow [docs/deployment.md](docs/deployment.md): production `.env`, a database with no sample data (`php database/remove_samples.php` cleans one that was used for testing), the first MSW Committee Head account, scheduled jobs and backups.

## Style guide

With `APP_ENV=local`, open `/styleguide`, `/styleguide/dashboard` and `/styleguide/auth` to see every component and status badge. These routes do not exist in any other environment.
