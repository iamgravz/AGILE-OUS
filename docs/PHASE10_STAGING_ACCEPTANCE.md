# Phase 10 — Synthetic Staging Acceptance & Release Gate

**Development status:** Draft PR #10, based on Phase 9. No real hosting was deployed, no real student records were processed, and no production release has been authorized.

## Purpose and scope

Phase 10 is a **pre-staging** acceptance layer. It validates that the independently implemented Membership/Recruitment, Welfare, CMS, member card/privacy, academic casework, MFA, and backup components continue to work together in a disposable MySQL 8 + PHP 8.2 HTTP test environment. It is **not** the real-host staging acceptance sign-off.

**Fixtures:** Each CI run generates unique `@example.invalid` identities, synthetic case narratives, applications and membership records, solely inside an isolated MySQL service. The temporary JSON fixture is private to the CI runner and deleted with the runner; neither tokens nor case data are uploaded as build artifacts. Email delivery, external AI and automatic academic sanctions remain disabled.

## Repeatable automated acceptance matrix

| ID | Actor | Operation / expectation | Automated scenario |
|---|---|---|---|
| UAT-01 | Unauthenticated | Public landing, vacancies, welfare tracking and published CMS accessible | `tests/http_smoke.sh` and `tests/phase10_http_acceptance.sh` |
| UAT-02 | Applicant | Role-specific application persists with synthetic data and returns reference | HTTP smoke + MySQL workflow tests |
| UAT-03 | President | Can open aggregated dashboard but **cannot** view individual applicants, Welfare cases/notes, academic records, email drafts or HR decisions | Phase 10 HTTP |
| UAT-04 | Technical Admin | Aggregate dashboard + restricted MSW Head identity-review screen; **no** student case or grade access | Phase 10 HTTP |
| UAT-05 | Assigned MSW Member | Can open assigned applications and Welfare cases, but not another member's assignments or the academic casework dashboard | Phase 10 HTTP |
| UAT-06 | Other MSW Member | Cannot read another reviewer’s recruitment/Welfare records | Phase 10 HTTP |
| UAT-07 | Committee Head | Can open HR requisition and CMS draft screens but not Welfare/application/academic case details or the membership directory | Phase 10 HTTP |
| UAT-08 | The Source Code Editor | Can manage own editorial draft and request review, but cannot self-publish or access MSW confidential workflows | Phase 10 HTTP |
| UAT-09 | General Member | Can access own membership card and optional academic summary, but not staff dashboards or other students' records | Phase 10 HTTP |
| UAT-10 | General Member | Public ID verification defaults private; explicit opt-in enables limited verification; opt-out revokes it immediately | Phase 10 HTTP |
| UAT-11 | MSW Head | Full authorized recruitment screening and welfare workflow, with audit and secure assignment | Existing HTTP + MySQL integration |
| UAT-12 | Academic reviewer | Draft-bylaws grade screening is provisional, has a human appeal path and **does not** auto-remove officers | Phase 7 MySQL integration |
| UAT-13 | Authenticated privileged staff | Password-only access blocked; MFA enrollment, challenge, one-use recovery code, CSRF protection | Phase 8 HTTP + MySQL integration |
| UAT-14 | Private document access | Quarantine, scan-error/infected rejection, owner-only access and file-integrity verification | Phase 9 MySQL integration |
| UAT-15 | Backup operator | Wrong key/tamper detected; SQL and private-file restore only to isolated environments | Phase 9 backup suites |
| UAT-16 | Mail administrator | Uncertain Gmail send not blindly retried; manual reconciliation and outbox audit | Phase 5 MySQL integration |
| UAT-17 | Staging reviewer | Migration/state invariants pass; machine-readable acceptance evidence contains no student PII and production gate **stays blocked** | `staging_acceptance_report.php` |
| UAT-18 | Public tracking user | Welfare token reveals **status only**, not reporter identity, private notes or narrative | Phase 10 HTTP |

## Role–access boundaries

| Feature | MSW Head | MSW Member | President | Technical Admin | Committee Head | Source Code Editor | Member |
|---|---|---|---|---|---|---|---|
| Membership applicant case details | Yes | Assigned only | **No** | **No** | No | No | No |
| Individual Welfare cases | Yes | Assigned only | **No** | **No** | No | No | No |
| Academic review + grades | Yes | No | **No** | **No** | No | No | Own limited status only (when linked) |
| Membership approval/verification | Yes | Status work only, no final approval | No | No | No | No | No |
| HR staffing requisition | Yes | No | No | No | Yes (scope to be formally validated) | No | No |
| CMS draft | Yes | No | No | No | Yes | Yes | No |
| CMS publish authorization | Yes | No | No | No | No | No | No |
| Member ID visibility | Own opt-in if linked | Own opt-in if linked | Own opt-in if linked | Not a member role | Own opt-in if linked | Own opt-in if linked | Own opt-in |

