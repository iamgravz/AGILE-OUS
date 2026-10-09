# Phase 9 — Secure Files, Encrypted Document Backups and Staging Readiness

**Status:** Development only. This branch depends on PR #8 and has no approved production deployment. Use synthetic data until AGILE/PUP authorization and privacy controls are formally established.

## 1. Fail-closed private-file quarantine

Migration `013_attachment_quarantine.sql` adds `scan_status`, `content_sha256`, scan timestamps and an audit-oriented `security_events` table. **All legacy and new files default to `quarantined`**, regardless of previous availability. No silent migration to clean is allowed.

The upload flow preserves the previous Membership/Welfare/Academic file features, storing files under the protected `storage/private` directory (never under web root). The user-facing page displays security status rather than a usable download or AI-processing link until an approved scanner clears the file.

Run from a **trusted CLI worker**, not a public HTTP route:

```bash
# After provisioning ClamAV and updating fresh signatures:
FILE_SCANNING_ENABLED=true FILE_SCAN_DRIVER=clamav php jobs/run_file_scanner.php
```

Only the fixed `clamscan` binary is invoked, through an argument array (not an interpolated shell command). A queued file transitions `quarantined → scanning → clean / infected / scan_error`.

A file is released **only** when its original recorded MIME and byte count remain unchanged, ClamAV exits cleanly, and its SHA-256 hash is recorded. Each actual download verifies the clean status and rehashes the file to detect later tampering. Infected files and failed scans cannot be downloaded. AI-grade extraction now uses the same clear-and-hash authorization.

When ClamAV is missing, outdated, times out or errors, files remain blocked (`scan_error`). Interrupted scans are also blocked. Any scanner errors require authorized review and correction; **never change database scan flags directly to clean**. An operator should install/update the scanner, investigate errors and requeue under a controlled procedure.

**Important limitations:** ClamAV is only one defensive layer. It cannot guarantee that any PDF/JPEG/PNG is free of exploits. Before real student uploads, implement appropriate document sanitization where feasible, antivirus signatures/health monitoring, retention periods, quotas, separate service permissions and incident procedures. CI uses a specially gated synthetic scanner (`APP_ENV=testing`, `CI=true`, `RUN_E2E_TESTS=yes`) that is NOT accepted in staging.

## 2. Separate authenticated private-file archive

The previous `secure_backup.php` covers **MySQL only**. This phase adds an encrypted archive for `storage/private`:

```bash
# Never put these environment secrets into code, PRs or logs.
export AGILE_FILE_BACKUP_KEY_B64="PROVISION_FROM_SECRET_MANAGER"
php scripts/private_file_backup.php backup
php scripts/private_file_backup.php verify /secure/backups/agile-files-XXXXXXXX.afb
```

The `AttachmentVault` uses libsodium XChaCha20-Poly1305 secretstream with authenticated per-file metadata, ordered opaque filenames, content hashes and size bounds. It streams encrypted frames without writing a plaintext tar/SQL archive to disk. Unknown files, symlinks, unsupported filenames and missing/unreadable sources fail backup rather than being silently excluded. It includes *all* private objects (including quarantined ones) to preserve recovery inventory; restore does not grant scan clearance.

Only a previously created **empty and isolated** directory whose basename matches `agile_restore_files_*` may receive restored private objects. The restore gate is disabled by default:

```bash
mkdir -m 700 /safe/isolated/agile_restore_files_rehearsal
AGILE_ALLOW_ISOLATED_RESTORE=true php scripts/private_file_backup.php restore \
  /secure/backups/agile-files-XXXXXXXX.afb /safe/isolated/agile_restore_files_rehearsal
```

The archive is authenticated in full before restoration starts. Any integrity failure or unsafe filename stops restoration and removes partial objects. Restore never overwrites existing files. No live storage restore mechanism is exposed by this command.

**Consistent recovery requires pairing** MySQL and private-file backups from a controlled, low-write maintenance window. These currently run independently, so this phase **does not implement an atomic database+filesystem snapshot**. A production recovery process must reconcile references and document inventory, including scanning statuses, student records and audit logs; practice full restoration before launch. Keep keys separate, securely backed up and rotated, with tested retention and offsite copies.

## 3. Security event summary

The scanner records security-event metadata (event type, severity and private object identifier only; no student narratives, filenames or grades). A read-only 24-hour count report is available to authorized operators over CLI:

```bash
SECURITY_MONITOR_ENABLED=true php jobs/run_security_report.php
```

The report does not email confidential records. It is off by default. A production alerting/incident-response system, centralized logging and log retention are separate deployment requirements.

## 4. TLS staging template and preflight

`deploy/staging/nginx.conf.example` is a **template**, not a deployed site. Operators must provide the approved staging hostname, certificate, private PHP-FPM upstream and app root. It:

- Redirects HTTP to HTTPS, uses TLS 1.2+/HSTS and restrictive security headers
- Sets `public/` as the **only document root**, and executes `/index.php` as the sole PHP front controller
- Denies hidden paths, SQL archives, backup formats, app source and runtime directories
- Limits upload sizes to 6 MiB and sends no-store headers

The staging preflight is:

```bash
php scripts/staging_preflight.php
```

It checks for staging environment, HTTPS, secure session cookies, all three 32-byte encryption keys, mandatory real ClamAV scanning and worker availability, disabled external AI/live mail, disabled grade-based role transitions, required PHP extensions and non-public document storage. It does **not** set up hosting or certify the production system.

The project must explicitly authorize the staging server, connect a synthetic isolated DB, and install trusted TLS, PHP 8.2+ with sodium/fileinfo/pdo_mysql, MySQL 8, ClamAV with current signature DB and a private backup directory. Never copy production data into staging for an ordinary student demo.

## 5. Automated synthetic CI checks

- Existing PHP Quality Gate, RFC-6238 MFA vectors, privacy, bylaws and encrypted DB backups
- New `tests/attachment_vault.php`: encrypted archive roundtrip, bad key, tamper, truncation, unsafe filename, fresh restore
- New `tests/staging_preflight.php`: rejects HTTP, insecure sessions, synthetic scanner in staging, role-transition enabling and missing backup keys
- New `tests/attachment_scan_integration.php`: quarantine prevents downloads, synthetic benign scanner clears, malware signature blocks, absent scanner leaves scan_error, tampering after scanning blocks download
- Separate synthetic private-file CLI backup/verify/restore workflow in MySQL Integration

**Do not run these tests against real student data.** CI's synthetic malware-marker scanner is intentionally impossible to select in actual staging through the preflight.

## Remaining launch blockers

1. Production-approved privacy and retention schedule under applicable Philippine data protection obligations
2. Malware signatures/status monitoring, staged incident response, quarantine purge/requeue procedure and file sanitization policy
3. Atomic or documented consistent DB+private-file snapshot plan, offsite backup, full disaster-recovery drill and acceptable recovery targets
4. Authorized supervised MFA enrollment/recovery, complete RBAC penetration testing and session-security review
5. Approved HTTPS domain/hosting, key manager, deployment access controls, observability, synthetic staging user acceptance tests
6. Final ratified AGILE bylaw versions and due-process authorization; keep all academic flags advisory meanwhile
7. Isolated Gmail OAuth acceptance testing and explicit privacy authorization for any external grade processing

**No production merge or deployment is authorized merely by passing CI.**
