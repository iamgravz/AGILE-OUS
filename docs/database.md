# Database Reference

The database has 46 tables (45 for the system and `schema_migrations`), 76 foreign keys and 9 CHECK constraints, stored in MySQL 8 with InnoDB and `utf8mb4_unicode_ci`. The tables are in third normal form with surrogate primary keys. The definitions are in [database/schema.sql](../database/schema.sql).

**Foreign keys:** deleting a parent record is blocked (`RESTRICT`) so that business records and their history cannot disappear. The two exceptions are the pure join tables `role_permissions` and `user_permissions`, which use `CASCADE`.

**Encrypted columns:** `welfare_cases.subject`, `welfare_cases.description`, `welfare_cases.closing_remarks`, `case_notes.note`, `referrals.referred_to`, `welfare_attachments.original_name`, `welfare_cases.guest_name`, `welfare_cases.guest_email`, `welfare_cases.guest_student_no`, `referrals.remarks`, `case_followups.remarks`, `case_resolutions.actions_taken` and `case_resolutions.summary` hold AES-256-GCM ciphertext. Welfare attachment files are encrypted the same way before they are stored. A guest's email is also stored as `guest_email_hash`, an HMAC-SHA-256 keyed from `ENCRYPTION_KEY` (`EncryptionService::blindIndex()`), so Track Status and account linking can find a guest's cases without decrypting every row, and a leaked database cannot be matched against a list of emails.

**Rules the database enforces itself** (the PHP services check them too, but MySQL is the last line of defense; `tests/DatabaseIntegrityTest.php` proves each one):

| Rule | Constraint |
|---|---|
| An application has exactly one owner, an applicant or a member | `chk_membership_applications_owner` |
| A welfare case belongs to a member or a guest email | `chk_welfare_cases_owner` |
| A guest email always has its lookup hash | `chk_welfare_cases_guest_hash` |
| HR requests and vacancies have at least one slot | `chk_hr_requests_slots`, `chk_vacancies_slots` |
| A vacancy never has more filled than slots, and closes after it opens | `chk_vacancies_slots_filled`, `chk_vacancies_dates` |
| An evaluation score is between 0 and 100 | `chk_evaluations_score` |
| An academic year looks like 2026-2027 | `chk_academic_terms_year` |
| A term is entered once, and only one term is current | `uq_academic_terms_year_semester`, `uq_academic_terms_current_flag` (on the generated column `current_flag`, which is NULL for every term that is not current) |
| One applicant per PUP student number (BR-02) | `uq_applicants_student_no` (an empty number is saved as NULL) |
| A position title appears once per committee | `uq_positions_committee_title` (on the generated `committee_key`, 0 for executive posts) |

**Indexes:** every foreign key is indexed. Composite indexes serve the queries that run most: `notifications (user_id, channel, read_at)` for the bell count, `notifications (channel, status, scheduled_at)` for the email queue, `welfare_cases (status, priority)` for the case queue, and `vacancies (status, closes_at)` for open positions. `created_at` is indexed only where lists are sorted or filtered by date: `audit_logs`, `notifications`, `membership_applications`, `welfare_cases` and `content_posts`.

**References without a foreign key (by design):** `audit_logs.entity_id` and `ai_extractions.source_id` point at different tables depending on `entity_type` / `source_type`, so no single foreign key can describe them. The audit log must also outlive the rows it describes. The application always writes them together with their type.

**Deliberate copy:** `committees.head_user_id` repeats who holds the committee's Committee Head position, so the decision rules (BR-20) need one lookup. `MembershipService::changeRole()` updates both, and `database/report.php` prints a check that should always be 0.

**Migrations:** `database/setup.php` runs `schema.sql`, then each file in `database/migrations/` that is not yet in `schema_migrations`, in name order, and records it. Every file also checks `information_schema` before changing anything, so a database from before `schema_migrations` existed simply runs them all once. A fresh install and an upgraded database end with identical structure (checked column by column, index by index and constraint by constraint).

**Data minimization:** `jobs/cleanup_expired.php` runs daily and deletes one-time codes, edit links and account tokens 7 days after they expire or are used, and rate-limit counters after a day (RA 10173). Only their hashes were ever stored.

## Tables

### Access control and administration

| Table | Purpose |
|---|---|
| `schema_migrations` | Which migration files have run on this database |
| `roles` | The 7 system roles, including `guest` for the public (no account, no permissions) |
| `permissions` | The 34 permission codes |
| `role_permissions` | Which permissions each role has |
| `users` | Login accounts and profile basics |
| `user_permissions` | Extra permissions granted to one user |
| `user_tokens` | Hashed email verification and password reset tokens |
| `rate_limits` | Attempt counters for login, tracking and guest forms |
| `committees` | AGILE's 7 committees and The Source Code, and their heads |
| `positions` | Executive Committee, committee and The Source Code positions |
| `audit_logs` | Insert-only record of sensitive actions |
| `settings` | Configurable system values |

