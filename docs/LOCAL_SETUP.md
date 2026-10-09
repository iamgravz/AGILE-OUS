# AGILE OUS — Local Development Setup

## Requirements
- PHP 8.2+ with `pdo_mysql`, `mbstring`, `fileinfo`
- MySQL 8.0+ or compatible MariaDB, Composer
- Git; Windows: PHP and MySQL from XAMPP are acceptable for local testing

## Setup (development only)
1. Clone the repository and checkout `feat/phase1-auth-membership`.
2. Copy `.env.example` to `.env` (never commit it). **This initial milestone reads environment variables; it does not yet parse the .env file automatically.** Configure variables in your shell or server environment.
3. Create a local MySQL database and restricted local account:
   `CREATE DATABASE agile_ous CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
   `CREATE USER 'agile_app'@'localhost' IDENTIFIED BY 'replace-with-strong-password';`
   `GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES ON agile_ous.* TO 'agile_app'@'localhost';`
4. Set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` as environment variables. For production set `SESSION_SECURE=true` and use HTTPS.
5. Run `php scripts/migrate.php` and `php scripts/create_admin.php head@example.invalid "MSW Head"` with **synthetic local-only credentials**.
6. Start `php -S 127.0.0.1:8000 -t public public/router.php` and visit `http://127.0.0.1:8000`.
7. Run `php tests/cli_smoke.php`. For end-to-end validation, register a dummy applicant, log in as local MSW Head and confirm the application appears in the staff dashboard.

## Current implementation boundaries
This is a functional **first milestone**, not the entire completed system. It has public registration, database persistence, secure staff sessions, login throttling, read-only listing and audit records. Other modules (full registration wizard, staff approval actions, Welfare, Gmail, CMS, academic verification, digital ID) remain to be implemented. Do not process real student data until authorization and privacy safeguards are completed.

## Known hardening items
- Add environment loading (e.g. phpdotenv), tests against a real MySQL service, integration tests for CSRF/session and concurrent writes.
- Add email verification or secure application tracking; never expose applicant data using reference code alone.
- Add record-level and field-level policies before expanding staff access.
- Replace visible CLI password entry with a non-echoing secure provisioning method.
- Define a migration strategy for partial MySQL DDL failures and schema upgrades.
