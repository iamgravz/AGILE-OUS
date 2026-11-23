# Technical Audit: AGILE Membership and Student Welfare Management System

Audit date: November 23, 2026. Scope: the whole repository at `C:\laragon\www\agile` (branch `main`, 83 commits), its
MySQL database `agile_ams`, and its documentation. This first pass is inspection only: no code was changed before this
report. Remediation is listed in section K and is done afterward, one item at a time, each retested.

Every result below comes from a check that was run. Checks that were **not** run are listed as such in section F.

---

## A. Executive summary

The system is a custom PHP 8.3 MVC application (no framework) with its own router, middleware, repositories, services
and views. It covers the requested scope: membership applications, student welfare cases, recruitment, eligibility
screening, credentials, content, events, service requests, reports, and role-based access for six roles.

Security controls are real and tested: CSRF on every POST, prepared statements, output escaping, HTML sanitizing,
password hashing, hardened session cookies, a strict Content-Security-Policy and an encrypted welfare store. The
automated suite (414 tests) passes, and `composer audit` reports no known vulnerable dependency.

The main weaknesses are not in security but in **consistency**: one committed file that may hold personal data, a
removed feature (rubric scoring) that still has a settings screen and dead code, documentation that does not yet
describe six modules added on November 23, and two very large service classes.

| Severity | Count | Items |
|---|---|---|
| Critical | 1 | C-1 |
| High | 2 | H-1, H-2 |
| Medium | 6 | M-1 to M-6 |
| Low | 4 | L-1 to L-4 |

Readiness (section K): ready to demonstrate after C-1, H-1 and H-2 are fixed; ready to deploy after the deployment
checklist in `docs/deployment.md` and `php jobs/preflight.php` pass on the host.

---

## B. Repository structure

| Area | Files | Notes |
|---|---|---|
| `app/Controllers` | 43 | One controller per module; thin, call services. |
| `app/Services` | 73 | Business rules, permission checks (`authorize()`), transactions, audit logging. |
| `app/Models` | 43 | Repositories over PDO; `BaseRepository` for common CRUD. |
| `app/Views` | 199 | PHP templates; layouts `public`, `dashboard`, `docs`. |
| `app/Core`, `app/Middleware` | 15 | Router, request/response, session, CSRF, error handler, auth/guest/permission middleware. |
| `config` | 9 | App, routes, menu, permissions, elections, retention, credentials. |
| `database` | 35 migrations, 31 seeds | `schema.sql` is the full schema; migrations bring an existing database up to it. |
| `jobs` | 17 | Scheduled jobs (email queue, reminders, backups, archive, preflight). |
| `tests` | 45 | PHPUnit 11: unit, database (rolled back) and real-HTTP tests. |
| `public` | | The only web root; `assets/css` (10), `assets/js` (15). |
| `docs` | 14 | Context, database, deployment, security review, ISO/IEC 25010 evaluation, test cases. |

Entry point: `public/index.php` sets security headers, starts the session, and dispatches through `config/routes.php`.
The repository root has an `.htaccess` that denies everything, so only `public/` can be served.

Dependencies (`composer.json`): dompdf (PDF reports and certificates), endroid/qr-code, HTMLPurifier (content and
email HTML), PHPMailer (SMTP), phpdotenv, symfony/http-client and nyholm/psr7 (used by the optional AI extractor),
anthropic-ai/sdk (optional eligibility document extraction, off by default with `AI_PROVIDER=none`). Dev: PHPUnit.

Files that do not belong to the application (see C-1): `attendance/` (one spreadsheet), `banner/` (17 MB of
recruitment banners and a Google Form banner page), `zoom-background/` (1 MB of Zoom backgrounds).

---

## C. Code quality findings

**C-1 (Critical, conditional): a spreadsheet that may contain personal data is committed.**
`attendance/Attendance_2026-11-05.xlsx` has been in git since the first commit (`4daf7b8`) and was pushed to GitHub.
Its contents were not opened for this audit; its name suggests an attendance list, which would hold students' names
and possibly other personal data, against the project's own rule that real people's data stays out of the repository.
The `banner/` and `zoom-background/` folders are design files for AGILE's social media, not part of the system.
*Fix:* move all three folders out of the repository (keep the files locally), add them to `.gitignore`. If the
spreadsheet holds personal data and the GitHub repository is public or shared, removing it from history needs a
history rewrite, which is the owner's decision.