### Membership and recruitment

| Table | Purpose |
|---|---|
| `members` | Member records. Membership is always Active; only officer roles expire |
| `membership_status_history` | History of role changes |
| `digital_credentials` | Digital IDs and certificates with QR tokens |
| `hr_requests` | Committee requests to fill positions |
| `vacancies` | Approved openings created from HR requests |
| `membership_applications` | Applications, with tracking codes and decisions |
| `application_status_history` | Status timeline of each application |
| `application_questions` | Common and role-specific questions |
| `application_answers` | Applicants' answers |
| `application_documents` | Uploaded documents and their verification results |
| `ai_extractions` | AI-suggested values awaiting human confirmation |
| `evaluations` | Rubric scores, one per evaluator per application |
| `interviews` | Interview schedules and results |
| `appointments` | Appointment records awaiting or having MSW Head approval |

### Welfare

| Table | Purpose |
|---|---|
| `welfare_categories` | The 7 case categories, with fixed ids 1 to 7 |
| `guest_verifications` | One-time email codes for guest submissions |
| `welfare_cases` | Confidential cases from members and guests. A guest's name, email and student number are encrypted |
| `case_notes` | Encrypted case notes |
| `referrals` | Referrals to authority officers or offices |
| `case_followups` | Scheduled follow-ups |
| `case_resolutions` | Encrypted resolution details, one per case |
| `welfare_case_history` | Timeline of every case event, including views |
| `welfare_attachments` | Case attachments and their disposition after closure |

### Content, communications and eligibility

| Table | Purpose |
|---|---|
| `content_posts` | News, events, publications and announcements |
| `email_templates` | Branded email templates |
| `email_drafts` | Emails composed for selected groups |
| `notifications` | Queued emails and in-app notifications |
| `academic_terms` | Semesters and screening deadlines |
| `eligibility_screenings` | Screening record per officer per term (its unused `document_path` was dropped) |
| `eligibility_documents` | Files an officer submitted for a screening |
| `eligibility_reviews` | MSW Head decision on a flagged screening |

## Additions to the SAD data dictionary

33 of these items are marked *(addition)* in section 9 of [PROJECT_CONTEXT.md](PROJECT_CONTEXT.md).

### New tables

| Table | Reason |
|---|---|
| `user_tokens` | The set-password link for officer accounts and password reset need single-use, expiring tokens stored only as hashes. |
| `rate_limits` | Login, tracking and guest forms need attempt counters that persist between requests. |
| `application_status_history` | FR-MEM-13 requires a status timeline that applicants can view. |
| `guest_verifications` | BR-17 and BR-18 require a one-time email code before a welfare case, an application, or registering at `/register`. |
| `email_drafts` | FR-COM-02 requires saved email drafts addressed to selected groups. |
| `applicants` | BR-18: people apply with no account, so their details live here. One row per email. |
| `application_access_links` | BR-18: emailed links to continue a draft, revise or withdraw. Only hashes are stored. |

### New columns

