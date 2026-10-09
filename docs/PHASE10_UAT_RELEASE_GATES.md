# AGILE OUS — Phase 10 Integration & Staging Acceptance

**Classification:** Development-only / synthetic-data UAT.  
**Base:** PR #9 (`feat/phase9-quarantine-file-backups`).  
**Bylaws:** The 2026-09-24 AGILE OUS Constitution and By-Laws **draft** remains explicitly **unratified**.  
**Decision:** **NO-GO FOR PRODUCTION** until the independent gates below are formally signed off. Passing GitHub Actions only validates the tested synthetic flows; it does not imply that a live staging site exists.

## Acceptance coverage

| User journey / requirement | Automated evidence | Remaining human review |
|---|---|---|
| Public Membership registration | `tests/http_smoke.sh` submits a role-specific synthetic application and verifies reference persistence | Final form wording, accessibility, privacy notice, and applicant identity verification |
| HR requisition and vacancy capacity | `tests/extended_integration.php`: approve HR request → publish 1 vacancy → interview/evaluate → appoint and update capacity | Committee naming/quotas, identity and true vacancy allocation |
| Reviewer assignments / MSW Head approval | `tests/workflow_integration.php` and extended tests enforce assignment and human verification | Organizational staffing delegation and approvals |
| Welfare confidential case intake and tracking | Synthetic submission, assigned reviewer only, token-based public status, case triage and resolution | Emergency escalation, support referral directory, response SLAs, retention, consent |
| President read-only oversight | `tests/role_matrix_http.sh`: aggregate dashboard allowed, applicant/welfare/academic details refused | Confirm final policy on aggregate statistics and minimum privacy thresholds |
| Technical Admin boundaries | Admin identity-review access allowed; applicant, welfare, grade and email content denied | Administrator separation of duties and emergency operations |
| The Source Code / content publication | `tests/extended_integration.php`: editor drafts, authorized publisher approves, public post appears | Editorial authority, article guidelines and moderation policy |
| Student membership accounts and IDs | Linked accounts, activation and opt-in public verification tests in previous suites | Policy for ID validity, identity proof, production ID asset design |
| Semestral academic monitoring | `tests/academic_casework_integration.php`: appointed staff included, general excluded, draft flags advisory, corrections/appeals logged | Approved academic policy (currently DRAFT), who reviews W/D/INC and appeals |
| Privileged MFA | `tests/http_smoke.sh`, `tests/mfa_integration.php`: password-only blocked, TOTP setup, one-time recovery | Supervised first enrollment, key escrow/rotation, device loss recovery |
| Confidential uploaded documents | `tests/attachment_scan_integration.php`: quarantine, infection, hash tamper, failed scanner | Actual ClamAV operations, malware-signature updates, sanitization, retention |
| Encrypted MySQL + private-file backups | CLI isolated restore / file identity tests in CI | Consistent DB/files snapshot, offsite restore, RPO/RTO signoff |
| Email drafts and outbox | Queue idempotency, disabled transport, uncertain delivery hold, authorized operator review | Real Gmail OAuth with authorized test account, DNS / deliverability and unsubscribe where appropriate |
| Privacy-safe cross-system integrity | `tests/acceptance_integrity.php` produces aggregate pass/fail counts without student identity | Evidence review, independent security audit, operational controls |

## Role permissions: HTTP acceptance contract

The suite `tests/role_matrix_http.sh` creates only `@example.invalid` synthetic accounts:

| Route / capability | MSW Head | MSW Member | President | Technical Admin | General Member |
|---|:---:|:---:|:---:|:---:|:---:|
| MSW confidential welfare dashboard | Yes | Only assigned cases | No | No | No |
| Academic grade review / appeal inbox | Yes | No | No | No | No |
| Membership applicant details | Yes | Only assigned | No | No | No |
| HR request management | Yes | No | No | No | No |
| Email drafts | Yes | Draft only | No | No | No |
| Email-delivery reconciliation | Yes | No | No | No | No |
| Overview dashboard | Detailed | Assigned-only | Aggregates | Aggregates | No |
| Own semester summary | If linked | If linked | If linked | No | If linked |
| Independent staff-to-member identity review | MSW Head reviews other staff | No | No | MSW Head only | No |

**All privileged sessions require MFA**, including read-only President and technical Admin. The matrix checks that password-only login cannot access the dashboard, then authenticates with a one-use synthetic recovery code before testing permissions.

## CSRF and public-route regression

A missing CSRF token on the very first request used to compare equal to another missing token. Phase 10 fixes `verifyCsrf()` so it requires **both** a server-generated 64-character token and matching submitted token. HTTP tests now deny:

- Tokenless first-request public application POST (419)
- Tokenless first-request confidential Welfare POST (419)
- Existing-session POST with token omitted (419)
- Explicit blank token (419)