**H-1 (High): rubric scoring was removed, but its settings screen and code remain.**
Rubric scoring was replaced by the MSW interviewer's assessment (`AssessmentService`), yet:
- `app/Views/admin/settings.php` (lines 17, 99-100) and `public/assets/js/rubric-total.js` still let the MSW Committee
  Head edit rubric weights that nothing uses; `SettingsService::rubricFrom()` still validates them.
- `app/Services/EvaluationService.php`: only `committeeVacancies()` and `OPEN_STATUSES` are still called; `rubric()`,
  `panel()`, `canEvaluate()`, `submit()` and `queue()` are dead. `EvaluationRepository` is used only by them.
- Permission `evaluation.submit` is checked only inside that dead code.
A screen that saves settings with no effect is misleading in a demonstration. *Fix:* remove the rubric settings and the
dead methods; keep the `evaluations` table (6 historical rows) read-only, or archive it with a migration.

**M-1 (Medium): placeholder routes and an unused view remain.**
`config/routes.php` lines 302-313 still register `PlaceholderController` for `/admin` and, as a fallback, for every
menu item. Every menu item now has a real route, so the fallback serves nothing except `/admin`, which shows a
"not built yet" page to the MSW Committee Head. `app/Views/portal/coming_soon.php` is not referenced anywhere.
*Fix:* point `/admin` to the existing admin pages (Users) or remove it; delete the fallback block, the controller,
`dashboard/placeholder.php` and `portal/coming_soon.php`.

**M-2 (Medium): two service classes are too large.**
`MembershipService.php` is 1,590 lines and `WelfareService.php` 1,077. `MembershipService` mixes the apply wizard,
documents and their versions, staff review, decisions and committee-head checks. A first split exists
(`ApplicationRequirements`, `ApplicationReminderService`). *Fix:* move document handling (add, replace, remove,
versions) into an `ApplicationDocumentService`; leave the rest. A full rewrite is not justified.

**M-3 (Medium): some permission names do not match what they guard.**
The "Run for office" menu item is gated by `welfare.submit` (`config/menu.php` line 38), and the Applications list by
`evaluation.view`. They work, but a reader cannot tell from the name. *Fix:* rename to `candidacy.file` and
`application.view` with a migration that keeps every role's access unchanged, and update `docs/PROJECT_CONTEXT.md` §6.

**M-4 (Medium): views call services or repositories directly.**
The first count said two places; a full search found nine. `app/Views/portal/post.php` loaded the author, related posts
and event registration, and `app/Views/dashboard/_cards.php` loaded unread notifications. Also: the unread count in
`layouts/dashboard.php`, and `FileStorageService::maxUploadMb()` in four upload views. *Fix:* the page data moves to the
controller or `DashboardService`. The unread count (needed by every page, with no single controller) and the upload
limit (one setting) stay in the views as read-only lookups.

**M-5 (Medium): comments narrate change history.**
40 comments carry dates such as "(Nov 23, 2026)" or say what used to be there ("Rubric scoring was removed").
History belongs in git; comments should explain the code as it is. *Fix:* reword those comments to the current
behaviour and drop the dates; keep the reasons.

**L-1 (Low): no static analysis.** No PHPStan or Psalm is installed, so type mistakes are caught only by tests.
*Fix:* add PHPStan as a dev dependency at a modest level and fix what it reports.

---

## D. Security assessment

Verified controls (code inspected and covered by tests in `tests/SecurityReviewTest.php`, `HttpAccessTest.php`,
`AccessMatrixTest.php` and others):

