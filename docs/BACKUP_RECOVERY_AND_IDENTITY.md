# AGILE OUS — Secure Identity, Mail Recovery & Backup Runbook

**Status:** Development and synthetic-data validation only. Not yet authorized for real student records or public launch.

## 1. Identity linking (two-person verification)

A staff account and a member record are **separate identities** until explicitly linked:

1. Staff signs in, opens `/staff/identity` and sees only **their own** matching, approved member records (same registered email, active organizational role).
2. Staff enters their current password; system verifies it server-side and creates a 48-hour, single-active-request identity link.
3. Staff **cannot approve their own request**.
4. MSW Head reviews other officers, committee staff and publication accounts. Technical Admin may review **MSW Head identity only** without gaining access to welfare/grades.
5. Approval re-checks password-confirmed request metadata, account status, same email, active role assignment and member availability under a MySQL transaction, then links `members.user_id` to `users.id`.
6. Academic demotion (when policy approved, manual decision documented and gate enabled) can now revoke privileged access from the **linked** account in the same transaction.

The ordinary General Member account activation invitation remains available. Existing staff with a matching email should use independent linking rather than creating a duplicate member login. Passwords are never logged or emailed.

**Still required for production:** staff onboarding/offboarding playbook, privileged MFA, secure account recovery, prevention of unattended sessions, and independent policy authorization for handling grade-related role changes.

## 2. Email idempotency, uncertain delivery and recovery

- Every notification uses a unique `event_key` in `notification_outbox` to prevent duplicate queue entries.
- When a worker safely identifies failure **before** Gmail submission, limited retry/backoff can occur.
- Once an actual Gmail send POST is attempted, any unresolved transport outcome is categorized `needs_review`. **Never automatically resend ambiguous deliveries.**
- Interrupted `processing` jobs older than 10 minutes are moved to `needs_review` by the recovery job.
- MSW Head can review blocked/failed/uncertain messages at `/staff/email/reconcile`. Reviewer documents provider investigation in a mandatory 30–1,000-character note and chooses `mark_failed` or `requeue`. Gmail transport must be configured to requeue.
- Decisions are kept in `mail_delivery_reviews` and central audit logs.
- `submitted` means Gmail accepted the API submission. It does **not** mean the recipient received or read the email.
- If `MAIL_TRANSPORT=disabled`, messages never leave the system.

No real messages were sent during development or GitHub Actions testing.

## 3. Encrypted database backups

The command-line backup tool uses MySQL `mysqldump` and PHP libsodium secretstream XChaCha20-Poly1305. It writes an encrypted `.abk` file directly without creating a plaintext dump on disk.

**Preconditions:** PHP 8.2+ with `sodium`, MySQL client tools (`mysqldump`, `mysql`), database read privileges, a private backup directory outside the web root and a securely provisioned 32-byte key.

Generate a fresh backup key **locally on a trusted machine**, and store it in a secret manager. Never commit or display it in GitHub:

```bash
php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
```

Configure `AGILE_BACKUP_KEY_B64` as a host-injected environment variable or protected `.env` entry, and set `AGILE_BACKUP_DIR` to a private directory with restricted access.

```bash
php scripts/secure_backup.php backup
php scripts/secure_backup.php verify /path/to/agile-YYYYMMDD-HHMMSS-xxxx.abk
```

The utility verifies the AEAD authentication tag after backup. Wrong keys, tampered bytes and truncated files fail verification. No unauthenticated backup must be trusted.

### Isolated recovery test (not directly to production)

First provision an **empty separate database** named `agile_restore_test` with a dedicated restoration account. It must not be the live `agile_ous` database. Set the safety gate, then execute:

```bash
AGILE_ALLOW_ISOLATED_RESTORE=true php scripts/secure_backup.php restore /path/to/file.abk agile_restore_test
```

Restore refuses any database name outside `agile_restore_*`, the configured live DB name, and non-empty targets. It checks the encrypted file integrity **before** invoking MySQL restore, with no plaintext intermediate file.

**A full recovery rehearsal must include** checking row counts and sample application, membership, welfare, and audit relationships with synthetic records; schema version; migrations; server roles; backup age; and ability to restore the matching private documents.

### Scope and limitations

- The backup utility encrypts **MySQL only**, not `storage/private` documents. Those require a separate encrypted, access-controlled backup with an independent restore rehearsal.
- Protect and rotate keys under a documented policy; losing the key makes the backup unrecoverable.
- Maintain secure offsite copies and retention scheduling based on an approved Privacy Impact Assessment, not arbitrary indefinite retention.
- Never send backup files or encryption keys to GitHub, issues, CI artifacts or email.
- Automated backup scheduling and production restore are **not enabled** by this pull request.
- Backup configuration, storage access, restore privileges and recovery tests must be reviewed before launch.

## 4. Local and CI validation

```bash
php scripts/migrate.php
php tests/cli_smoke.php
php tests/mysql_integration.php
php tests/workflow_integration.php
php tests/extended_integration.php
php tests/security_identity_mail_integration.php
php tests/backup_cipher.php
```

Integration suites using special `CI` and `RUN_E2E_TESTS` flags must run only against disposable databases with **synthetic** records. GitHub Actions also exercises the encrypted backup and isolated restore when client utilities are available.

## 5. Remaining critical work

Privileged MFA, comprehensive role-by-record access tests for private attachments, secure password reset, actual organizational identity/appointment roster reconciliation, encrypted private-file backups, emergency response policy, complete data privacy notice and consent handling, official eligibility bylaws approval, Gmail OAuth live-delivery testing with authorized test accounts, system observability, and approved HTTPS hosting remain launch blockers.
