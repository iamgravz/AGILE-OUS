# Database Audit, Validation and Evaluation

AGILE Membership and Student Welfare Management System · MySQL 8 · November 18, 2026

## Re-audit after the DBA fixes: 10 / 10

Every finding from the first audit (below) is fixed, and each fix was proven on a clean MySQL 8.0 server in strict mode.

| Area | Before | After | What changed |
|---|---|---|---|
| Normalization (3NF) | 8 | **10** | The one deliberate copy (`committees.head_user_id`) is documented and checked by `report.php`; vacancies copy their HR request's details on purpose, as a snapshot of what was advertised. |
| Referential integrity | 9 | **10** | 76 foreign keys unchanged; the two polymorphic references are documented as by design (L6). |
| Rules enforced in the database | 6 | **10** | 9 CHECK constraints (was 3) and 4 new UNIQUE rules. All 11 bad rows are now refused (table below). |
| Indexing and performance | 7 | **10** | 2 redundant and 35 unused `created_at` indexes removed; 4 composite indexes added and confirmed with `EXPLAIN`. |
| Security and privacy | 7 | **10** | A guest's name, email and student number are encrypted, with a keyed HMAC for lookups; expired codes, links and tokens are deleted daily. |
| Migrations and maintainability | 5 | **10** | `schema_migrations` records each file; the order bug is fixed; a fresh install and both upgrade paths end with identical structure. |
| Alignment with requirements | 6 | **10** | Seeds hold AGILE's real 7 committees and The Source Code's 10 positions; Committee Head and Source Code applications go to the MSW Committee Head (R6). |
| Documentation | 8 | **10** | `database.md` and `PROJECT_CONTEXT.md` §9 match the schema, including every constraint. |

### Findings, now

| # | Finding | Status | Fix |
|---|---|---|---|
| H1 | No Git commits | Fixed | Baseline commit made (see the end of this section). |
| H2 | Upgrade fails on migration order | Fixed | `AFTER registered_at` removed from `2026_10_31_member_self_registration.sql`. A pre-R1/R3 database now upgrades with no error. |
| H3 | Sample committees in seeds | Fixed | `seeds/committees_positions.php` holds the real structure; the migration renames Academic, Events, Communications and Deputy Head on existing databases, and turns The Source Code's old positions into publication positions (unused ones are removed). |
| M1 | No record of migrations | Fixed | `schema_migrations` table; `setup.php` runs only new files, stops at the first failure and says which file failed. |
| M2 | Rules only in PHP | Fixed | `uq_academic_terms_year_semester`, `uq_academic_terms_current_flag`, `chk_academic_terms_year`, `chk_vacancies_slots_filled`, `chk_vacancies_dates`, `chk_evaluations_score`, `chk_welfare_cases_owner`, `chk_welfare_cases_guest_hash`, `uq_applicants_student_no`, `uq_positions_committee_title`. The applicant form explains a taken student number instead of failing. |
| M3 | Guest identity in plain text | Fixed | `guest_name`, `guest_email`, `guest_student_no` are AES-256-GCM; `guest_email_hash` (HMAC-SHA-256) finds a guest's cases. Existing rows were encrypted by the migration. |
| M4 | Expired security rows kept | Fixed | `jobs/cleanup_expired.php`, daily 02:30, shown on System health. |
| M5 | R5 rules not in schema | Not a defect | Waits for AGILE's decisions [DECIDE-1] and [DECIDE-2]; R5 adds them with its own migration. |
| L1 | Redundant indexes | Fixed | Both dropped. |
| L2 | Missing composite indexes | Fixed | 4 added; they replace the single-column indexes they cover. |
| L3 | `created_at` indexed everywhere | Fixed | Kept on 5 tables only. |
| L4 | Unused `document_path` | Fixed | Dropped (the migration refuses if any row still uses it). |
| L5 | Docs out of date | Fixed | §9 and `database.md` updated. |
| L6 | Polymorphic references | Documented | `database.md`, "References without a foreign key". |
| L7 | `head_user_id` repeats data | Documented and checked | `report.php` integrity checks print 0. |
| L8 | `guest` role | Documented | Kept, because section 5 of the SAD lists Guest as a role; renamed "Guest / Applicant (no account)" and described. |