| Control | Where | Result |
|---|---|---|
| CSRF | `app/Core/Router.php` line 135 adds `CsrfMiddleware` to every POST route | Implemented and tested: `tests/CsrfTest.php` posts without a token and with a wrong one (419, nothing saved), and the same form with the token succeeds |
| SQL injection | All queries use PDO prepared statements. Interpolated parts are fixed identifiers, placeholders or integers (for example `ContentPostRepository.php` line 61, whose `$order` comes from fixed choices) | No user input reaches SQL text |
| XSS | Views escape with `e()`. The 36 raw echoes are integers, layout sections already escaped, or HTML cleaned by HTMLPurifier (`HtmlSanitizer`) | No unescaped user input found |
| Access control | `perm:` route middleware plus `authorize()` in services; every protected route is tested for all six roles against the §6 matrix | Implemented and tested |
| Passwords | `password_hash(PASSWORD_DEFAULT)` / `password_verify` (`AuthService.php`), lockout after failed logins | Implemented |
| Sessions | `Session.php`: HttpOnly, SameSite=Lax, Secure on HTTPS, ID regenerated on login | Implemented |
| Headers | `public/index.php`: strict CSP (`default-src 'self'`, no inline script or style), `nosniff`, `frame-ancestors 'none'` | Implemented |
| Errors | `ErrorHandler.php` sets `display_errors=0` and logs | No stack traces to users |
| Uploads | Extension allow-list, MIME check of content, size limit, random stored names outside the web root, `move_uploaded_file`; downloads go through `FileAccessPolicy` | Implemented; tests upload disguised and oversized files |
| Welfare data | AES-256-GCM encryption with a blind index (`EncryptionService`) | Implemented |
| Secrets | `.env` is git-ignored; `.env.example` holds no secrets | OK |
| Dependencies | `composer audit` | No advisories |

Findings:
- **L-2 (Low):** HSTS is not sent. Correct for local HTTP; on the host it should be added at the web server once
  HTTPS works (add to `docs/deployment.md`).
- **L-3 (Low):** `audit_logs.entity_id` and `ai_extractions.source_id` have no foreign key. This is intentional
  (they point to different tables depending on a type column), and is documented here so it is not mistaken for a gap.
- C-1 above is also a privacy finding.

Not tested: penetration testing by a third party, and brute-force behaviour under real network load.

---

## E. Database assessment

- 57 tables, all InnoDB, utf8mb4. `schema.sql` and the live database have the same tables (checked by script).
- Every `*_id`, `*_by` and `assigned_to` column has a foreign key except the two polymorphic columns in L-3.
- Foreign keys use `ON DELETE RESTRICT`, so records are never deleted silently; removal of sample data is done by
  `SampleDataRemover` with an explicit plan.
- Status columns are `ENUM`s matching the transition maps in code (`ApplicationStatus`, `DocumentStatus`), tested in
  `TransitionMapsTest.php`.
- Multi-step changes run in `Database::transaction()`; rows that must not change concurrently are locked
  (`SELECT … FOR UPDATE`), for example event slots and interview results.
- Backups: `jobs/backup_database.php` (gzip, rotation) and `docs/restore.md`.

Findings:
- **H-1 (database part):** `evaluations` (6 rows) and `settings.evaluation.rubric` belong to the removed rubric.
- **L-4 (Low):** `ai_extractions` (0 rows) supports the optional AI extraction, which is off by default. Keep it only
  if AGILE intends to use the feature; otherwise document it as optional (done in `docs/deployment.md`).

---

## F. Functional testing results

Run on November 23, 2026, on PHP 8.3 and MySQL 8.0, against throwaway test databases:

| Check | Command | Result |
|---|---|---|
| Syntax | `php -l` on every tracked PHP file | No errors |
| Unit, database and HTTP tests | `composer test` | 414 tests, 4,893 assertions, all passed, 1 skipped (needs an upcoming sample event when none exists) |
| Dependency vulnerabilities | `composer audit` | None found |
| Static analysis | `composer analyse` (PHPStan 2.3, level 5) | Added after the inspection (L-1). First run: 30 findings; after fixes: no errors |
| Access matrix | `AccessMatrixTest`, `HttpAccessTest` (in the suite) | Every protected route matches §6 for all six roles |
| ISO/IEC 25010 checks | `Iso25010Test` (in the suite) | Passed; report in `docs/iso-25010.md` |
| Inline styles blocked by CSP | `PortalPagesTest` and others | None on the pages tested |

Modules covered by HTTP or database tests include: apply wizard (with the registration-form questions), document
upload and versions, staff review and decisions, interviews and the applicant assessment, Ask for Help (guest and
member), welfare case handling, eligibility, ID and certificate requests, candidacies, event registration and
attendance, notification preferences, service requests, reports and exports.

