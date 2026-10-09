# Phase 7 — Academic Dashboard, Provisional Grade Preview and Correction/Appeal Workflow

**Status:** Active development on an isolated PR branch; no public deployment.  
**Reference:** Original 2026-09-24 AGILE OUS Constitution and By-Laws **draft**, especially Article III and Article VI §2.  
**Policy version:** `DRAFT-2026-09-24-ARTICLE-VI-2` (not ratified, stored with `is_approved = false`).

## Intended operating model

The AGILE OUS system checks covered appointees each semester: Executive Officers, Committee Heads, Deputy Heads, Committee Members and The Source Code. General Members are **excluded** from routine semestral grade checks.

**Important legal/policy distinction:** The source draft's Article VI §2 sets an officer-candidacy requirement of no 5.0/F, W or D *during the entire stay in the institution*. Checking additional appointed/publishing positions each semester is a separately proposed AGILE operating rule. Checking each semester does not shorten the historical lookback. Full academic policy remains unratified until proper organizational and university approval.

The system distinguishes three separate states:

| Layer | Data and authority | Effect |
|---|---|---|
| **Provisional preview** | `DraftBylawsPolicy::preview()`, saved to restricted `academic_provisional_previews` | Displays preliminary concerns and missing information. No official status change or role removal |
| **Actual authorized academic verification** | Existing `Academic::screen()` and `Academic::verify()`; requires an explicitly approved policy | Recorded qualified human review only |
| **Academic role transition** | Existing `Academic::transitionToGeneral()`; controlled by default-off governance flag and authorized process | Role is changed only after separate documented approval; never triggered by preview or appeal closure |

## Implemented web routes

### MSW Head only

- `GET /staff/academic/casework`: scoped semester counts (pending checks, human-verified concerns, review requests) and recent appeal inbox.
- `GET /staff/academic/preview?id=<verification_id>`: form to enter verified year level, current enrollment/full load, course/grade pairs, and whether historical records are complete. Role category and executive position are **server-derived from the member's active assignment**, never trusted from a web form.
- `POST /staff/academic/preview`: persists confidential, append-only preview entry with a draft label, flags and reviewer. Does **not** update the official verification, student role, or `eligibility_policies.is_approved`.
- `GET /staff/academic/appeals`: confidential correction and appeal inbox, with verified student and term context.
- `POST /staff/academic/appeals/update`: move a request from `submitted` to `in_review`, `resolved` or `rejected`, or from `in_review` to a final state. Stores every event and reviewer response.

### Linked members only

- `GET /member/academic`: own semester check summary and case request history. Grade data, other people's records, and the MSW Head-only inbox are not shown.
- `POST /member/academic/request`: request a correction, appeal or additional-information review using an authenticated member account explicitly linked to `members.user_id`.
- Only one open request per member/verification at a time. New requests become available after previous requests have a terminal state. Request notes and responses remain confidential.

### Authentication restrictions

President, unrelated committee members, other students, and technical administrators cannot access academic case details or create previews. Staff-member identity linking requires the separate reviewed `MemberIdentity` flow before they can view their own member records. No public reference-number lookup for academic grades.

## Data model

Migration: `database/migrations/011_academic_casework.sql`.

- `academic_provisional_previews`: append-only policy-versioned preview inputs/results and restricted reviewer, linked to official verification ID.
- `academic_review_requests`: member-initiated correction or appeal, owner, type, current status and review response.
- `academic_review_events`: append-only history of submission and every reviewer transition, including actor/time/note.

Sensitive academic grade records are stored only in restricted database tables and controlled upload storage, **not Git or public status endpoints**. Backups and host encryption/access controls still need production review.

## Verifiable acceptance criteria

Automated MySQL tests run with synthetic records and confirm:

1. Executive officer gets a term check; General Member does not.
2. Draft preview uses the actual assigned position (President) even if request payload tries to substitute `General Member`.
3. Grade `5.0`, President year below 3, or incomplete institutional record produces explicit human-review flags.
4. Running a preview never sets `academic_verifications.policy_id`, `automated_flag` or `verified_result`; no role is removed.
5. Only MSW Head can preview and handle a confidential appeal.
6. Only a member with a linked matching account can submit or view their own requests.
7. Duplicate pending requests are refused. Submitted/in-review/resolved event history remains append-only.
8. Finalizing a correction request does not alter grades, official eligibility or member role.
9. President cannot access detailed academic cases or the restricted dashboard.

Run locally against a **disposable database only**, with proper flags:
```bash
php scripts/migrate.php
CI=true RUN_E2E_TESTS=yes php tests/academic_casework_integration.php
```

## Not yet launch-ready

- Identity verification and legitimate educational document submission for real students need PUP/organization authorization and an approved privacy notice.
- Formal correction/appeal deadlines, second-reviewer requirements, decision appeal procedure and policy scope must be defined by the organization.
- MFA and the complete private-file backup/recovery system remain launch blockers.
- Current correction form supports secure text submissions; authenticated student-upload handling requires additional explicit access controls and retention policy.
- Uploaded document AI extraction requires separate privacy authorization; it remains disabled by default.
- Official final grade outcomes and adverse role changes cannot be based on the unratified draft alone.