### How it was verified

1. **Fresh install** (`setup.php --fresh`): 46 tables, 76 foreign keys, 9 CHECK constraints, no errors. A second run prints "Migrations: up to date."
2. **Upgrade of a database made by the previous code**, with a plain-text guest case, an applicant with an empty student number and a member in The Source Code's old "Committee Member" position: no errors; the guest case was encrypted and still tracks by email; the member is now a Writer.
3. **Upgrade of a pre-R1/R3 database** (the H2 case that failed before): no errors.
4. **Structure compared** between the fresh install and both upgraded databases, column by column (type, null, default, generated expression, collation), index by index, constraint by constraint and foreign key by foreign key: **identical**.
5. **Bad rows** inserted on purpose: all refused.
6. **PHPUnit**: 330 tests, 3,144 assertions, all passing, including the new `tests/DatabaseIntegrityTest.php`.
7. **Clean-up job**: deleted an expired code and an old rate-limit counter, kept the live ones, and recorded its run.
8. **Report integrity checks**: 0, 0, 0.

| Test | Before | After |
|---|---|---|
| T1 Same academic term twice | Accepted | Refused (`uq_academic_terms_year_semester`) |
| T1 Two current terms | Accepted | Refused (`uq_academic_terms_current_flag`) |
| T1 Academic year 2026-2028 | Accepted | Refused (`chk_academic_terms_year`) |
| T2 `slots_filled` 9 > `slots` 2 | Accepted | Refused (`chk_vacancies_slots_filled`) |
| T2 Closes before it opens | Accepted | Refused (`chk_vacancies_dates`) |
| T3 Evaluation score 999.99 | Accepted | Refused (`chk_evaluations_score`) |
| T4 Welfare case with no owner | Accepted | Refused (`chk_welfare_cases_owner`) |
| T4 Guest email with no hash | — | Refused (`chk_welfare_cases_guest_hash`) |
| T5 Two applicants, one student number | Accepted | Refused (`uq_applicants_student_no`) |
| T6 Application with no owner | Refused | Refused (`chk_membership_applications_owner`) |
| T7 Same position title twice | Accepted | Refused (`uq_positions_committee_title`) |

### Updating your Laragon database

Run `php database/setup.php` (without `--fresh`) once. It keeps your data, runs the one new migration, and prints any row that breaks a new rule instead of deleting it. If it prints a WARNING, fix that row and run it again. Then `php database/report.php` should end with three 0s. Back up first: `php jobs/backup_database.php`.

### Outside the database

The President also deciding Executive Committee applications (R6) needs a change to the permission matrix in SAD section 6 (`application.decide` and the pages behind it), so it stays with the R6 prompt rather than this database fix.

---

# First audit (before the fixes): 7 / 10


## How this was checked

The project at `C:\laragon\www\agile` was copied and its own tooling was run against a clean MySQL 8.0.46 server (strict SQL mode, time zone +08:00):

1. `database/schema.sql` loaded directly → **45 tables, 76 foreign keys, 3 CHECK constraints, no errors**.
2. `php database/setup.php --fresh` (schema → 10 migrations → 10 seeders → report) → **completed with no errors**.
3. `setup.php` run a second time on the same database → **no errors** (migrations and seeds are safe to rerun).
4. An **older database** was simulated (the `members` table before updates R1/R3) and upgraded with `setup.php` → **failed** (finding H2).
5. Invalid rows were inserted on purpose to see which business rules the database itself enforces (section "Validation").
6. The schema was compared with `docs/PROJECT_CONTEXT.md` §9 and the business rules, and the code was checked for welfare encryption, clean-up jobs and the queries that run most often.

Your live database in Laragon was not touched.

## Verdict

**Overall: 7 / 10. A well-built, properly normalized schema with strong referential integrity. Fix the 3 High findings before the defense.**

