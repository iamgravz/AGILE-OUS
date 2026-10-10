# AGILE OUS — Development & Incident Operations

## Local developer setup (synthetic records only)
1. Install Docker Engine / Docker Desktop and Compose.
2. Copy `.env.example` to untracked `.env`.
3. Generate two **different** 64-character hex secrets with `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`.
4. Set `APP_KEY`, `WELFARE_ENCRYPTION_KEY`, `DB_PASSWORD`, `MYSQL_ROOT_PASSWORD` in `.env`.
5. Run `docker compose up -d --build`; visit http://127.0.0.1:8080.
6. Apply SQL migrations **once in order** (001 through 008) to a fresh DB, from a trusted local SQL client. Prefer a disposable DB for testing.
7. Create a **fictional** test staff account with the CLI script. Do not paste passwords into chats or logs.
8. Run `php tests/permissions.php` and `php tests/welfare_security.php`. Run DB tests against test-only database.
9. Set `APPLICATIONS_OPEN=true` only in isolated development for form testing; the compose configuration intentionally keeps it false.

## Backup & restore drill
- Use your database platform's vetted `mysqldump --single-transaction --routines --triggers` equivalent.
- Store backups **outside** the public document root and repository, encrypted in transit and at rest.
- Verify restore on a separate isolated environment after each schema release, including referential integrity.
- Keep DB, APP_KEY and WELFARE_ENCRYPTION_KEY backups coordinated; losing the welfare key may make case descriptions permanently unreadable.
- Never rotate the welfare key in place without a tested re-encryption and rollback migration.

## Operational incident checklist
1. Disable `APPLICATIONS_OPEN` if public intake is suspected compromised.
2. Restrict admin/staff traffic and preserve relevant logs while excluding welfare narratives.
3. Revoke affected sessions and credentials; rotate exposed keys with a controlled migration plan.
4. Notify designated incident/privacy officers and assess required reporting timelines.
5. Restore from known-good artifacts and verify application permissions before reopening.
6. Produce an incident chronology, affected data scope and remediation record.

## Known risks
- Per-IP public limiter and per-account login limiter need concurrency-safe enforcement, cleanup and reverse-proxy protection.
- No external SMTP/Gmail transport, no MFA, no tested backup automation, no data-subject deletion workflow.
- Authorization is checked in PHP; production should have independent security tests and segregated operational roles.
- Reporter CSV references are pseudonymous, not anonymous.
- Welfare descriptions are encrypted with AES-256-GCM; metadata and application database backups still require protection.
