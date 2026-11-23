# Alignment report: steps 1-19 and every update, against docs/PROJECT_CONTEXT.md

Audit date: October 31, 2026. Phase 1 only: apart from this report, no code, docs or real data were changed for this audit. It also covers UI G1 and EMAIL E1, which came after the prompt's list. Where this prompt and the master copy differ, the master copy wins.

## Phase 2 result (October 31, 2026)

**Verdict: Ready for step 20.** Rows 1-4 are fixed. One owner task remains from row 1: upload the `mswmember@agile.test` photo again. It does not block step 20. The 15 Low rows and 4 Not yet rows were not changed.

| Row | What changed |
|---|---|
| 1 | Eligibility PDF restored from Downloads. Credentials 3 and 4 rebuilt at their original paths, with the same serial and QR token, still pending approval. No database rows changed. Every referenced file now exists except the photo. |
| 2 | `MembershipService::changeRole()` refuses to end the role of the only active MSW Committee Head, by ineligibility or by appointment. Recorded in BR-10. |
| 3 | `jobs/application_reminders.php` now also sends the pending-over-3-days reminder to MSW staff and the For Revision reminder with a fresh edit link. |
| 4 | `authorize_any()` added to `issueFor`, `reissue`, `changeRole`, `expireRole` and `ensureMember`. On the web a listed permission is required. Only the command line with nobody signed in passes without one. |

| Check | Result after the fixes |
|---|---|
| PHPUnit | OK: 210 tests, 2189 assertions, including 9 new in `tests/RemindersAndGuardsTest.php` |
| Rework suites | HTTP 236 and browser 897, all pass |
| Reminders job, twice on a scratch database | First: 3 pending and 1 revision. Second: 0 and 0. |
| Migrations | None needed. No schema changed. |

The summary and commands below are from Phase 1, before these fixes. One existing test changed: `tests/CredentialApprovalTest.php` called `reissue` signed in as an MSW Member, which row 4 now correctly refuses, so it signs in as the MSW Head for that line.

## Summary (Phase 1)

**Verdict at Phase 1: Not ready for step 20.** There was one High row, and it was lost files, not a code fault. No security, permission or required-flow problem was found in the code. After row 1 is fixed and the Medium rows are fixed or accepted, the project is ready.

| Severity | Rows needing action |
|---|---|
| High | 1 |
| Medium | 3 |
| Low | 15 |

| Status | Rows |
|---|---|
| OK | 90 |
| Partial | 14 |
| Missing | 1 |
| Conflict | 4 |
| Not yet | 4 |
| **Total** | **113** |

## Commands

The real database was only read. `setup --fresh`, PHPUnit and the jobs ran on a separate database, `agile_ams_audit`.

| Command | Result |
|---|---|
| `composer validate` | Valid |
| `composer install` | Nothing to install |
| `php -l` on app, config, database, jobs, public | 307 files, 0 errors |
| `php database/setup.php --fresh` | 44 tables, 76 foreign keys |
| `php database/report.php` | 34 permissions, 63 role-permission rows, 10 seeded users |
| `vendor/bin/phpunit` | OK: 201 tests, 2164 assertions |
| `php jobs/run.php all`, first run | 1 digest queued and 1 email sent, exit 0 |
| `php jobs/run.php all`, second run | 0 sent, exit 0 |

PHPUnit ran after setup and before the jobs. An earlier run after the jobs had 1 failure, in `tests/AccountsAndDecisionsTest.php:126`, because that test expects the day's account digest not to have gone out yet. The rework HTTP suite (236 passed) and browser suite (897 passed) last ran after EMAIL E1, with no code changed since.

## How row 1 happened

The test runners removed any file in `storage` that was not on a list taken when they started. The owner was using the real site during those runs, so real uploads looked like test leftovers.

| File | Created | Test run |
|---|---|---|
| Eligibility PDF, photo | 20:46:25, 20:47:19 | 20:46:20 to 20:48:37 |
| Credentials 3 and 4 | 22:17:17, 22:17:18 | 22:16:11 to 22:17:35 |

The check that reported "uploads 5, before 5" only compared counts, so it did not show this. Test cleanup now moves leftovers to a holding folder, and never moves a file the real database references. It no longer deletes anything.

## Results

"Rework HTTP" and "Rework browser" are the suites run on a scratch database. "Older suites" are the per-step suites from earlier steps.

