# Phase 8 — Staff MFA and Confidential Student Academic Evidence

**Development-only.** This PR builds on Phase 7, preserves earlier features, and uses only synthetic CI records. Do not deploy to actual students until governance, privacy, and independent security testing are complete.

## Privileged MFA

All current privileged server roles — MSW Head, MSW Member, President, Technical Admin, Committee Head/Deputy, Executive Officer, and Source Code Editor — require a second factor *on every new login session*.

- `Auth::requireRole()` validates role and MFA session; additionally, `public/index.php` applies a default-deny MFA gate **before all routed requests** for a password-authenticated privileged session.
- A staff account cannot reach dashboards, confidential welfare details, membership applications or academic records until MFA setup/verification succeeds.
- First enrollment at `/mfa/setup` requires current password re-authentication. A random 160-bit TOTP secret is encrypted with libsodium SecretBox before storage and is displayed only in the enrollment response. The service refuses enrollment if the encryption key is missing.
- Time-based codes use RFC 6238 (SHA-1, 30-second window, 6-digit, ±1 time step). Last accepted time step is stored, so the same TOTP code cannot be replayed.
- Eight high-entropy single-use recovery codes are generated when enrollment is confirmed, shown once, and **only SHA-256 digests** are stored. Recovery code consumption is atomic inside a DB transaction.
- MFA failures are counted per account over 15 minutes with a five-attempt lockout. Session ID rotates after successful setup or verification; privileged MFA sessions expire after 8 hours or 30 minutes of inactivity. Role changes invalidate the step-up authorization.
- Existing member-only roles remain password-authenticated; linked officers with privileged roles require MFA even for their own member portal.

### Required operations and rollout safety

Before enabling actual privileged accounts, provision `MFA_KEY_B64` as a protected, backed-up environment secret (base64 of a cryptographically random 32-byte key). **Never put the secret into the repository, CI logs, email, screenshots, or a student record.** Configure `SESSION_SECURE=true` behind HTTPS and reliable server sessions. Local CI injects a random, disposable key for test accounts only.

**Production deployment blockers:** approved staff identity/onboarding and recovery procedure, supervised first-factor registration or enrollment invitation, formal offboarding, MFA code recovery with independent review, hardened rate-limiting and reverse-proxy controls, device/session inventory, and security penetration testing. A lost encryption key prevents validation of existing stored TOTP secrets.

## Academic appeal evidence

- `academic_review_request` is added as a private-file owner type; files remain below `storage/private`, outside the web root.
- An authenticated member must be **explicitly linked to the owning `members.user_id` record** to attach evidence to a review request.
- A member can upload only while the review request is open (`submitted` or `in_review`).
- Only that linked member and MSW Head can fetch the evidence; President, technical admin, other officers and unrelated students are denied.
- File type checks already restrict to PDF/JPEG/PNG and size <=5 MiB. File access is audited, with MIME-based extension and opaque random storage keys. Files must be malware-scanned, retention-managed and backed up before real use.
- Download handler serves an attachment with `Cache-Control: private, no-store`; filenames are not taken from untrusted uploaded data.
- Members can submit correction/appeal records and additional evidence through the own-account page. The MSW caseworker sees the same file through the restricted review inbox.

## Verification

Quality Gate:
- PHP lint for every PHP file
- `php tests/mfa_totp.php` RFC 6238 reference vectors
- Existing draft-bylaws, backup encryption and general smoke tests

Isolated MySQL Integration (synthetic only):
- `php tests/mfa_integration.php` encrypted enrollment, password reauthentication, monotonic counter, one-use recovery and brute-force limit.
- Existing recruitment, Welfare, email and Phase 7 integration tests.
- Academic owner/evidence tests reject President and unrelated students; closed review cases reject new uploads.
- HTTP smoke test proves password login cannot open protected routes, completes TOTP setup and opens dashboard, and requires a second-factor recovery code on the next login.

### Known limits

MFA setup using only a password requires trusted, verified staff onboarding to avoid an attacker with stolen credentials claiming an unregistered MFA device. Email or SMS fallback is deliberately not available. Academic evidence uploads are **not a malware-scanning system**; do not use real documents until scanning, quarantine, safe file-preview policy, data retention and privacy controls are approved. Backups in previous phases cover MySQL but not encrypted private file storage. These remain launch blockers.

**Policy caveat:** Academic grade screening is still based on the unratified draft AGILE bylaws only for provisional advisory flags. MFA and evidence uploads do not authorize sanctions, automatic officer disqualification, or release of grades to the President.
