# AGILE OUS — Membership & Student Welfare Management System

**Status: DEVELOPMENT / RELEASE BLOCKED.** This repository contains substantial working implementation code, but has **not** been demonstrated to pass complete tests or approved for real student records. Do not advertise it as deployed or production-ready.

## Stack
PHP 8.2+ (PDO MySQL, OpenSSL), MySQL 8, server-rendered HTML, GitHub Actions. Docker configuration is **local development only**, not a production deployment.

## Implemented modules
- Staff accounts: password-hash login, session regeneration, CSRF, permission checks, and basic account-identifier throttling.
- Membership: non-login applicant form (disabled by default), period-specific duplicates, interview state, positions, head approval/rejection, transactionally checked vacancy limits and change history.
- Recruitment: staff interview scheduling, three-criterion evaluation, position capacity configuration and term-specific vacancies.
- Notification drafts: prepare, review/approve or cancel messages. **No actual email transport**, no message is automatically sent.
- Reporting: status totals by year/semester, leadership-only pseudonymous CSV export.
- Welfare: internal staff-only case creation, encrypted descriptions, category/priority/status, assignment, case-specific read checks, event history and audited detail reads. **Not a public welfare submission portal**.
- Operational: CLI preflight, GitHub Actions lint / RBAC / cryptography / anonymous HTTP / MySQL schema / synthetic integration smoke checks, local Docker dev setup, release-readiness runbook.

## Quick local setup (fictional test data only)
1. Copy `.env.example` to `.env` and fill in DB_PASSWORD, MYSQL_ROOT_PASSWORD, APP_KEY and WELFARE_ENCRYPTION_KEY. Generate distinct random 32-byte values for the last two using `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`.
2. Run `docker compose up -d --build` using Docker Desktop or Docker Engine with Compose.
3. Apply `database/001_initial.sql` through `database/008_public_submission_guard.sql` **in numeric order once** into the newly created `agile_ous` test DB via a trusted local MySQL client. Existing populated DB migrations require separate review/backups.
4. Create an authorized fictional staff account with `php scripts/create_user.php staff@example.test membership_head "Test Staff"` using PHP connected to the database.
5. Open http://127.0.0.1:8080. For public form testing only, set `APPLICATIONS_OPEN=true` in a separate isolated local environment; the Compose service explicitly keeps submissions closed.
6. Run `php tests/permissions.php`, `php tests/welfare_security.php`, `php tests/database_smoke.php`, `php tests/integration_smoke.php` (integration requires APP_ENV=testing), and `php scripts/preflight.php` with correct configuration. GitHub Actions config automates these checks.

The local web container intentionally binds only to 127.0.0.1 and uses PHP's development server. Never expose it on the public internet.

## Permission summary
- President: organizational aggregate dashboard **only**, no confidential case records and no write controls.
- Membership Head: memberships, positions, interviews, evaluations, decisions and export.
- Membership Member: view applications; destructive or final approval operations restricted to Head/Admin.
- Welfare Head: full internal welfare case workflow and assignment.
- Welfare Member: creates cases and accesses/updates only personally created or assigned cases.
- Admin: broad technical access, **pending final governance approval** and separate least-privilege/break-glass review.

## Migration sequence
`001_initial` → `002_security` → `003_membership` → `004_recruitment` → `005_recruitment_automation` → `006_vacancy_integrity` → `007_welfare` → `008_public_submission_guard`.

Run each migration exactly once in order on a new development database. These early migrations are not guaranteed to be idempotent on a populated deployment.

## Testing & review
See `docs/RELEASE_READINESS.md` and `docs/OPERATIONS.md`. Passing basic CI is necessary but **not sufficient** for safe production launch.
Do not merge into main or activate public intake until independent review, authenticated HTTP tests, real concurrency tests, privacy review, incident response and backup recovery are verified.

## Known outstanding release blockers
- No successful fully verified CI + end-to-end QA report; no staging or production deployment.
- No privileged MFA / password reset or server-level IP rate limiting.
- Application self-declaration does not authenticate BSIT OUS eligibility; incomplete applicant privacy/retention handling.
- Notification drafts do not send emails; delivery opt-in, address verification and operator approval required.
- Welfare privacy/governance sign-off, confidential case retention policy, appropriate escalation and rights/requests processes not finalized.
- General member reclassification and semester lifecycle rules still require formally approved business-policy implementation.
- Production reverse proxy/TLS/HSTS, backup drills, monitoring, multi-user UAT and concurrent last-slot approval stress tests outstanding.

**Do not enter any real student welfare data or applicant PII until the release gates are completed.**