**Security note:** Role membership and departmental committee scope require a final approved institutional identity/assignment registry. These tests verify only the current code's stated role boundaries and must not be interpreted as complete organizational policy compliance.

## Automated machine-readable acceptance evidence

The read-only CLI tool:

```bash
# Only in an authorized staging shell:
APP_ENV=staging AGILE_UAT_READ_ONLY=true php scripts/staging_acceptance_report.php

# Within disposable CI only:
CI=true RUN_E2E_TESTS=yes php scripts/staging_acceptance_report.php
```

The report includes:
- Migration completeness and consistency with tracked migration files.
- Draft bylaws are not marked approved or attached to final grade decisions.
- No vacancy overfill and no General Member holding active privileged assignments.
- No supposedly clean attachment missing its recorded integrity digest.
- Approved staff/member identity links are internally consistent.
- No aging mail delivery job stuck in `processing`.
- Aggregate counts for policy, quarantine, mail queue and open academic review, **without** names, contacts, document content, grades, tokens or secrets.

The CI workflow uploads only this **sanitized JSON summary**, with seven-day retention. Fixture JSON and private grade/case records are never artifacts.

**Built-in release gate:** `production_release_authorized=false`; `release_gate=BLOCKED_PENDING_HUMAN_APPROVAL_AND_REAL_STAGING_VALIDATION` even if every CI assertion passes. Release authorization requires separately documented and independently approved governance and real staging evidence.

## Manual UAT and real staging sign-off — NOT completed

A real staging owner must separately record dated, named approvals of all of the following, without publishing private student data in GitHub:

1. An institutionally approved, dedicated **HTTPS staging host** with documented secrets, backups, isolated synthetic database and hardened server configuration.
2. Independent privacy impact assessment, notice, lawful handling of welfare/grades, retention and incident-management procedures.
3. Working malware scanning with maintained signatures, real scans of synthetic PDF/JPEG/PNG samples, quarantine review and error recovery.
4. Authorized MFA enrollment/reset, staff joining/leaving protocols, administrative break-glass oversight and threat-model review.
5. Formal review of the **draft** AGILE Constitution and By-Laws, unresolved General/Regular Member terminology, grading interpretation, committee/publication scope and due process.
6. End-to-end manual responsive/mobile accessibility review (keyboard navigation, forms, screen readers, Philippine timezone, correct login/logout flows).
7. Authorized Gmail OAuth test account, isolated mail sandbox or disabled transport, provider duplication/reconciliation procedure.
8. Consistent MySQL + document backup strategy, offsite key recovery, complete disaster recovery rehearsal with documented RTO/RPO and privacy-safe audit.
9. Independent vulnerability scan and penetration test, access-control negative cases and remediation sign-off.
10. Committee, Executive and Membership Welfare stakeholders accept scope, role permissions and the actual UAT results.

No boxes are checked by this software automatically, even when GitHub Actions is green.

## Issues to resolve before real staging

- Form submission controls and per-endpoint API rate limiting need formal security testing, particularly unauthenticated applications and Welfare status tracking.
- Real committee HR requisition scoping requires a confirmed committee membership registry rather than free-text department claims.
- The President sees an **aggregated Membership dashboard**; further organizational summary views must be separately reviewed for data-minimization.
- External AI grade extraction and Gmail OAuth live delivery remain disabled pending institutional approval.
- Privileged password reset/MFA reset and files' malware scan operational recovery still require written procedures.
- Database and private document archives are distinct; ensure a consistent snapshot and tested restoration.
- Source draft bylaws are **provisional only**. No automated disqualification, appointment denial or demotion may be authorized by CI.

## Developer verification

GitHub Actions `MySQL Integration` includes all preceding tests plus:
```bash
PHASE10_FIXTURE=/path/in/runner/phase10_acceptance.json php tests/seed_phase10_acceptance.php
PHASE10_FIXTURE=/path/in/runner/phase10_acceptance.json bash tests/phase10_http_acceptance.sh
php tests/staging_acceptance_report.php
php scripts/staging_acceptance_report.php
```
These commands are gated to CI with `RUN_E2E_TESTS=yes`; use **synthetic data only** in a disposable database. The Quality Gate and Repository Hygiene workflows remain required.

### Completion criteria for this PR

- [ ] PHP Quality Gate passes on the final commit.
- [ ] MySQL Integration and all Phase 10 HTTP scenarios pass on the final commit.
- [ ] Sanitized acceptance report is generated; all schema invariants pass.
- [ ] No secrets/actual private student data are in PR, logs, artifacts or code.
- [ ] Draft dependencies #1–#9 remain unmerged until independently reviewed.
- [ ] No production deployment or irreversible action is performed.

Once these automated items are verified, this remains a **draft** PR awaiting security/organizational review.