| Column | Reason |
|---|---|
| `membership_applications.applicant_id` | BR-18: who applied without an account. Exactly one of this and `user_id` is set, enforced by a CHECK constraint. |
| `users.approved_by`, `approved_at`, `rejection_remarks` | BR-21: who approved a new account and when, or why it was rejected. |
| `members.applicant_id` | BR-18: an approved applicant becomes a member before having an account. |
| `members.registered_at` | BR-18: when they registered at `/register`. `members.user_id` stays NULL until then. |
| `members.registration_reminders` | How many reminders to register were sent, so the daily job cannot send one twice. |
| `membership_applications.status` value `waitlisted` | AGILE's Waitlisted decision: qualified but no slot yet; the decider can approve or reject later. |
| `membership_applications.wizard_step` | Lets an applicant leave the wizard and resume at the same step, even after logging out. |
| `membership_applications.enrollment_confirmed_at` | Records when the applicant confirmed current enrollment (BR-01). |
| `digital_credentials.status`, `reason`, `approved_by`, `approved_at`, `review_remarks`, `replaces_credential_id` | BR-19: a new credential waits for the MSW Committee Head, and a manual reissue says what it replaces. |
| `digital_credentials.revoked_at` | A credential must stop verifying when it is replaced, such as after a role expires. |
| `hr_requests.qualifications` | FR-REC-01 requires qualifications on each HR request. |
| `hr_requests.remarks` | FR-REC-02 and BR-14 require remarks when a request is returned or approved. |
| `vacancies.slots` | The system must know how many slots exist to mark a vacancy as Filled (FR-REC-09). |
| `application_questions.options_json` | Select-type questions need a list of choices. |
| `application_documents.verification_remarks` | Applicants need to know why a document needs correction. |
| `ai_extractions.source_type` | Extractions can come from application documents or eligibility screenings. |
| `interviews.reminder_sent_at` | Prevents the hourly reminder job from sending the 24-hour reminder twice. |
| `interviews.identity_verified`, `role_confirmed`, `privacy_reminder_given`, `conflict_of_interest`, `checklist_by`, `checklist_at` | BR-25: the interview checklist from AGILE's INTERVIEW_CONTROL sheet. A CHECK constraint (`chk_interviews_checklist`) refuses a confirmed checklist with an item missing. |
| `appointments.status` | Separates a prepared appointment from one the MSW Head approved or returned (FR-REC-08). |
| `appointments.prepared_by` | Records which MSW Deputy Head or Member prepared the appointment (FR-REC-08). |
| `welfare_cases.guest_name` | Guest cases have no user account to take the name from (BR-17). Encrypted. |
| `welfare_cases.guest_email` | Guests prove ownership and receive updates through this email (BR-17). Encrypted. |
| `welfare_cases.guest_email_hash` | Finds a guest's cases by email without decrypting every row (keyed HMAC). |
| `welfare_cases.guest_student_no` | Holds the optional PUP student number from the guest form (BR-17). Encrypted. |
| `welfare_cases.student_marked_urgent` | Keeps the student's own urgency flag separate from the priority set by the MSW Head. |
| `welfare_cases.acknowledged_at` | Needed to enforce the 24-hour acknowledgment rule (BR-07). |
| `welfare_cases.closing_remarks` | BR-14 requires remarks when a case is closed. |
| `case_followups.reminder_sent_at` | Prevents the follow-up job from sending duplicate reminders. |
| `welfare_attachments.original_name` | Files are stored under random names, so the original name is kept for display, encrypted (Step 22). |
| `content_posts.excerpt` | Provides the short summary shown on list pages. |
| `content_posts.venue_or_link` | Events must show a venue or link (FR-PP-02). |
| `content_posts.review_remarks` | BR-14 requires remarks when content is returned. |
| `notifications.recipient_email` | Guests have no user account, so their email address is stored directly (BR-17). |
| `notifications.title` | In-app notifications need a short title in the bell list. |
| `notifications.link` | In-app notifications need a page to open when clicked. |
| `notifications.last_error` | Records why an email failed, to support retries and troubleshooting. |
| `academic_terms.grace_days` | Sets how long after the deadline a screening is marked Incomplete. |
| `academic_terms.current_flag` | Generated from `is_current` so a UNIQUE index allows only one current term. |
| `positions.committee_key` | Generated `IFNULL(committee_id, 0)` so a UNIQUE index covers executive posts too. |

## Other changes

These are not marked *(addition)* in section 9, but they also differ from the SAD or fill a gap in section 9.

| Item | Reason |
|---|---|
| `welfare_cases.user_id` is nullable | A guest case has no user account (BR-17). |
| `notifications.user_id` is nullable | Emails to guests have no user account (BR-17). |
| `welfare_case_history.actor_id` is nullable | Guest submissions and guest tracking views have no user to record (BR-17). A NULL actor means a guest or the system. |
| `CHECK (slots > 0)` on `vacancies` | Applies the rule section 9 gives for `hr_requests.slots` to the slots copied into a vacancy. |

## Seed data

`database/seed.php` loads the reference data in `database/seeds/`. Running it again leaves existing rows unchanged.

- Five email templates are added to the 23 listed in section 10, because the jobs and recruitment flow it describes need them: `WELFARE_FOLLOWUP_REMINDER`, `APP_PENDING_REMINDER`, `APP_DOCUMENT_CORRECTION`, `HR_REQUEST_APPROVED` and `HR_REQUEST_RETURNED`.
- Demo users, the 3 demo applicants and the approved applicant who has not registered (`2024-00114-OU-0`) are skipped when `APP_ENV=production`, because they share a published password. There is no applicant role. `nomember@agile.test` is a test account with no member record.
- Committees and positions are AGILE's real structure (update R6): the 7 committees, each with Committee Head, Deputy Committee Head and Committee Member; The Source Code with Editor-in-Chief, Managing Editor, News Editor, Feature Editor, Writer, Head Cartoonist, Cartoonist, Layout Editor, Head Photojournalist, Photojournalist and Videographer/Editor; and the Executive Committee positions (President, Vice President for Internal Affairs, Vice President for External Affairs, Secretary, Assistant Secretary, Treasurer, Auditor). The Source Code has no committee head, so the MSW Committee Head decides its applications, and an application for a Committee Head position also goes to the MSW Committee Head. Migration `2026_11_18_dba_hardening.php` renamed the old sample rows (Academic, Events, Communications, Deputy Head) on existing databases.
- `guest@agile.test` represents the guest role and is `disabled`, because guests do not log in (section 5). The `guest` role has no permissions and also holds the disabled accounts retired by migration 003.
- `org.mission` and `org.vision` hold sample wording until AGILE's official text is available.
