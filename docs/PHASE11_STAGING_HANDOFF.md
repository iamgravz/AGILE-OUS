# AGILE OUS — Phase 11 unified staging acceptance and release handoff

**Current state: DRAFT, NOT DEPLOYED.** This branch integrates two independent Phase 10 test efforts while leaving **main and every existing PR unchanged**. All automated evaluations use synthetic `@example.invalid` accounts on isolated GitHub CI services.

## 1. Reconciliation of two different Phase 10 draft PRs

Two distinct draft branches are present in GitHub:

| Source | Distinct contribution | How Phase 11 preserves it |
|---|---|---|
| **PR #10** (`feat/phase10-staging-acceptance`) | Two-scoped-MS W-member Welfare and applicant cases, editorial draft publication denial, public Welfare tracking privacy, Committee Head, Source Editor, General Member digital ID opt-in/out, machine-readable acceptance report | Six source files copied unchanged into this branch; its integration steps appended to the existing MySQL CI sequence |
| **PR #11** (`feat/phase10-uat-release-gates`) | CSRF first-request protection, five-role authenticated/MFA permissions, 12 cross-module integrity invariants, deliberately corrupted synthetic rollback-only tests, human release requirements | Selected as this branch's base; all tests and documentation retained |

Both suites run **sequentially** in the same isolated CI environment, with unique fixture names so they do not overwrite one another. The 7-day artifact contains **only** aggregate schema, review and policy-gate metadata, never the temporary synthetic identity/OTP/Welfare tracking files.

### Before merging

**Do not merge PR #10 and PR #11 separately and then blindly merge this branch.** That would duplicate implementations and overwrite the independently changed `.github/workflows/mysql-integration.yml`.

Recommended engineering review order:
1. Keep PR #10 and #11 open as historical work while reviewing Phase 11's combined diff.
2. Review the two acceptance services (`AcceptanceReport` and `AcceptanceAudit`) against the same requirements; decide later whether to consolidate them into one API.
3. Verify all tests on Phase 11 head commit; perform additional secure code review and scan any UI/identity edge cases.
4. Confirm the complete dependency chain from PR #1 through PR #9; if governance authorizes a merge, merge bottom-up into main. Rebase Phase 11 against the accepted Phase 10 baseline as needed.
5. Close/supersede redundant PRs **only after** confirming every unique test scenario was retained, without losing review/audit history.
6. No production deployment or use of actual student data until all independent security, privacy, operational and organizational approvals are documented.

The mere existence of this integrated draft PR is **not** permission to merge.

## 2. New operator-facing preflight and technical handoff

`scripts/staging_readiness_handoff.php` is an opt-in CLI-only, fail-closed checker for an **already-provisioned and authorized staging host**:

```bash
APP_ENV=staging AGILE_UAT_READ_ONLY=true php scripts/staging_readiness_handoff.php
```

It checks `StagingPreflight::inspectEnvironment()` first. Only after infrastructure conditions pass does it start a **read-only MySQL transaction**, call both independent read-only acceptance services, and roll back the transaction. The output is privacy-minimized JSON with:

- Technical configuration issue descriptions without secret values;
- Pass/fail of schema and cross-module data integrity gates;
- A list of **still-pending manual reviews**;
- A hardcoded `production_release_authorized: false` and `NO_GO_UNTIL_SEPARATE_HUMAN_APPROVAL`.

The CLI exits nonzero for failed technical conditions. It does not deploy, migrate, send emails, inspect academic grade content or modify case records. It is explicitly disabled in local/development/production contexts.

**Important:** The tool can label a configured staging instance *technically acceptable for human review*. It never outputs production authorization, even if every technical check passes. The existence or setup of a real staging host has **not** been verified by this work.

## 3. Human staging signoff form

An authorized release owner should create a private signed approval record using the following fields, **without uploading grade/welfare data or secrets to GitHub**:

| Gate owner | Evidence to record | Review state |
|---|---|---|
| AGILE officers and school authority (where needed) | Authorized project scope, roles, organization charter/bylaws version | **PENDING** |
| Privacy representative / designated organizational officer | Notice, lawful data-handling basis, retention/deletion, response plan | **PENDING** |
| Membership & Student Welfare Head | Applicant, welfare, referral and grade-review due-process workflows | **PENDING** |
| Security reviewer (independent) | CSRF/MFA/session authorization, actual upload-scan operations, penetration test | **PENDING** |
| Operations/backup custodian | Protected keys, MySQL+private-document snapshot, offsite recovery and RTO/RPO drill | **PENDING** |
| Accessibility testers | Mobile/desktop/keyboard and screen-reader UAT across actual roles | **PENDING** |
| Deployment administrator | Approved HTTPS staging domain, isolated test database, ClamAV and monitoring | **PENDING** |
| Authorized communication officer | Isolated Gmail OAuth/send, disabled default and delivery reconciliation | **PENDING** |

No item is checked automatically. The current draft bylaws do not authorize automated academic dismissal or role removal.

## 4. CI verification

- `Quality Gate`: PHP syntax, RFC-6238 TOTP, source-draft preview, encrypted SQL and private-file archive, staging preflight and new technical-versus-governance gate tests.
- `MySQL Integration`: schema idempotence, application-to-member approval, HR capacity, casework, private permissions, email reconciliation, MFA, draft academic review, private-file quarantine and restored backup checks.
- **PR #11 carry-over**: first-request CSRF denial; anonymous, MSW Head, MSW Member, President, Admin, General Member HTTP permissions; acceptance integrity + rollback-only tamper checks.
- **PR #10 carry-over**: two separately assigned reviewers and confidential welfare cases; publisher control, member public verification opt-in/out, token-only Welfare public status; separate acceptance report and short-retention aggregate artifact.
- `tests/release_readiness.php` verifies that missing human signoffs, unsafe config, or invalid DB state cannot self-authorize a release.

Actual HTTPS reverse-proxy behavior, current antivirus signatures, browser/mobile accessibility, institutional bylaw approvals and external Gmail delivery are **NOT** verified by the synthetic CI workflow.

**Decision as of this draft: Production NO-GO.**