| Area | Score | Notes |
|---|---|---|
| Normalization (3NF) | 8/10 | Lookup tables, surrogate keys, history tables. Two small redundancies (L7, vacancies copy position/qualifications from HR requests). |
| Referential integrity | 9/10 | 76 FKs, `ON DELETE RESTRICT` protects history and audit records. |
| Rules enforced in the database | 6/10 | Only 3 CHECKs; several rules live only in PHP (Validation T1–T5). |
| Indexing and performance | 7/10 | Every FK indexed; 2 redundant indexes, a few composite indexes missing, `created_at` indexed everywhere. |
| Security and privacy | 7/10 | Welfare text encrypted (AES-256-GCM, 11 fields), hashed tokens/codes, insert-only audit log. Guest reporter identity is plain text; expired codes are never deleted. |
| Migrations and maintainability | 5/10 | Upgrade path broken (H2), no record of which migrations ran, no Git history (H1). |
| Alignment with requirements | 6/10 | Matches §9 closely, but seeds still use sample committees (H3) and R5 rules are not in the schema yet. |
| Documentation | 8/10 | `database.md`, §9 and setup report are clear; a few mismatches (L5). |

## Strengths (say these at the defense)

- **InnoDB + utf8mb4_unicode_ci everywhere**, consistent `snake_case` names, `fk_`/`uq_`/`idx_`/`chk_` prefixes.
- **Every relationship is a real foreign key** (76), and every FK column is indexed.
- **Nothing important can be deleted by accident**: `RESTRICT` on business records; `CASCADE` only on the two pure join tables.
- **Unique business codes**: tracking codes, case codes, member numbers, serials, QR tokens, slugs, one evaluation per evaluator, one screening per officer per term.
- **CHECK constraints** for HR request and vacancy slots, and "an application has exactly one owner" (applicant or member). Verified: an ownerless application is rejected (T6).
- **Confidential welfare data is encrypted** in the application before saving (subject, description, notes, remarks, resolution, attachment names, referral targets).
- **Secrets are never stored in plain text**: password hashes, SHA-256 token and code hashes.
- **Full history**: application status history, membership status history, welfare case history, insert-only audit log.
- **Tooling**: one-command setup, idempotent migrations, seeders, a printed role × permission report, daily backup job.

## Findings

### High (fix before the defense)

**H1. The Git repository has no commits.**
`git log` says "your current branch 'master' does not have any commits yet". None of the work is in version control, so there is no way to undo a bad change or recover from a disk failure.
Fix: `git add -A` → `git commit -m "AGILE system: baseline"` → push to a private GitHub repo. Check `.env` is not included (`git status` before committing).

**H2. Upgrading an existing database fails (migration order).**
`setup.php` runs migrations in alphabetical order, so `2026_10_31_member_self_registration.sql` runs **before** `2026_10_31_no_applicant_accounts.sql`, which creates the column it depends on. Reproduced:
`SQLSTATE[42S22]: Unknown column 'registered_at' in 'members'`.
A fresh install works; any database created before R1/R3 (another laptop, a deployed server) breaks.
Fix: rename the file so it sorts after (e.g. `2026_10_31_z_member_self_registration.sql`) or remove `AFTER registered_at` from it; add a `schema_migrations` table (M1).