**Not executed in this audit:** responsive layout testing in real browsers and phones, screen-reader testing,
load testing, and third-party penetration testing.

---

## G. UI/UX assessment

Strengths: one design system (`tokens.css`, `components.css`), labelled form fields with inline errors, empty states
on lists, tables that stack on small screens (`table--stack`), reduced-motion support, and no inline styles.

Findings:
- **M-6 (Medium): decorative effects should be reviewed.** The glass (frosted) panels, the rolling notice and the
  hero animations were added for appearance. They respect reduced motion, but each should be kept only where it
  helps (for example, the rolling notice saves space for several notices); otherwise prefer plain surfaces.
- Terminology is mostly consistent; "Committee Head" vs "Comm Head" and "Ask for Help" vs "welfare concern" should be
  checked page by page during the UI review.
- Responsive behaviour was designed in CSS but not tested on devices in this audit (section F).

---

## H. Documentation consistency

| Document | Status |
|---|---|
| `README.md` | Install, database, run, tests, email and key steps match the code. It describes XAMPP; the developer uses Laragon with the MySQL80 service, which also works. |
| `docs/PROJECT_CONTEXT.md` | **Out of date (H-2):** still describes rubric evaluations as part of recruitment (lines 23, 93, 95, 155, 165, 239). |
| `docs/database.md` | **Out of date (H-2):** does not list the November 23 tables: `interview_questions`, `applicant_assessments`, `event_registrations`, `notification_preferences`, `document_versions`, `service_requests`, `service_request_messages`. |
| `docs/deployment.md` | Matches the code; add HSTS (L-2). |
| `docs/iso-25010.md` | Matches the tests as of its date; add the new modules to its scope. |

**H-2 (High):** an evaluator comparing the documents with the running system would find six modules missing from
the documentation and one removed module still described. *Fix:* update `PROJECT_CONTEXT.md`, `database.md` and the
module list in `README.md`.

---

## I. Modified files

None during the inspection. Changes made afterward, one item at a time, each followed by the full test suite:

