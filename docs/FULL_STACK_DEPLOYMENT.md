# AGILE OUS — Full PHP/MySQL deployment handoff

## Required platform
An always-available PHP 8.3 Apache container with persistent MySQL 8 and reliable PHP session persistence. Examples are a VPS with Docker Compose or a PHP-capable container PaaS coupled to managed MySQL. **GitHub Pages and static Vercel hosting do not execute this PHP/MySQL application.**

## Current release gate
NO-GO for real users until docs/RELEASE_READINESS.md is signed off. `APPLICATIONS_OPEN=false` must remain set. The GitHub static staging page is not this backend.

## Container
`Dockerfile` provides PHP 8.3, Apache, PDO MySQL and OPcache, exposing only `public/` as the HTTP document root. Supply HTTPS termination via a managed ingress/reverse proxy. Hosting providers may require `PORT` and/or a specific listen port; adapt Apache to provider requirements before deploy. The included health check does not establish database readiness.

## Required environment (set in hosting secret management, not in source)
- APP_ENV=production
- APP_DEBUG=false
- APP_TIMEZONE=Asia/Manila
- DB_HOST, DB_PORT=3306, DB_NAME, DB_USER, DB_PASSWORD
- APP_KEY: separate random string, at least 32 characters
- WELFARE_ENCRYPTION_KEY: distinct 64-character random hex string, safely escrowed
- SESSION_SECURE=true
- APPLICATIONS_OPEN=false

Never enter secrets into repository files, support tickets or chat. Enable TLS certificate validation for remote MySQL at the driver/hosting level; database connectivity should be private when possible.

## Database bootstrap
Create database using a restricted database account, apply `001_initial.sql` through `008_public_submission_guard.sql` exactly once on a disposable staging database in numeric order. Migration 005_vacancies_notifications.sql comes before 006_vacancy_integrity.sql. **Do not run initial ALTER migrations blindly on an existing populated database**; back up, review the current schema, and use a reconciled migration plan.

## Verification sequence
1. Pass the latest GitHub Actions quality gate.
2. Run `scripts/preflight.php` using the staging environment; ensure secrets exist without printing them.
3. Test fictional staff sign-in, CSRF, session persistence, logout, role restrictions and account deactivation.
4. Test fictional applicant workflow, interviews, position assignment, approval, report export and notification drafts.
5. Verify welfare case encrypt/decrypt, assign/read permissions and audit rows with synthetic cases.
6. Run concurrent last-position approval tests and anti-abuse tests.
7. Run backups/restore drills, TLS configuration audit, monitoring and incident plan.
8. Complete privacy governance, Data Privacy Act review, retention and authorized organizational approvals.
9. Enable actual student intake only after formal release approval.

## Sessions and scaling
Default PHP session storage is local to the instance. For more than one instance or ephemeral hosting, configure an audited shared session handler (Redis or database) with restrictive credentials, encryption in transit and session expiry before enabling horizontal scaling. Never put sensitive session content into public client storage.

## Avoid unsafe early launch
- No welfare details in a public URL or static build.
- No built-in PHP dev server in production.
- No auto-sending applicant email without a reviewed mail transport.
- No public student submissions before verification of eligibility, privacy and retention.
- No claims of end-to-end readiness based solely on syntax or smoke tests.