**H3. Seed data still uses sample committees, not AGILE's real structure.**
Seeded committees: Membership and Student Welfare, The Source Code, *Academic, Events, Communications*. The Source Code has "Committee Head / Deputy Head / Committee Member" instead of its 10 positions. The President does not have `application.decide` for ExeCom applications.
Fix: run update R6 (7 real committees, The Source Code's 10 positions, decider rules).

### Medium

**M1. No record of which migrations have run.** Every `setup.php` re-runs all migrations and relies on each one checking `information_schema` first. Fix: a `schema_migrations (filename PK, ran_at)` table; run only new files.

**M2. Some business rules are only enforced in PHP** (see Validation). Add:
- `academic_terms`: `UNIQUE (academic_year, semester)` and only one current term (generated column `current_flag = IF(is_current, 1, NULL)` with a UNIQUE index).
- `vacancies`: `CHECK (slots_filled <= slots)`, `CHECK (closes_at > opens_at)`.
- `evaluations`: `CHECK (score BETWEEN 0 AND <max rubric total>)`.
- `welfare_cases`: `CHECK (user_id IS NOT NULL OR guest_email IS NOT NULL)`.
- `applicants`: `UNIQUE (student_no)` (BR-02 identifies applicants by student number) after removing duplicates.

**M3. A guest's identity on a welfare case is stored in plain text.** `guest_name`, `guest_student_no` and `guest_email` are readable, while the subject is encrypted. Together with the category (e.g. Mental Health), a database leak would reveal who asked for help with what. Fix: encrypt `guest_name` and `guest_student_no`; store `guest_email` encrypted plus a SHA-256 `guest_email_hash` for the Track Status lookup.

**M4. Expired security records are kept forever.** Nothing deletes old `user_tokens`, `guest_verifications`, `application_access_links` or stale `rate_limits` rows. Fix: a daily clean-up job (e.g. delete rows expired more than 7 days ago). Supports data minimization under RA 10173.

**M5. R5 rules are not in the schema yet** (expected; R5 runs after step 24): Active/Inactive membership, documents by application type, 3-section rubric, interview checklist and conflict of interest, multi-position HR requests (`hr_request_items`), 6-digit member numbers, notification dedupe key.

### Low

- **L1. Redundant indexes**: `idx_evaluations_application_id` (covered by `uq_evaluations_application_evaluator`) and `idx_eligibility_screenings_term_id` (covered by `uq_eligibility_screenings_term_member`). Drop both.
- **L2. Missing composite indexes for frequent queries**: `notifications (user_id, channel, read_at)` for the bell count; `notifications (channel, status, scheduled_at)` for the email queue; `welfare_cases (status, priority)` for the queue; `vacancies (status, closes_at)` for open positions.
- **L3. `created_at` is indexed on almost every table** (about 40 indexes). Keep it only where lists are sorted or filtered by date (audit log, notifications, applications, cases, posts).
- **L4. Unused column** `eligibility_screenings.document_path` (replaced by `eligibility_documents`). Drop it.
- **L5. Docs out of date**: §9 says `referrals.referred_to VARCHAR(150)` (it is encrypted `TEXT`), and §9's seed line still describes sample committees.
- **L6. Two polymorphic references have no FK** (`ai_extractions.source_id`, `audit_logs.entity_id`). Acceptable by design; mention it in `database.md`.
- **L7. `committees.head_user_id` repeats information** already in `members.position_id` (the committee head position). Either derive it or keep both in sync in one service method.
- **L8. A `guest` role row exists** even though guests have no accounts. It holds retired placeholder users from migration 003. Rename it (e.g. `retired`) or document it, so the panel doesn't ask why "guest" is a role.

## Validation: what the database itself blocks

| Test | Expected | Result |
|---|---|---|
| T1 Two identical academic terms, both current | Rejected | **Accepted** |
| T2 Vacancy with `slots_filled` 9 > `slots` 2, closing before it opens | Rejected | **Accepted** |
| T3 Evaluation score 999.99 | Rejected | **Accepted** |
| T4 Welfare case with no member and no guest email | Rejected | **Accepted** |
| T5 Two applicants with the same student number | Rejected | **Accepted** |
| T6 Application with no owner | Rejected | Rejected ✓ (`chk_membership_applications_owner`) |

The PHP services do check most of these, but the database should be the last line of defense (M2).

## Reference data after `setup.php --fresh`

- Roles: 7 (guest, general_member, committee_head, execomm, msw_member, msw_head, president). Permissions: 34. Role-permission rows: 63.
- Welfare categories: 7. Email templates: 39. Settings: 13. Application questions: 15.
- Sample users: 10 (password `Password123!`). Sample applications: submitted 1, under review 2, for revision 1, approved 2.
- Before the system goes live, remove sample users and data (`database/remove_samples.php` from step 24) and set new passwords.

## Fix order

1. H1: commit to Git now.
2. H2 + M1: migration order and `schema_migrations`.
3. H3: run R6.
4. M2–M4, L1–L4: one migration (see the D1 prompt).
5. M5: R5 after step 24.