| # | Area | Requirement | Expected | Found | Status | Severity | Proposed fix |
|---|---|---|---|---|---|---|---|
| 1 | Data (§11, BR-06, BR-19) | §11 files; data loss | Every file the real database references exists on disk | Missing: `eligibility_documents` id 2 (`RegistrationCertificate (4) (1).pdf`), `users` id 6 photo, `digital_credentials` ids 3 and 4. Their creation times fall inside test runs whose cleanup deleted "new" files. | Fixed (the photo needs the owner) | High | Restore the PDF from Downloads (same SHA-256 as the screening's other copy). Owner re-uploads the photo. Ids 3 and 4 get new files when approved, or through Return and Regenerate. The test cleanup is already changed so it no longer deletes. |
| 2 | §5, §6 | BR-10 + §5 "System administrator" | The system always has a usable MSW Committee Head | `app/Services/MembershipService.php:867` ends the role with no check that it is the only MSW Head. On Oct 31 this left no `msw_head` user. Only an MSW Head can approve an appointment, so the app had no way back, and it took a direct database correction. | Fixed | Medium | Owner decision. Recommended: refuse the decision while no other active MSW Head exists, with a clear message telling the President to appoint one first. |
| 3 | §10 | §10 `application_reminders.php` | Pending over 3 days to MSW; For Revision email with a fresh edit link; register reminders | `jobs/application_reminders.php:11` sends only register reminders. `APP_PENDING_REMINDER` is seeded but never used. | Fixed | Medium | Add the two missing reminders, once a day per application, using the existing templates and `newRevisionLink()`. |
| 4 | §2.4, §6 | §2 rule 4 "inside the service method" | Every service method that changes data checks authorize() | Protected only by their callers: `app/Services/CredentialService.php:66` issueFor, `app/Services/CredentialService.php:83` reissue, `app/Services/MembershipService.php:813` changeRole, `app/Services/MembershipService.php:867` expireRole, `app/Services/MembershipService.php:969` ensureMember. No route reaches them directly, and the callers do check. | Fixed | Medium | Make them internal, or add a check that allows both real callers (BR-10 means the President can reach expireRole). |
| 5 | §8 | §8 digital credential approved→revoked | Status matches §8 | `app/Services/CredentialService.php:41` uses `revoked_at`, as the §9 enum has no `revoked`. §8 and §9 read differently. | Conflict | Low | Add a note to §8. |
| 6 | §8 | §8 officer role active→expired | Transition map in the service | `app/Services/MembershipService.php:846` sets `role_status` directly, with no map. It also goes back to active on a new appointment, which §8 does not list. | Partial | Low | Add a small map, or note the appointment case in §8. |
| 7 | §8 | Records outside §8 | No lifecycle required | `app/Services/WelfareService.php:616` referrals, `app/Services/InterviewService.php:157` interview results, `app/Services/AccountApprovalService.php:39` user status have no map. | OK | Low | None. §8 does not list them. |
| 8 | §9 | §9 `membership_status_history.old_role` | Not marked NULL | Live column allows NULL, for the first history row. | Conflict | Low | Mark NULL in §9. |
| 9 | §9 | §9 `welfare_case_history.actor_id` | Not marked NULL | Live column allows NULL, for guest and system events. | Conflict | Low | Mark NULL in §9. |
| 10 | §9 | §9 "created_at/updated_at unless noted" | Both columns | `eligibility_documents` has no `updated_at`. `audit_logs`, `case_notes`, `welfare_case_history` and `settings` match what §9 states. | Partial | Low | Note it in §9. Rows are insert-only. |
| 11 | §10 | §10 template list | Codes match | All 33 exist. 5 more are in use or seeded but not listed: `APP_DOCUMENT_CORRECTION`, `APP_PENDING_REMINDER`, `HR_REQUEST_APPROVED`, `HR_REQUEST_RETURNED`, `WELFARE_FOLLOWUP_REMINDER`. | Partial | Low | Add them to §10. |
| 12 | §11 | §11 "files only through FileController" | One way out | `app/Controllers/MediaController.php` also serves public CMS images through FileStorageService. | Partial | Low | Note the public image case in §11. |
| 13 | §11, E1 | Contact email source | One source | Emails read `org.contact_email` from Settings. Footer, privacy and coming-soon pages read `ORG_CONTACT_EMAIL` from `.env`, by design for error pages. They can differ. | Partial | Low | Use the same real address in both. |
| 14 | §9 settings | `session.idle_minutes` | Setting takes effect | Seeded, but `app/Middleware/AuthMiddleware.php:37` reads `SESSION_IDLE_MINUTES` from `.env`. | Partial | Low | Decide with the Settings page in steps 20-24. |
| 15 | §12 | §12 status names | "Under Review", "For Revision" | Pills show sentence case from `app/helpers.php` `status_label()`, per POLISH P1. Chart labels still show capitals. | Conflict | Low | Change the §12 wording, and make chart labels match. |
| 16 | §12 | §8 credential status label | Every §8 status has a label and tone | `pending_approval` has no entry in `config/statuses.php`, so it shows as neutral with a generated label. | Partial | Low | Add the entry. |
| 17 | §12.1 | This prompt vs §12.1 | Prompt: shadows only on dropdowns, modals and toasts; 3 fonts | §12.1 (UI G1) now allows soft card shadows and lists 2 families in 6 files. The master copy wins, so these are OK. `box-shadow` is on cards, table wraps, dropdowns, modals, toasts, the focus ring, and the robot exception. | OK | Low | None. |
| 18 | §12.1 | Gradient grep | None | `gradient`: 2 hits, both `public/assets/vendor/chartjs/chart.umd.js`. `blur(`: 2 hits, both `vendor/quill/quill.js`. Library code, not styling. `backdrop-filter`, CDN URLs, emoji, "Oops" and marketing phrases: 0. | OK | Low | None. |
| 19 | §12.1, §10 | Owner details | Real organization details | Footer TODOs (address, Facebook), hero photo TODO, seeded signatory and contact email, and `public/assets/img/email-banner.jpg` is not in the project, so emails use the text header. | Partial | Low | Owner supplies these. |
| 20 | §13 | Test coverage | Every TC is checked by something current | TC-15, 16, 21, 23, 24, 26 and 29 are checked only by older step suites, some of which now fail on old wording. The code paths were read and hold. | Partial | Low | Add them to PHPUnit over time. |
| 21 | Docs | docs/database.md | Matches the code | Tables and foreign keys match. It does not mention the E1 settings. | Partial | Low | Add them. |
| 22 | Docs | docs/sad-updates.md | Carries SAD changes | Has the UI note only. BR-03, BR-05, matrix, UC-02, UC-03, BR-20, BR-21, TC-34 and TC-35 from the owner's R4 note are not there. | Partial | Low | Add them when updating the SAD. |
| 23 | §10 | `backup_database.php` | Daily gzip dump | Not built. | Not yet | Low | Step 20-24. |
| 24 | §1, §6 | `/admin/*`, `/emails`, `/email-templates`, `/my-submissions` | Real pages | Placeholder pages. Sidebar items are permission-checked. | Not yet | Low | Step 20-24. |
| 25 | §3 | Gmail API driver | `MAIL_DRIVER=gmail` | Not built. `app/Services/Mail/MailerFactory.php:16` says step 24. | Not yet | Low | Step 24. |
| 26 | §9 | `email_drafts` compose screens | FR-COM-02 | Table exists. No screens. | Not yet | Low | Step 20-24. |
| 27 | §5, §6 | §5 no applicant role | 7 roles | Database: committee_head, execomm, general_member, guest, msw_head, msw_member, president. | OK |  |  |
| 28 | §6 | §6 matrix | Exact match | 34 permissions, 63 rows, 0 differences, parsed from §6 against `role_permissions`. Guest has 0 rows. | OK |  |  |
| 29 | §6 | Routes | Right middleware | `config/routes.php`: every staff route has `perm:` matching §6. Public, own-record and BR-10 routes are the commented exceptions, checked in the service. | OK |  |  |
| 30 | §6 | Own committee | HR requests for own committee only | `app/Services/RecruitmentService.php:494`. | OK |  |  |
| 31 | §6 | Own records | Applications, cases and files are the owner's | `app/Services/MembershipService.php:420`, `app/Services/WelfareService.php:209`, `app/Services/FileAccessPolicy.php:118`. | OK |  |  |
| 32 | BR-01 | BR-01 | Enrolled students only | `app/Services/MembershipService.php:1056` refuses submit without confirmation. | OK |  |  |
| 33 | BR-02 | BR-02 | One active per vacancy, email or student number | `app/Models/MembershipApplicationRepository.php:80`, `:150`; `app/Services/MembershipService.php:372`. | OK |  |  |
| 34 | BR-03 | BR-03 | All required documents verified | `app/Services/MembershipService.php:748`. | OK |  |  |
| 35 | BR-04 | BR-04 | From an approved request; closes itself | `app/Services/RecruitmentService.php:258`, `:405`; `perm:vacancy.manage`. | OK |  |  |
| 36 | BR-05 | BR-05 | One evaluation each; MSW Head approves appointments | `database/schema.sql:466`, `app/Services/EvaluationService.php:122`, `app/Services/AppointmentService.php:166`. | OK |  |  |
| 37 | BR-06 | BR-06 | MSW Head and President only; views logged | `app/Services/WelfareService.php:378`, `:435`. | OK |  |  |
| 38 | BR-07 | BR-07 | Only MSW Head assigns and closes; 24 hours | `app/Services/WelfareService.php:481`, `:515`, `:416`, `:789`. | OK |  |  |
| 39 | BR-08 | BR-08 | Officers only | `app/Models/EligibilityScreeningRepository.php:37`. | OK |  |  |
| 40 | BR-09 | BR-09 | No failed subject and enrolled | `app/Services/EligibilityService.php:58`. | OK |  |  |
| 41 | BR-10 | BR-10 | MSW Head decides; President for the MSW Head | `app/Services/EligibilityService.php:575`, `:400`. See the Medium row on the last MSW Head. | OK |  |  |
| 42 | BR-11 | BR-11 | Suggestions only | `app/Services/AiExtractionService.php:160`, `:191`. | OK |  |  |
| 43 | BR-12 | BR-12 | Public after approval | `app/Services/CmsService.php:168`, `app/Models/ContentPostRepository.php:23`. | OK |  |  |
| 44 | BR-13 | BR-13 | Only roles expire | `members.status` is ENUM('active'); `app/Services/MembershipService.php:846`. | OK |  |  |
| 45 | BR-14 | BR-14 | Remarks required and logged | `app/Services/MembershipService.php:743`, `:787`, `:722`, `:815`; `app/Services/AppointmentService.php:167`; `app/Services/RecruitmentService.php:184`; `app/Services/CmsService.php:169`; `app/Services/WelfareService.php:482`. | OK |  |  |
| 46 | BR-15 | BR-15 | Closed is final; disposition | `app/Services/WelfareService.php:92`, `:718`. | OK |  |  |
| 47 | BR-16 | BR-16 | Others see what §6 allows | Same checks as the §6 rows; `tests/AccessMatrixTest.php`. | OK |  |  |
| 48 | BR-17 | BR-17 | Guests submit and track | `app/Services/WelfareService.php:187`; `app/Controllers/GuestWelfareController.php:246` honors the setting. | OK |  |  |
| 49 | BR-18 | BR-18 | No account on approval; /register | `app/Services/MembershipService.php:1001`; `app/Services/MemberRegistrationService.php:31`, `:49`; `app/Models/MemberRepository.php:33`. | OK |  |  |
| 50 | BR-19 | BR-19 | Pending until approved | `app/Services/CredentialService.php:117`, `app/Services/FileAccessPolicy.php:118`, `app/Models/CredentialRepository.php:44`. | OK |  |  |
| 51 | BR-20 | BR-20 | Decider rules | `app/Services/MembershipService.php:581`, `:596`, `:632`. | OK |  |  |
| 52 | BR-21 | BR-21 | Pending account, then approval | `app/Services/AuthService.php:78`, `:220`; `app/Services/AccountApprovalService.php:35`, `:86`. | OK |  |  |
| 53 | §8 | §8 lifecycles | A map per record | `app/Services/ApplicationStatus.php:12`, `DocumentStatus.php:12`, `NotificationStatus.php:16`, `RecruitmentService.php:26`, `:34`, `WelfareService.php:58`, `CmsService.php:19`, `EligibilityService.php:30`, `AppointmentService.php:24`, `CredentialService.php:41`. | OK |  |  |
| 54 | §8 | No direct status writes | Writes pass the map | Each `status` write in those services has a transition check first, checked by script and by reading. | OK |  |  |
| 55 | §9 | Live vs schema.sql vs migrations | No differences | Real database, fresh setup and `schema.sql` alone: 44 tables, 76 foreign keys, 65 unique keys, 0 differences in columns, types, NULL, defaults, keys or checks. | OK |  |  |
| 56 | §9 | Live vs §9 | Tables and columns | 44 of 44 tables; 292 columns checked for type, NULL, UNIQUE, default and FK. Only the Low rows above differ. Columns named `*_id` without an FK: `ai_extractions.source_id` and `audit_logs.entity_id`, both by design. | OK |  |  |
| 57 | §10 | Jobs | CLI-only, lock, idempotent | All 9 use `jobs/_bootstrap.php` (refuses web) and `job_lock()`. Run twice: the second sent nothing. | OK |  |  |
| 58 | §11 | Prepared statements | Everywhere | `app/Core/Database.php:34`. Interpolated SQL holds only table names, placeholders and whitelisted ORDER BY. | OK |  |  |
| 59 | §11 | e() on output | Every view | Scripted check of every `<?=`: the rest are fixed strings or already escaped. `app/Views/cms/_article.php:41` is sanitized at save (`app/Services/CmsService.php:104`). | OK |  |  |
| 60 | §11 | CSRF | Every POST | `app/Core/Router.php:135` adds it to every POST. No POST form lacks the token. | OK |  |  |
| 61 | §11 | Uploads | Through FileStorageService | Only `app/Services/FileStorageService.php:43` calls `move_uploaded_file`. | OK |  |  |
| 62 | §11 | Welfare encryption | Encrypted | Real case subject is stored as ciphertext. `app/Services/WelfareService.php:972`. | OK |  |  |
| 63 | §11 | Rate limits | Login, register, track, codes, reset | `app/Services/AuthService.php:183`, `:287`; `app/Services/MemberRegistrationService.php:34`; `app/Services/TrackingService.php:29`; `app/Services/GuestVerificationService.php:47`. | OK |  |  |
| 64 | §11 | Sessions and headers | Secure settings | `app/Core/Session.php:14`, `:22`, `:23`, `:54`; `public/index.php:20`; `app/Middleware/AuthMiddleware.php:39`. | OK |  |  |
| 65 | §11 | Audit log | Insert-only | No UPDATE, DELETE or TRUNCATE on `audit_logs` anywhere. | OK |  |  |
| 66 | §12.1 | Fonts | Only IBM Plex | 6 files in `public/assets/fonts`; browser test confirms all load from this site. | OK |  |  |
| 67 | Flows | Public apply, email code, submit, track | Works end to end | Rework HTTP. | OK |  |  |
| 68 | Flows | Return for revision, edit link | Works end to end | Rework HTTP. | OK |  |  |
| 69 | Flows | General application decided by MSW Head | Works end to end | Rework HTTP. | OK |  |  |
| 70 | Flows | Committee application accepted only by that committee head | Works end to end | Rework HTTP, `tests/AccountsAndDecisionsTest.php`. | OK |  |  |
| 71 | Flows | Approval: member record, no account | Works end to end | Rework HTTP. | OK |  |  |
| 72 | Flows | /register, pending account, MSW Head approves, login | Works end to end | Rework HTTP. | OK |  |  |
| 73 | Flows | Credentials pending, MSW Head approves, download | Works end to end | Rework HTTP, `tests/CredentialApprovalTest.php`. | OK |  |  |
| 74 | Flows | Guest welfare, track | Works end to end | Rework HTTP, `tests/WelfareRulesTest.php`. | OK |  |  |
| 75 | Flows | Eligibility screening, role expiry | Works end to end | `tests/EligibilityTest.php`. | OK |  |  |
| 76 | Flows | Dashboards per role | Works end to end | Rework browser, each seeded role at 360 and 1366. | OK |  |  |
| 77 | §13 | TC-01 | Can pass now | Yes. Rework browser. | OK |  |  |
| 78 | §13 | TC-02 | Can pass now | Yes. Rework HTTP, older suites. | OK |  |  |
| 79 | §13 | TC-03 | Can pass now | Yes. Rework HTTP, `app/Services/TrackingService.php:39`. | OK |  |  |
| 80 | §13 | TC-04 | Can pass now | Yes. Rework HTTP. | OK |  |  |
| 81 | §13 | TC-05 | Can pass now | Yes. Rework HTTP, `app/Services/FileStorageService.php:97`. | OK |  |  |
| 82 | §13 | TC-06 | Can pass now | Yes. Rework HTTP. | OK |  |  |
| 83 | §13 | TC-07 | Can pass now | Yes. PHPUnit, Rework HTTP (ID and certificate are pending first, BR-19). | OK |  |  |
| 84 | §13 | TC-08 | Can pass now | Yes. `tests/EligibilityTest.php`. | OK |  |  |
| 85 | §13 | TC-09 | Can pass now | Yes. `tests/RecruitmentTest.php`. | OK |  |  |
| 86 | §13 | TC-10 | Can pass now | Yes. `tests/RecruitmentTest.php`. | OK |  |  |
| 87 | §13 | TC-11 | Can pass now | Yes. `tests/AppointmentFlowTest.php`. | OK |  |  |
| 88 | §13 | TC-12 | Can pass now | Yes. `tests/AppointmentFlowTest.php`. | OK |  |  |
| 89 | §13 | TC-13 | Can pass now | Yes. `tests/WelfareRulesTest.php`. | OK |  |  |
| 90 | §13 | TC-14 | Can pass now | Yes. `tests/WelfareRulesTest.php`. | OK |  |  |
| 91 | §13 | TC-15 | Can pass now | Yes. Older suites, `app/Services/WelfareService.php:378`. | OK |  |  |
| 92 | §13 | TC-16 | Can pass now | Yes. `tests/WelfareCaseTest.php`. | OK |  |  |
| 93 | §13 | TC-17 | Can pass now | Yes. PHPUnit, Rework HTTP. | OK |  |  |
| 94 | §13 | TC-18 | Can pass now | Yes. `tests/WelfareCaseTest.php`. | OK |  |  |
| 95 | §13 | TC-19 | Can pass now | Yes. PHPUnit, Rework HTTP. | OK |  |  |
| 96 | §13 | TC-20 | Can pass now | Yes. `tests/AccessMatrixTest.php`. | OK |  |  |
| 97 | §13 | TC-21 | Can pass now | Yes. Older suites, `app/Core/Router.php:135`. | OK |  |  |
| 98 | §13 | TC-22 | Can pass now | Yes. PHPUnit. | OK |  |  |
| 99 | §13 | TC-23 | Can pass now | Yes. Older suites, `app/Models/ContentPostRepository.php:23`. | OK |  |  |
| 100 | §13 | TC-24 | Can pass now | Yes. Older suites, `app/Services/EmailQueueService.php:89`. | OK |  |  |
| 101 | §13 | TC-25 | Can pass now | Yes. `tests/EligibilityTest.php`. | OK |  |  |
| 102 | §13 | TC-26 | Can pass now | Yes. Older suites, `tests/EligibilityTest.php`. | OK |  |  |
| 103 | §13 | TC-27 | Can pass now | Yes. `tests/EligibilityTest.php`. | OK |  |  |
| 104 | §13 | TC-28 | Can pass now | Yes. `tests/AiExtractionTest.php`. | OK |  |  |
| 105 | §13 | TC-29 | Can pass now | Yes. Older suites, `app/Services/WelfareService.php:946`. | OK |  |  |
| 106 | §13 | TC-30 | Can pass now | Yes. PHPUnit. | OK |  |  |
| 107 | §13 | TC-31 | Can pass now | Yes. Rework HTTP. | OK |  |  |
| 108 | §13 | TC-32 | Can pass now | Yes. Rework HTTP. | OK |  |  |
| 109 | §13 | TC-33 | Can pass now | Yes. `tests/CredentialApprovalTest.php`, Rework HTTP. | OK |  |  |
| 110 | §13 | TC-34 | Can pass now | Yes. Rework HTTP. | OK |  |  |
| 111 | §13 | TC-35 | Can pass now | Yes. Rework HTTP. | OK |  |  |
| 112 | Docs | PROGRESS.md | Steps 1-19 and every update | Steps 1-19, Rework, R1, R3, R2, R4, P1, G1, E1 are all listed. | OK |  |  |
| 113 | Docs | README.md | Matches the code | Install, setup and job commands match. | OK |  |  |

---

# Earlier report (first alignment check, kept for history)

# Alignment report: steps 1-19 and the rework, against docs/PROJECT_CONTEXT.md

Audit date: October 31, 2026. Phase 1 only: no code, docs (other than this file) or real data were changed to make this report.

## Phase 2 result (October 31, 2026)

*Later on Oct 31: UPDATE R2 defined BR-19 and TC-33 and added the credentials approval step, which closes rows 12 and 14. UPDATE R1 and R3 changed BR-18, so rows 3, 11 and 15 below are out of date. The owner must run `php database/setup.php` again. See PROGRESS.*

**Verdict: ready for step 20 once the owner runs `php database/setup.php` (row 3).** Rows 1, 2, 4-10, 13 and 16 are fixed. Row 11 stays as the context file says. Rows 12, 14 and 15 need text only the owner has, and do not block step 20 unless that text adds rules. The Low rows are unchanged. Line numbers in the tables below are from the audit, before these fixes.

| Check | Result after the fixes |
|---|---|
| PHPUnit | OK: 179 tests, 1615 assertions |
| Rework suite | HTTP 157 and browser 688, all pass |
| Steps 8, 14, 15, rerun after the fixes | All pass: Step 8 HTTP 115 and browser 103, Step 14 HTTP 220 and browser 282, Step 15 HTTP and browser 218 each |
| Step 10, rerun after the fixes | HTTP 142 and browser 145, all pass. The 2 unexplained failures came from seeded rows. |
| Steps 7, 9, 11, 12, rerun after the fixes | Run to the end. Remaining failures check old wording, the removed applicant role, modules that were stubs at the time, or the MSW Head approving a committee position (now refused by BR-20). Step 9 also needs `APP_NAME=AGILE`. |
| Steps 13, 16, 17, 18, 19, last run before the fixes | Step 13 and 18 passed. Steps 16, 17 and 19 had only the expected failures listed further down. Not rerun after the fixes, which did not change their modules except through shared services covered by the rework suite and PHPUnit. |
| Steps 5, 6 | Failures check the old `/register` and the old dashboards. The Step 5 HTTP and browser suites stop at the old register flow, so TC-32 is covered by the rework suite instead. |
| Step 3 | Not rerun. It checks the old look, which section 12.1 replaced. The rework browser suite covers the new look. |
| Migrations | 003 and 004 tested on a copy of the real database |

**Incident during Phase 2.** An old Step 7 test runner, rerun without its delete lines being read, removed everything in `storage/uploads`, including the welfare attachment for case `574ZHA8FZ239` and an eligibility upload. Both were restored from the owner's Downloads folder to the same paths and verified against the originals. No database row changed. The delete line is gone from every copy of the runner.

## Summary (Phase 1, as first reported)

**Verdict: Not ready for step 20.** Three High rows must be fixed first. Two of them are bugs from the rework, and the third needs one command from the owner.

| Status | Rows (92 total) | | Severity | Rows |
|---|---|---|---|---|
| OK | 62 | | High | 3 |
| Partial | 11 | | Medium | 13 |
| Missing | 4 | | Low | 11 |
| Conflict | 11 | | | |
| Not yet | 4 | | | |

Rows 1-27 carry a severity. Six of the Low rows are notes on behavior that works. Rows 36, 53, 78, 87 and 89 repeat a finding from rows 1-27 in their own section, so they carry none. Row numbers are the ones to use in "fix 1, 2, 4".

### How the checks ran

`setup.php --fresh` drops the database, so it ran on a throwaway database (`agile_ams_audit`), never on the real `agile_ams`. The real database was only read or copied.

| Command | Result |
|---|---|
| `composer validate` | Valid |
| `composer install` | Nothing to install. `composer.lock` unchanged. Autoload files regenerated. |
| `php -l` on app, config, database, jobs, public | 0 files with errors |
| `setup.php --fresh`, then `report.php` | 44 tables, 72 foreign keys, 7 roles, 34 permissions, 62 grants, 9 seeded users, no applicant role |
| `vendor/bin/phpunit` | OK: 173 tests, 1592 assertions |
| `php jobs/run.php all`, twice | **Could not run: `jobs/run.php` does not exist (row 13).** Each of the 6 existing jobs ran twice instead. The first queue run sent 4 emails and the second sent 0. Notification rows did not grow. The other 5 jobs had nothing due in this data, so this run alone says little about them. |
| Rework suite | HTTP 144 passed, 0 failed. Browser 687 passed, 1 failed: one connection refused on `/privacy` at 1366px, which passed on the run before, so it looks like a test server hiccup. |
| Upgrade test | A copy of the real database, upgraded by `setup.php`, has exactly the same columns as a fresh database. A forced failure while approving someone without an account returned 500 and left no user, no credentials and no files. |

### Earlier suites against the reworked code

They use `nomember@agile.test` in place of the removed applicant user.

| Suite | Result |
|---|---|
| Step 18 | HTTP 71 and browser 31, all pass |
| Step 17 | HTTP 187 passed, 1 failed. Browser 236 pass. The failure expects a user with no My Eligibility menu item, and that user now has the general_member role. Expected. |
| Step 16 | HTTP 233 passed, 1 failed. Browser 288 pass. The failure expects the word "Applicant" on a case. Expected. |
| Step 19 | HTTP 282 passed, 13 failed. Browser 376 pass. Failures are old titles, the removed applicant dashboard, and counts that now include the seeded applicants. Expected. |
| Step 15 | HTTP **stopped at its line 475**, where it expects the old `/register`. No failures before that, but later checks did not run (row 16). Browser 218 pass. |
| Step 14 | 4 failed, then no total printed. The first is an MSW Head approving a committee position, which BR-20 now refuses (403). The next ones depend on it. Later checks did not run (row 16). |
| Step 13 | HTTP 212, browser 267, all pass |
| Step 12, 11 | Browser 122 and 138, all pass. **Their HTTP suites printed no total in this run,** so they count as not verified (row 16). |
| Step 10 | HTTP 130 passed, 12 failed. Browser 145 pass. 10 failures come from seeded rows or the now-public `/apply`. **2 are not root-caused:** "missing document returns to step 5" and "submit redirects to success" landed on step 3. Submitting works in this suite's browser run and in the rework suite (row 16). |
| Step 9 | HTTP 141 passed, 24 failed. 10 are only `APP_NAME=AGILE-OUS`, the rest are old wording or the old apply and tracking setup. |
| Step 8 | HTTP 115, browser 103, all pass |
| Step 6 | HTTP 446 passed, 37 failed. Browser 98 pass. Failures are old dashboard titles and the welcome card. Expected. |
| Step 5 | 7 failed: old register behavior and the removed `/verify-email/resend`. Expected. |
| Steps 3, 7 | Not rerun (row 16). |

### Side effects of the audit

- `storage/uploads/applications/seed/` has 2 small seeded PDFs from scratch seeding. The real setup uses the same files, so they were left.
- `storage/logs/jobs.log` gained lines from older test runners.
- `storage/logs/app.log` was removed by an older test's cleanup. It held only 3 errors from 11:20-11:21, while the login view was being patched.

## Findings

Severity: High = security, permissions, data loss, or a broken required flow. Medium = rule partly enforced or schema mismatch. Low = wording, docs, design polish.

### High and Medium

| # | Area | Requirement | Expected | Found | Status | Severity | Proposed fix |
|---|---|---|---|---|---|---|---|
| 1 | f | §11 "store SHA-256 hash only" | Plain tokens are erased after the email is sent | `app/Services/EmailQueueService.php:18` lists only `ACCOUNT_VERIFY`, `PASSWORD_RESET`, `GUEST_EMAIL_CODE`. After sending, `ACCOUNT_SETUP`, `APPLICATION_LINK` and `APP_FOR_REVISION` rows still hold a usable token in `payload_json` (proven on a scratch database). Anyone who can read the table could set a member's password or edit an application. Comments in the code wrongly say it is erased. | Fixed | High | Replace the old code with `ACCOUNT_SETUP` and `APPLICATION_LINK`, handle `APP_FOR_REVISION` when it carries a token, and clear rows already sent in a migration. Add a test. |
| 2 | f | §11 rate limit on track; BR-18 | `/track` is limited to 5 tries per 15 minutes | `app/Controllers/TrackController.php:56` has no limit check, and `app/Services/TrackingService.php:75` shows the result. With `/track` already returning 429, 12 more guesses returned 200 and a right code and email showed the status. | Fixed | High | Check and count the same limit in `editLink()`. Add a test. |
| 3 | d | §9, §5 "no applicant role" | The live database matches §9 | Real `agile_ams`: no `applicants` or `application_access_links`, `user_id` still NOT NULL, applicant role still there, old enums. The new code needs these, so `/apply`, `/register`, approvals and lists will fail on your site until it is upgraded. Tested on a copy: it upgrades cleanly and keeps your welfare case and 111 audit rows. | Open, owner runs setup | High | The owner runs `php database/setup.php`, without `--fresh`. |
| 4 | b | BR-02 by email / student number | One active general application per person | `app/Services/MembershipService.php:1150` checks a logged-in user's own `user_id` only. An account with the same email and number as an active applicant moved past the membership type step (proven). The applicant side, `:1167`, also ignores applications filed from an account. | Fixed | Medium | Check both owner types by email and student number. |
| 5 | f | §11 "consent checkbox + notice on all forms" | Consent before personal data is kept | `app/Views/membership/apply/start.php` has a notice only. `app/Services/ApplicantAccessService.php:89` saves the applicant after the code, and consent comes only at step 6 (`consent_at`). | Fixed | Medium | Add the consent checkbox to the start form, or save nothing until consent. Recommend the checkbox. |
| 6 | f | §11 "30-min idle timeout" | Applicant sessions end when idle | `app/Services/ApplicantAccessService.php:28` gives a fixed 120 minutes with no idle check, so a shared computer stays open. | Fixed | Medium | Add the 30-minute idle check. Drafts are saved, so they only need a new code or link. |
| 7 | f | §11 "serve only via authorized FileController" | Files go through FileController | `app/Controllers/ApplyController.php:374` serves an applicant's upload itself. It checks ownership and logs, but it is a second path. | Fixed | Medium | Move the check into `FileAccessPolicy::application_document` and let guests reach it through FileController. |
| 8 | c | §8 "submitted→withdrawn (applicant)" | Applicants can withdraw | `app/Services/MembershipService.php:420` works for an account only. Someone without one has no way to withdraw. | Fixed | Medium | Offer it through an emailed link. This needs a new link purpose, so it is your decision. |
| 9 | d | §9 "exactly one of applicant_id / user_id" | The database enforces it | Only the code enforces it. The only CHECK constraints are `chk_hr_requests_slots` and `chk_vacancies_slots`. | Fixed | Medium | Add a CHECK constraint in a migration. |
| 10 | a | BR-21 (silent on this case) | A rejected account leaves a clear state | `app/Services/AccountApprovalService.php:51` disables the login, but the member record and any ID and certificate stay valid, because `members.status` can only be active (BR-13). | Fixed | Medium | Your decision: revoke the credentials on rejection, or show a warning before rejecting. Recommend revoking. |
| 11 | h | Your check: "approval -> member record, no account" | Per your wording, no account on approval | BR-18 and BR-21 in the master copy say approval creates a pending account. `app/Services/AuthService.php:97` does that. | Kept, no change | Medium | Your decision. The context file says it wins over anything else, so I would keep the code unless you say otherwise. |
| 12 | h | Your check: "credentials pending -> MSW Head approves -> download" | Per your wording, an approval step for credentials | Not in the master copy. TC-07 issues them on approval, and `app/Services/MembershipService.php:718` does that. No such step exists. | Open, needs the rule | Medium | Your decision. If you want it, please send the rule and add it to the master copy. |
| 13 | e | Your check: `php jobs/run.php all` | A runner exists | No `jobs/run.php`. §3, §4 and §10 list only individual jobs. | Fixed | Medium | Your decision: add a small runner, or drop it from the check. |
| 14 | b, i | Your check: "BR-01 to BR-21", "TC-01 to TC-35" | Every number is defined | The master copy has **no BR-19 and no TC-33**. The SAD or an earlier version may have them. | Open, needs the text | Medium | Please send the text of both, or confirm they do not exist. |
| 15 | j | Your check: "every R/P update" | PROGRESS names R1/R3, R2, R4, P1 | PROGRESS has one combined rework entry, and I never received prompts with those labels. Parts of your note match, others may not. | Open, needs the prompts | Medium | Send the R and P prompts, so I can check each one against the code. |
| 16 | h, i | §13 | Each flow has current tests | The Step 15 and Step 14 HTTP suites stop early, and the Step 12 and 11 HTTP suites printed no total. No test covers guest cases joining an account on approval. Two Step 10 failures are not root-caused. The Step 3 and 7 suites were not rerun. | Fixed | Medium | Update those suites for the new model and add a test for guest case linking. |

### Low

| # | Area | Requirement | Found | Status | Severity | Proposed fix |
|---|---|---|---|---|---|---|
| 17 | j | Docs match the code | `docs/database.md` says 41 tables and 68 keys, against 44 and 72, and lacks the new tables and the `applicant` removal | Conflict | Low | Update it |
| 18 | j | Docs match the code | README does not mention `/apply`, `/register`, account approval or the upgrade step | Partial | Low | Update it |
| 19 | d | §5 role name | An upgraded database keeps role name "Guest". A fresh one says "Guest / Applicant". | Partial | Low | Set it in the migration |
| 20 | f | §11 "60-min expiry" | The ACCOUNT_SETUP link lasts 72 hours (`app/Services/AuthService.php:28`) because a first-time email may wait. Not stated in the master copy. | Conflict | Low | Add it to §11 or shorten it |
| 21 | a | §2 rule 4 "RBAC inside the service" | `MembershipService::changeRole`, `ensureMember`, `AuthService::createAccountForApplicant` and `WelfareService::linkGuestCases` have no check of their own. Their callers check, and the job runs with nobody logged in. | OK | Low | Note only |
| 22 | a | §6 | A deciding committee head cannot open the documents (`FileAccessPolicy` needs `document.verify`). They see which are missing. | OK | Low | Your decision |
| 23 | h | Step 19 dashboards | `app/Models/DashboardRepository.php:260` misses interviews of someone who applied without an account and is now a member | Partial | Low | Use the account linked to the applicant |
| 24 | g | §12.1 "only the 3 fonts" | Chart.js labels use its default font (`public/assets/js/dashboards.js`). Emails use Georgia and Arial. PDFs use DejaVu, which the PDF renderer needs. | OK | Low | Set the chart font. Leave emails and PDFs. |
| 25 | g | §12.1 no gradients or shadows | The login page has a dotted `radial-gradient` and robot shadows (`login-robot.css:5,40,94,211,223,291`). The robot is the owner's exception. The dots behind the page are arguably outside it. | OK | Low | Remove the page dots |
| 26 | f | §4 folder list | No `RateLimitMiddleware`. Limits live in services. `tests/docs/test-cases.md` does not exist. | OK | Low | Update §4, or add them in a later step |
| 27 | j | Your SAD note | BR-03, BR-05, the matrix, UC-02, UC-03, BR-20, BR-21, TC-34 and TC-35 are updated in the master copy. The SAD document itself was not edited. | OK | Low | The owner updates the SAD |

### Permissions and routes (a)

| # | Requirement | Found | Status |
|---|---|---|---|
| 28 | §5 roles | 7 roles, no applicant, on a fresh database. See row 3 for the real one. | OK |
| 29 | §6 matrix | `report.php` output matches §6 row by row. `tests/AccessMatrixTest.php` reads §6 and compares every code for every role. | OK |
| 30 | Routes | Every route is one of: `perm:`, the auth group, the guest group, or public by §6. `/apply/*` and approve/reject have checks in `MembershipService`. | OK |
| 31 | Service writes | Scripted scan of public service methods. The only ones without a check are token-based, own-record, jobs, or row 21. | OK |
| 32 | Own committee | `app/Services/RecruitmentService.php:494,504` | OK |
| 33 | Own records | `app/Services/MembershipService.php` `findOwn`, `isOwner` | OK |
| 34 | Decider per BR-20 | `app/Services/MembershipService.php:574`, tested by TC-34 | OK |

### Business rules (b)

| # | Rule | Enforced at | Status |
|---|---|---|---|
| 35 | BR-01 | `MembershipService.php:975` (declaration, then COR verified by staff) | OK |
| 36 | BR-02 | See row 4 | Fixed (row 4) |
| 37 | BR-03 | `MembershipService.php:696` | OK |
| 38 | BR-04 | `RecruitmentService.php:238,405` | OK |
| 39 | BR-05 | `EvaluationService.php:107,122`, UNIQUE in the schema, `AppointmentService.php:166,212` | OK |
| 40 | BR-06 | `WelfareService.php:367,415,267,328` | OK |
| 41 | BR-07 | `WelfareService.php:470,506,655,405` | OK |
| 42 | BR-08 | `EligibilityScreeningRepository.php:28` | OK |
| 43 | BR-09 | `EligibilityService.php:346` | OK |
| 44 | BR-10 | `EligibilityService.php:372,400,575` | OK |
| 45 | BR-11 | `AiExtractionService.php:113,199` | OK |
| 46 | BR-12 | `CmsService.php:168`, `ContentPostRepository.php:31` | OK |
| 47 | BR-13 | `members.status` ENUM('active'), `MembershipService.php:792` | OK |
| 48 | BR-14 | `MembershipService.php:657,670`, `AppointmentService.php:100` | OK |
| 49 | BR-15 | `WelfareService.php:63,707` | OK |
| 50 | BR-16 | RBAC plus `AccessMatrixTest` | OK |
| 51 | BR-17 | `WelfareService.php:187`, `GuestWelfareController` | OK |
| 52 | BR-18 | `ApplicantAccessService.php:80`, `AuthService.php:51,97` | OK |
| 53 | BR-19 | Not in the master copy | Missing (row 14) |
| 54 | BR-20 | `MembershipService.php:574`, `:588` for the email | OK |
| 55 | BR-21 | `AccountApprovalService.php:29,51`, `AuthService.php:220`, `AuthController.php:22` | OK |

### Lifecycles (c)

| # | Lifecycle | Map and check | Status |
|---|---|---|---|
| 56 | Application | `ApplicationStatus.php:12`, written only at `MembershipService.php:1192` | OK |
| 57 | Document | `DocumentStatus.php:12` | OK |
| 58 | HR request | `RecruitmentService.php:26,540` | OK |
| 59 | Vacancy | `RecruitmentService.php:34,547,560` | OK |
| 60 | Welfare | `WelfareService.php:58,96` | OK |
| 61 | Content | `CmsService.php:19,227` | OK |
| 62 | Eligibility | `EligibilityService.php:30,622` | OK |
| 63 | Notification | `NotificationStatus.php:16`, `EmailQueueService.php:74` | OK |
| 64 | Appointment | `AppointmentService.php:24,171,217` | OK |

`users.status` and `referrals.status` are written directly, but §8 lists no lifecycle for either.

### Database (d)

| # | Check | Found | Status |
|---|---|---|---|
| 65 | Fresh database against §9 | 44 of 44 tables. A script found no differences in column names, ENUM values, VARCHAR and CHAR sizes, TEXT and JSON types, or stated NULL and UNIQUE markers. Individual foreign keys were not compared to §9. | OK |
| 66 | schema.sql plus migrations against fresh | The upgraded copy of the real database has identical columns | OK |

### Notifications and jobs (e)

| # | Check | Found | Status |
|---|---|---|---|
| 67 | §10 template codes | All are seeded. Extras: `WELFARE_FOLLOWUP_REMINDER`, `APP_PENDING_REMINDER`, `APP_DOCUMENT_CORRECTION`, `HR_REQUEST_APPROVED`, `HR_REQUEST_RETURNED`. | OK |
| 68 | Jobs built so far | CLI-only guard and lock in `jobs/_bootstrap.php`, used by all 6. Not reachable from `public/`. | OK |
| 69 | `application_reminders.php` | Not built. `APP_PENDING_REMINDER` is seeded but unused. | Not yet (step 20-24; the master copy does not say which) |
| 70 | `backup_database.php` | Not built | Not yet (step 20-24) |
| 71 | Gmail driver, `google/apiclient` | Not built. `MailerFactory.php:16` says step 24. | Not yet (step 24) |
| 72 | `/admin/*`, `/emails`, `/email-templates`, `/my-submissions` | Placeholders | Not yet (step 20-24) |

### Security (f)

| # | Check | Found | Status |
|---|---|---|---|
| 73 | Prepared statements | `Database.php:34` turns off emulated prepares. Variables in SQL are fixed table names, placeholders or whitelisted columns. | OK |
| 74 | `e()` on output | 263 unescaped echoes reviewed by script, sampled by hand: fixed strings such as `' selected'` and class names. **A seeded, not each one.** | OK |
| 75 | CSRF | Applied to every POST in `Router.php:135`. No form lacks the field. | OK |
| 76 | Uploads | `move_uploaded_file` only in `FileStorageService.php:43` | OK |
| 77 | Welfare encryption | Covered by `WelfareCaseTest` and the Step 15 and 16 suites | OK |
| 78 | Rate limits | Login, reset, verify, welfare, and email codes (also covers `/register` and `/apply`). Row 2 closed the gap. | Fixed (row 2) |
| 79 | Sessions and headers | `Session.php:18-23`, `AuthMiddleware.php:37`, `public/index.php:20-23` | OK |

### Design (g)

| # | Check | Found | Status |
|---|---|---|---|
| 80 | Grep | No gradient outside the login page, no `backdrop-filter`, no emoji, no CDN or external font URL, no "Oops" or listed marketing phrases | OK |
| 81 | Shadows | Only modal, drawer, dropdown, toast. Table stacking uses a variable set to none. | OK |
| 82 | Fonts | All 5 files load. The browser confirms body, heading and code fonts on the screens checked. | OK |

### Flows (h)

| # | Flow | Evidence | Status |
|---|---|---|---|
| 83 | Public apply, email code, submit, track | Rework suite | OK |
| 84 | Return for revision, edit link | Rework suite | OK |
| 85 | General application decided by MSW Head | Rework suite | OK |
| 86 | Committee application accepted only by its head | Rework suite, `AccountsAndDecisionsTest` | OK |
| 87 | Approval, member record, no account | See row 11 | Conflict (row 11) |
| 88 | `/register`, pending, MSW Head approves, login | Rework suite | OK |
| 89 | Credentials pending, MSW Head approves, download | See row 12 | Conflict (row 12) |
| 90 | Guest welfare, track | Step 15 suite up to its stop, browser 218 pass | OK |
| 91 | Eligibility, role expiry | Step 17 suite, `EligibilityTest` | OK |
| 92 | Dashboards per role | Step 19 suite, rework browser run | OK |

### Test cases (i)

"Can pass now" is based on the suites above, on a migrated database.

| TC | Can pass now | TC | Can pass now |
|---|---|---|---|
| TC-01 | Yes | TC-19 | Yes |
| TC-02 | Yes | TC-20 | Yes |
| TC-03 | Yes | TC-21 | Yes |
| TC-04 | Yes | TC-22 | Yes |
| TC-05 | Yes | TC-23 | Yes |
| TC-06 | Yes | TC-24 | Yes |
| TC-07 | Yes, with the account pending under BR-21 | TC-25 | Yes |
| TC-08 | Yes | TC-26 | Yes |
| TC-09 | Yes | TC-27 | Yes |
| TC-10 | Yes | TC-28 | Yes |
| TC-11 | Yes | TC-29 | Yes |
| TC-12 | Yes | TC-30 | Yes |
| TC-13 | Yes | TC-31 | Yes |
| TC-14 | Yes | TC-32 | Yes |
| TC-15 | Yes | TC-33 | **Not defined (row 14)** |
| TC-16 | Yes | TC-34 | Yes |
| TC-17 | Yes | TC-35 | Yes |
| TC-18 | Yes | | |

### Limits of this audit

The rows above come from scripted scans, tests and reading the code, not a line-by-line review of every file. "OK" means no problem was found by those checks. All browser runs were in headless Chrome only.