| Item | Files | Why |
|---|---|---|
| C-1 | `.gitignore`; `attendance/`, `banner/`, `zoom-background/` untracked (files kept on disk) | Not part of the system. The owner confirmed the sheet holds no personal data, so history was not rewritten. |
| H-1 | Removed `EvaluationService.php`, `EvaluationRepository.php`, `EvaluationsController.php`, `public/assets/js/rubric-total.js`, the rubric part of `admin/settings.php` and `SettingsService.php`, the rubric seed, `DashboardRepository::applicationsAwaitingEvaluationBy()` (no caller), permission `evaluation.submit` (migration `2026_11_23_retire_rubric`), and their CSS. Added `MyCommitteeController` and `RecruitmentService::committeeVacancies()` (moved, unchanged). | The removed feature no longer had a screen that pretends to work. |
| H-1 (rule kept) | `AssessmentService::save()` | BR-25 (an interviewer who declared a conflict cannot score) and "no one assesses their own application" lived only in the removed rubric code. Both now guard the assessment; `AppointmentFlowTest::testAConflictBlocksTheResultAndTheScoreUntilRescheduled` checks it. |
| H-2 | `README.md` (new "What it does"), `docs/PROJECT_CONTEXT.md` (§1 modules, §5 roles, §6 record rules, BR-05, §9 additions and `evaluations` marked history), `docs/database.md` (36 permissions; 11 tables and 10 columns added with reasons) | The documents now describe the system as built. Table and column lists were taken from the live database, not written from memory. The inspection found 11 undocumented tables, not 7 as first reported. |
| M-1 | Removed `PlaceholderController.php`, `dashboard/placeholder.php`, `portal/coming_soon.php` and the fallback block in `config/routes.php`; `/admin` now redirects to Users (`AdminUsersController::home()`). Added `tests/MenuLinksTest.php`. | The fallback hid missing pages. The new test fails if a sidebar link has no page of its own; all 39 have one. |
| 4b | Added `tests/CsrfTest.php` | The CSRF control was implemented but no test proved a forged POST is refused. |
| M-4 | `PortalController::post()`, `portal/post.php`, `DashboardService::forCurrentUser()` (`unread`), `dashboard/_cards.php` | Page data comes from the controller or service; the two read-only lookups above stay, by choice. |
| M-5 | 40 files: comments in `app/`, `config/`, `database/seeds/`, `jobs/`, `public/assets/css/` | Dated and "was removed" comments now describe the code as it is; reasons are kept. Dates that are data (styleguide samples, email preview values, the privacy page date, date-format examples) were left. Two rewritten comments were checked against the code and corrected. |
| M-3 | `config/permissions.php`, `config/menu.php`, `config/routes.php`, `CandidacyService::file()`, `MembershipService`, `RecruitmentService`, `SearchService`, two views, `docs/PROJECT_CONTEXT.md` §6, `docs/database.md`, `AccessMatrixTest`; migration `2026_11_23_permission_names` | `evaluation.view` is renamed `application.view` (same row, same grants). New `candidacy.file` guards Run for office (menu, both routes, and the service), granted to exactly the holders of `welfare.submit`. On the real database a before/after snapshot of every role's and user's permissions showed the same access. |
| M-2 | New `app/Services/ApplicationDocumentService.php` (add, replace with versions, remove with versions, take over first-page files); `MembershipService` delegates to it; `HttpWholeFlowsTest` now also removes a draft document | Document storage lives in one class. `MembershipService`'s public methods, permission checks and staff verification are unchanged, so no controller changed. Honest result: 1,590 → 1,525 lines. The class stays large because it holds the apply wizard and staff decisions; splitting those touches every application path and is left for after the evaluation. Removing a document had no test before; it has one now. |
| L-1 | `composer.json` (dev: phpstan/phpstan; script `analyse`), `phpstan.neon`, `README.md`; fixes in `ApplicantAccessService`, `ElectedOfficersController`, `ElectedOfficerService`, `ApplicationDocumentService`, `MembershipService`, `DashboardService`, `FileAccessPolicy`, `WelfareService`, `InterviewService`, `jobs/preflight.php` | First run found 30 issues. The 11 in `app/` and `jobs/`: no runtime bug; docblocks that did not match the code (6), a list passed to the text validator, an unused injected property, a redundant `??`, and a PHP-version check in `preflight.php` that can never fail because Composer's platform check stops earlier. The session value read by `ApplicantAccessService::current()` is now checked for shape instead of trusted. The 19 in `database/seeds` were false positives (PHPStan treats live rows of literal arrays as impossible, e.g. the `guest` row); seeders are excluded from analysis, with the reason in `phpstan.neon`. No ignore comments or baseline were used. |
| H-1 (tests) | `AppointmentFlowTest`, `AdministrationTest`, `LandingPageTest`, `AccessMatrixTest`, `docs/test-cases.md` (BR-05) | Tests of the removed code were removed (414 → 404); BR-05 now points to the assessment test. |

---

## J. Remaining issues and risks

- Everything in sections C to H until fixed.
- Real email delivery has been tested only through the queue; sending through AGILE's SMTP account needs the App
  Password in the host's `.env` and `php jobs/test_notify.php`.
- Scheduled jobs must be set up on the host (`docs/scheduling.md`); without them, reminders and the email queue do
  not run.
- The encryption key must be backed up separately; without it, welfare records cannot be read.

---

## K. Readiness and remediation order

| Order | Item | Effort |
|---|---|---|
| 1 | C-1: move `attendance/`, `banner/`, `zoom-background/` out of the repository | Small |
| 2 | H-1: remove rubric settings and dead evaluation code | Small to medium |
| 3 | H-2: update the documentation for the new modules | Medium |
| 4 | M-1: remove placeholder routes, controller and views | Small |
| 4b | Add a test that a POST without a CSRF token is refused | Small |
| 5 | M-4, M-5: views without service calls; comments without change history | Small |
| 6 | M-3: rename the two permissions (with a migration) | Medium |
| 7 | M-2: extract `ApplicationDocumentService` | Medium |
| 8 | L-1: add PHPStan | Medium |
| 9 | M-6 and the UI review | Medium |

**Assessment:** functionally complete for its scope and secure in the areas tested. Demonstration-ready after items
1 to 4. Deployment-ready after those plus the host checklist and a passing `php jobs/preflight.php`.
