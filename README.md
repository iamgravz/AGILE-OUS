# AGILE OUS — Membership & Student Welfare

**Status:** Phase 1 secure PHP/MySQL foundation; **not production-ready**. This is an incremental implementation, not a completed system.

## Requirements
PHP 8.2+ with PDO MySQL, MySQL 8+, a local web server.

## Local installation
1. Create a new empty MySQL database `agile_ous`.
2. Apply `database/001_initial.sql` to that database.
3. Copy `.env.example` to `.env` and configure local DB credentials. Never commit `.env`.
4. Create an authorized local test account: `php scripts/create_user.php admin@example.test admin "Local Administrator"`.
5. Launch local development server: `php -S 127.0.0.1:8000 -t public`. Visit `http://127.0.0.1:8000`.

Use a restricted MySQL application account, not root. On deployment set `SESSION_SECURE=true` and enforce HTTPS; keep the document root set to `public/`.

## Implemented in this branch
- Login using password hashes, session regeneration, CSRF protection, no-store authenticated responses.
- PDO parameterized login query and safe output encoding.
- Live MySQL counts for approved membership applications and pending applications.
- Aggregate-only open welfare case count for authorized roles; the President has no edit routes.
- Role helper functions for later endpoint authorization.
- Starter database migrations without collecting confidential case descriptions.

## NOT implemented yet (required before real use)
- Public membership form, consent/validation, semester verification, review/approval workflow.
- Welfare submission and workflow with case-level ownership, sensitive-field encryption, audit trail, retention policy and referrals.
- Fine-grained RBAC permissions for each endpoint; role helpers are not a replacement for policy tests.
- Rate limiting, account lockout, MFA, password reset, production security headers, backups and operational monitoring.
- Transactions, validation and concurrency handling for approvals; recruitment processes and exports.
- Verified tests, deployment pipeline, caching and cache invalidation.

## Data-handling warning
Do not input real student details or welfare concerns into this phase-1 build. Welfare details must never be stored in logs or a shared/public cache. Avoid caching authorization, approval states, sessions and live confidential records. Add short-lived caching only after correct live queries and invalidation tests are completed.

## Phase 2 incremental security changes
- Central role/permission mapping in `src/authorization.php`, default-deny with `requirePermission()`.
- Dashboard counters use the same role permission checks; President can see aggregate counts, not case details or writes.
- `database/002_security.sql` adds audit and throttling storage **only**; no audit events or throttling are wired to the login handler yet.
- `tests/permissions.php` is a CLI smoke test: run `php tests/permissions.php`. It has not been executed in an integrated deployment.
- Role model is provisional and will need approval for admin visibility, membership member approval rights and welfare case assignment.
- **Security blocker:** until login throttling, auditable writes, endpoint authorization and end-to-end tests are finished, do not use with real student data.