A correct token is still accepted in the ordinary application, login, logout, MFA and protected review workflows.

## Aggregate integrity acceptance gates

`app/AcceptanceAudit.php` checks **counts only** (no raw student data) for the full migration set, unapproved original draft bylaws, approved applications linked to member records, active membership source status, vacancy capacity reconciliation, prohibited academic use of unapproved policy, General Member role restrictions, clean-file digest integrity, Gmail provider reference, completed appeal reviewer evidence, and duplicate pending academic appeals.

To produce a read-only JSON gate report in a **deliberately isolated CI database**:

```bash
CI=true RUN_E2E_TESTS=yes php scripts/acceptance_audit.php
```

Or run with `APP_ENV=staging` against an approved staging environment. If any gate fails, the command exits nonzero. Passing this report does not authorize deployment. It does **not** inspect real grade values, welfare narratives, or uploaded documents.

The rollback-only negative suite deliberately simulates three **synthetic** corruptions—an accidentally approved draft bylaw version, mismatched vacancy count, and a cleared file without a digest—and confirms each is detected. It then rolls back every change and rechecks all gates. This avoids an always-green audit that merely reports healthy data without detecting invalid states.

## Exact execution in GitHub Actions

The existing `MySQL Integration` workflow boots an isolated MySQL 8 test service, applies all schema migrations twice to ensure idempotence, runs the previously tested domain suites, verifies encrypted database and private-file restores, starts the PHP test server, executes authenticated registration/MFA HTTP smoke, then adds:

```bash
UAT_CREDENTIALS_FILE="$RUNNER_TEMP/agile-uat-credentials.txt" php tests/seed_uat_accounts.php
UAT_CREDENTIALS_FILE="$RUNNER_TEMP/agile-uat-credentials.txt" bash tests/role_matrix_http.sh
php tests/acceptance_integrity.php
php tests/acceptance_gate_negative.php
php scripts/acceptance_audit.php
```

Recovery codes exist **only** in an ephemeral runner-local file with mode `0600`; they are not committed, uploaded as artifacts, or printed in CI logs. The credential file holds synthetic test identities exclusively. It is deleted when the runner is discarded.

## Mandatory human staging acceptance (NOT automated / not complete)

Before any real-data rollout, independently document the **actual** result for each item. No default approvals:

1. **Organizational authorization:** Official AGILE officers authorize the project's purpose, scope, staff roles and data policies. PUP Open University institutional permissions are verified where necessary.
2. **Draft bylaws:** Article III membership categories and Article VI officer candidate qualifications are reconciled and formally approved. Until then, academic flags cannot cause adverse decisions.
3. **Privacy and incident handling:** Final notice, lawful processing basis, retention and erasure, consent/referrals, emergency escalation, breach response and account/data subject handling.
4. **Identity and security:** Privileged MFA enrollment with verified officers, disaster recovery of MFA and secrets, systematic access testing, independent penetration review, CSRF/auth/session and upload threat model.
5. **Operational ClamAV:** Real scanner enabled on staging, current signatures, incident runbook and alerting, upload quota/rate limits, unacceptable formats rejected.
6. **Backup restoration:** Real offsite encrypted DB+private-file snapshot restored to a totally separate host; relationships and hashes verified, recovery targets and key custody approved.
7. **Real browser and accessibility UAT:** Android/mobile, desktop, keyboard-only and assistive tech for every applicant/member/staff role; fix blocking defects and save signed evidence.
8. **Email and integration:** Gmail OAuth secrets only from approved owner; test queued/disabled/uncertain states with safe test addresses. External AI remains off pending privacy review.
9. **Staging environment:** Dedicated HTTPS hostname, no real data, server access and PHP-FPM configured, real ClamAV, protected secrets, staging preflight passed, monitoring and rollback rehearsal.
10. **Release governance:** Dependency PRs reviewed/merged in order; rollback rehearsed; release owner, security reviewer, privacy approver and operational contact approve with dated evidence.

**GO/NO-GO rule:** GitHub checks green AND all mandatory human gates recorded as approved AND valid data/legal authorizations present. Otherwise, **NO-GO**. Do not merge as production-ready or load real student records simply because synthetic CI passed.

## Known limits / next engineering work

- UI remains functional but does not replace design-system, accessibility and mobile-device UAT.
- No deployed staging host or browser-managed full user acceptance evidence is present in this PR.
- ClamAV malware scanning is simulated in CI only; actual staging ClamAV must be installed, configured and monitored.
- Database and private files are backed up separately, not an atomic snapshot.
- Published privacy notice, final bylaws and official role allocation still need signoff.
- Real Gmail OAuth delivery and AI-grade processing are intentionally disabled by default.
