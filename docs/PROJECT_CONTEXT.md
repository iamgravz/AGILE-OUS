# AGILE Membership & Student Welfare Management System — Project Context

> Source of truth for the AI coding assistant. Condensed from the SAD document v1.2 (October 2026).
> Put this file at `docs/PROJECT_CONTEXT.md`. Every build prompt refers to its sections (§1–§13).
> If the code and this file disagree, this file wins. If this file is silent, ask before inventing.
> **Update (Oct 30, 2026):** Guests and Applicants can submit and track welfare concerns (BR-17, §5, §6, §9).
> **Update (Oct 31, 2026):** The UI must look human-made, not AI-generated. Follow §12.1 for every screen.
> **Update (Oct 31, 2026, 2nd):** Applicants and guests have **no account and no profile**. They only fill in the membership application form or the welfare concern form (email verified by a one-time code) and track with code + email. A user account is created only when the MSW Head approves a membership application, or by the MSW Head for officers (BR-18). There is no public registration page.
> **Update (Oct 31, 2026, 3rd, replaces the last two sentences above):** The project owner restored `/register`. Every new account is pending until the MSW Committee Head approves it (BR-21). Applying through `/apply` still needs no account. Applications for a committee's positions are decided by that committee's head (BR-20).
> **Update (Oct 31, 2026, UI G1):** Gentelella-style admin look, recreated in our own CSS/JS, AGILE theme (maroon, gold, ink sidebar, IBM Plex). See §12.1. It replaces the flat look from POLISH P1. The stack in §3 does not change.
> **Update (Oct 31, 2026, POLISH P1):** §12.1 now describes a technical, precise IT-professional look: IBM Plex, dark ink chrome and cool grays. It replaces the earlier fonts and warm grays.
> **Update (Oct 31, 2026, 4th, UPDATE R1, replaces the open form above):** `/register` is only for approved applicants. Approval creates a member record with no account. The person registers with their PUP student number, a code goes to the email on their application, and the account then waits for the MSW Committee Head (BR-21).

---

## §1 Project summary

A responsive, secure, database-driven web app for **AGILE** (Academic Guild of IT Learners and Experts), the BSIT student organization of PUP Open University System. It replaces Google Forms + Sheets.

Seven modules:
1. **Public Portal** – mission/vision, news, events (with registration), publications, announcements, open positions, links to apply / ask for help, status tracking by code.
2. **Membership** – registration, profile, application wizard with the questions of AGILE's registration form by role, document upload with version history, decisions, member records, second committee, ID and certificate requests (made from AGILE's template and uploaded by the MSW Committee Head), member verification by the QR on the ID, status/history, audit logs, expiration reminders, candidacy for elected offices and term limits (Article VI).
3. **Recruitment** – HR requests → vacancies → document verification → interviews (AGILE's question bank) → the MSW interviewer's assessment → the Committee Head reads the Applicant profile and decides → appointments; vacancy auto-status.
4. **Welfare** – confidential cases: 7 categories, 4 priorities, attachments, referrals, follow-ups, notes, history, resolution, closure.
5. **Internal Administration** – auth, RBAC, personalized dashboards, search/filter, reports (PDF/CSV), audit trail, user/role/settings management.
6. **Communications & CMS** – branded email templates, drafts, queued notifications, in-app notifications with per-person preferences, publishing workflow, event attendance, service requests (record corrections, documents, questions) with a reply thread.
7. **Academic Eligibility** – semestral screening of officers (not General Members); flag → MSW Committee Head decides → role expires, person stays Active General Member. Optional AI extraction (suggest only).

---

## §2 Working rules for the AI assistant

1. **Stack is locked** (§3). No Laravel/Symfony/React/Tailwind/jQuery. Plain PHP 8 custom MVC + vanilla JS + custom CSS.
2. **Do only the current step.** Don't build ahead, don't refactor unrelated code. If you notice something outside scope, list it under "Notes for later" in your summary.
3. **Small files, clear names.** PSR-12, `declare(strict_types=1);`, namespaces under `App\` (PSR-4 via Composer). One class per file.
4. **Security is not optional** (§11): PDO prepared statements only, `e()` escaping in every view, CSRF token on every POST, RBAC check on every protected route **and** inside the service method.
5. **Business rules live in `app/Services`**, never in controllers or views. Controllers: validate input → call service → render/redirect.
6. **Every sensitive action is audit-logged** via `AuditService::log()`.
7. **Every status change goes through the allowed transitions** in §8. Invalid transition = exception, not a silent update.
8. **No fake data in production code.** Sample data only in `database/seeds/`.
9. **At the end of every step**: update `docs/PROGRESS.md` (what was built, files touched, how to test, known issues), then stop and print a short summary + manual test checklist. Do not start the next step.
10. **Ask instead of guessing** when a requirement is ambiguous. Offer 2 options with a recommendation.
11. **Every screen follows §12.1** (human-made design direction). Never use the patterns in its Avoid list, even if they seem "modern".

---

## §3 Tech stack

| Layer | Choice |
|---|---|
| Server (dev) | XAMPP — PHP 8.2+, MySQL 8 / MariaDB 10.6+, Apache with `public/` as document root (or `php -S localhost:8000 -t public`) |
| Back end | PHP 8.2+, Composer autoload (PSR-4 `App\` → `app/`) |
| Database | MySQL 8, InnoDB, `utf8mb4_unicode_ci`, timezone Asia/Manila |
| Front end | HTML5, custom mobile-first CSS (Grid/Flexbox, breakpoints 576/768/1200px), vanilla JS (Fetch API) |
| Composer libs | `vlucas/phpdotenv`, `phpmailer/phpmailer`, `google/apiclient` (Gmail API, later), `dompdf/dompdf`, `endroid/qr-code`, `ezyang/htmlpurifier` (CMS HTML allow-list) ; *additions, step 18, optional AI only:* `anthropic-ai/sdk`, `symfony/http-client`, `nyholm/psr7` ; dev: `phpunit/phpunit` |
| Self-hosted JS | Quill (rich-text editor for CMS), Chart.js (dashboards) — saved in `public/assets/vendor/`, no CDNs |
| Scheduler | Cron / Windows Task Scheduler running `php jobs/<job>.php` |
| Mail in dev | `MAIL_DRIVER=log` writes each email as an .html file to `storage/mail/` (no real sending needed) ; `smtp` (PHPMailer) ; `gmail` (Gmail API OAuth) |

---

## §4 Folder structure

```
/public              index.php (front controller), .htaccess, assets/css, assets/js, assets/img, assets/vendor  ← ONLY web-accessible folder
/app/Controllers     Portal, Auth, Membership, Recruitment, Welfare, Admin, Cms, Eligibility, Dashboard, Profile, File, Verify
/app/Core            Router, Request, Response, View, Database, Session, Csrf, Validator, Config
/app/Middleware      AuthMiddleware, GuestMiddleware, CsrfMiddleware, PermissionMiddleware, RateLimitMiddleware
/app/Services        AuthService, RbacService, AuditService, NotificationService, Mailer/*, FileStorageService,
                     EncryptionService, MembershipService, RecruitmentService, WelfareService, CmsService,
                     EligibilityService, CredentialService (PDF/QR), ReportService, AiExtractionService
/app/Models          PDO repositories, one per table (e.g. UserRepository)
/app/Views           layouts/public.php, layouts/dashboard.php, partials/, and one folder per module
/app/helpers.php     e(), csrf_field(), url(), asset(), old(), flash(), can(), status_badge()
/config              app.php, routes.php, permissions.php
/jobs                send_email_queue.php, close_vacancies.php, role_expiration_reminders.php, interview_reminders.php,
                     welfare_followups.php, eligibility_reminders.php, application_reminders.php, backup_database.php
/storage             uploads/, credentials/, mail/, logs/, backups/, cache/   ← NOT web-accessible
/database            schema.sql, migrations/, seeds/
/tests               PHPUnit tests + docs/test-cases.md
/docs                PROJECT_CONTEXT.md, PROGRESS.md
.env / .env.example  secrets (DB, APP_KEY, ENCRYPTION_KEY, MAIL_*), .env is git-ignored
```

---

## §5 Roles

| Slug | Name | Summary |
|---|---|---|
| `guest` | Guest / Applicant | **No account, no profile, no login.** Public portal. Fills in the **membership application form** (then called an *Applicant*) or the **welfare concern form**, after verifying their email with a one-time code. Tracks applications and welfare cases at `/track` with code + email; edits a draft or an application returned *For Revision* through a secure link emailed to them. |
| `general_member` | General Member | Active member, no officer role. First role with an account: an approved applicant registers at `/register`, and the account is usable once the MSW Head approves it (BR-21). Profile, ID, certificate, welfare, applies for vacancies while logged in. Excluded from eligibility screening. |
| `committee_head` | Committee Head | Files HR requests (own committee), reads the Applicant profile (answers, interview, assessment) and decides applications to their committee (BR-20), takes event attendance, drafts content. |
| `execomm` | Executive Committee | View-only dashboards. |
| `msw_member` | MSW Deputy Head / MSW Member | Read/write applications, status, document verification, interviews and the applicant assessment, appointment prep, event attendance, service requests. **Cannot** approve/reject applications, approve appointments, or see welfare cases. |
| `msw_head` | MSW Committee Head | **System administrator.** Only role that sees all data. Final decisions on applications, HR requests, vacancies, appointments, welfare, eligibility, content publishing. Manages users/roles/settings. |
| `president` | President | View-only dashboards + full access to welfare cases (view, notes, referrals, follow-ups). Cannot assign/reassign/close cases. |

Notes:
- There is **no `applicant` role**. "Applicant" in this document means a person with an application and no usable account yet.
- The technical maintainer has **no app role** and cannot read welfare content (encrypted).
- Officers who are committee deputies/members outside MSW, or The Source Code members, keep role `general_member` with `members.membership_type='officer'` and a `position_id`. They are covered by eligibility screening through their position's `role_type`, not through their role.
- A user can submit eligibility documents only if they have a screening row for the current term.

---

## §6 Permissions

Codes (stored in `permissions.code`; mapped to roles in `role_permissions`; extra grants in `user_permissions`). Default deny.

| Code | general_member | committee_head | execomm | msw_member | msw_head | president |
|---|---|---|---|---|---|---|
| `account.approve` *(addition, BR-21)* | | | | | ✔ | |
| `application.submit` (vacancy applications while logged in) | ✔ | | | | | |
| `service_request.submit` | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| `service_request.manage` | | | | ✔ | ✔ | |
| `candidacy.file` | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| `welfare.submit` | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| `profile.manage_own` (profile, ID, certificate) | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| `hr_request.submit` (own committee) | | ✔ | | | ✔ | |
| `hr_request.approve` / `vacancy.manage` | | | | | ✔ | |
| `document.verify` / `application.update_status` | | | | ✔ | ✔ | |
| `application.view` (read applications and their assessment) | | ✔ (read) | | ✔ | ✔ | |
| `event.attendance` | | ✔ | | ✔ | ✔ | |
| `interview.manage` | | | | ✔ | ✔ | |
| `application.decide` (approve/reject; committee heads: own committee only, BR-20) | | ✔ | | | ✔ | |
| `application.return` (return for revision) | | | | ✔ | ✔ | |
| `appointment.prepare` | | | | ✔ | ✔ | |
| `appointment.approve` | | | | | ✔ | |
| `member.view` | | | | ✔ | ✔ | |
| `credential.reissue` *(addition)* | | | | | ✔ | |
| `credential.approve` *(addition, BR-19)* | | | | | ✔ | |
| `welfare.view` / `welfare.handle` (notes, referrals, follow-ups) | | | | | ✔ | ✔ |
| `welfare.assign_close` | | | | | ✔ | |
| `content.draft` | | ✔ | | ✔ | ✔ | |
| `content.publish` | | | | | ✔ | |
| `email.compose` | | | | ✔ | ✔ | |
| `email_template.manage` | | | | | ✔ | |
| `eligibility.manage` (terms, verify, decide, AI) | | | | | ✔ | |
| `eligibility.decide_msw_head` *(addition)* (verify and decide the MSW Head's own screening only) | | | | | | ✔ |
| `dashboard.view_all` (read-only, all modules) | | | ✔ | | ✔ | ✔ |
| `report.export` | | | | | ✔ | |
| `admin.users` / `admin.settings` / `audit.view` / `backup.manage` | | | | | ✔ | |

Guest / Applicant access (no account, no permission rows; public routes with rate limits):
- **Apply for membership** via `/apply` — the public application wizard. Email verified with a 6-digit one-time code at the start; drafts are saved and resumed through a secure link emailed to them.
- **Submit a welfare concern** via `/welfare/guest` — name, email, optional PUP student number, consent; same email code.
- **Track** an application or welfare case at `/track` with code + email (status and non-confidential updates only).
- **Revise** an application returned *For Revision*: `/track` offers "Email me an edit link"; the link (single application, 72-hour expiry) reopens the wizard. Knowing the code + email alone never allows editing.
- **Withdraw** an application while it is *Submitted* *(addition)*: `/track` offers "Email me a withdrawal link", under the same rules.

Record-level rules (checked in services, on top of permissions):
- Everyone (guests/applicants and all logged-in roles) can track/view **their own** applications and **status only** of their own welfare cases. People without an account prove ownership with code + the email used on the form.
- Committee heads submit HR requests only for **their own committee**.
- **One assessment per application**, by an MSW interviewer after a passed or failed interview; saving again replaces it. An interviewer who declared a conflict of interest cannot assess (BR-25), and nobody assesses their own application.

---

## §7 Business rules

- **BR-01** Only currently enrolled BSIT-OU students may apply.
- **BR-02** One active application per vacancy per applicant email / student number (general membership = vacancy NULL counts as one).
- **BR-03** Application can't be approved until **all required documents are Verified**. Who approves or rejects is in BR-20.
- **BR-04** Only MSW Committee Head creates vacancies, only from an **approved** HR request. Vacancy closes automatically on closing date or when slots are filled.
- **BR-05** *(update, Nov 23 2026)* The MSW interviewer assesses the applicant after the interview, following AGILE's Applicant Evaluation sheet (one assessment per application). Rubric scoring by several evaluators was retired. Who accepts an application is in BR-20. Only MSW Head approves appointments.
- **BR-06** Welfare cases (details, notes, attachments) visible **only** to MSW Head and President. Every view is logged.
- **BR-07** Only MSW Head assigns/reassigns/closes cases. Urgent and Harassment/Bullying cases must be acknowledged within 24 hours.
- **BR-08** Eligibility screening each semester for officers (executive, committee heads/deputies/members, The Source Code). General Members excluded.
- **BR-09** Eligible = no failed subjects AND officially enrolled for the whole semester.
- **BR-10** Flagged officer is notified. MSW Head decides; if confirmed, role expires and person stays Active General Member. *(update, Oct 2026)* The MSW Head cannot verify or decide their own screening; the President does both for that screening only. The President's own screening stays with the MSW Head. *(update, Oct 31 2026, decided by the project owner)* The role of the only active MSW Committee Head cannot end, by this decision or by an appointment: the action is refused until another active MSW Committee Head exists, because only that role can approve an appointment.
- **BR-11** AI values are suggestions; must be confirmed by MSW Head; AI never approves/rejects/disqualifies.
- **BR-12** Content is public only after MSW Head approval.
- **BR-13** "Active" is the only membership status. Only officer **roles** expire.
- **BR-14** Every decision (approve, reject, return, appoint, close, remove role) **requires remarks** and is audit-logged.
- **BR-15** Closed welfare cases can't be reopened; student submits a new case (may reference old code). After closure MSW Head records the attachment disposition.
- **BR-16** Only MSW Head sees all data; others see only what §6 allows.
- **BR-18** *(update, Oct 31 2026, UPDATE R1 and R3)* Applicants and guests do not create accounts or profiles. The membership application and welfare concern are standalone forms with email verification. Approval creates the member record (member number) but NO account. The approved applicant registers at `/register`: they enter the PUP student number exactly as approved on the application, a 6-digit code goes to the email used on that application (never a new email), they enter the code, then set a password and give privacy consent. The account (role general_member) is created and linked to the member record, the applicant and earlier guest welfare cases with the same email, and it is pending under BR-21. Unknown student numbers, unapproved applications and student numbers that already have an account get the same generic message and nothing is created. One account per student number. Rate limited per IP and per student number. Officer accounts can still be created by the MSW Head with the ACCOUNT_SETUP link.
- **BR-19** *(added, UPDATE R2)* A newly generated digital ID or certificate is "pending approval". Only the MSW Committee Head can approve it (one at a time or in bulk) or return it with remarks. Members can view and download only approved, non-revoked credentials.
- **BR-20** *(added, Oct 31 2026, UPDATE R4)* Who accepts or rejects an application: general membership, an executive position (no committee) or a position in the MSW Committee: the MSW Committee Head. A position in any other committee: the head of THAT committee (`committees.head_user_id`). Fallbacks: if the committee has no head, or the head is the applicant, the MSW Committee Head decides. `MembershipService::decider()` holds these rules. A committee head can never see the decision panel for, or decide, another committee's applications; a forced attempt gets 403 and `access.denied`. MSW Deputy and Members still verify documents, schedule interviews and can Return for Revision. BR-03 still applies, remarks are always required, and everything is audit-logged (BR-14). When an application is under review with all required documents verified, the decider is notified (APP_READY_FOR_DECISION). *Decided by the project owner:* a committee head's acceptance is not the appointment. The MSW Committee Head still approves the appointment, and that is when the person gets the position and `slots_filled` goes up.
- **BR-21** *(added, Oct 31 2026, UPDATE R4)* An account created at `/register` starts as pending. Login shows: "Your account is waiting for approval by the MSW Committee Head. We'll email you once it's approved." The MSW Head approves or rejects it with remarks (`account.approve`). Approve: status active, `approved_by`/`approved_at`, ACCOUNT_APPROVED. Reject (remarks required): ACCOUNT_REJECTED with the reason; the account is disabled and freed, any ID and certificate are revoked, and the student number may register again after the issue is fixed. Officer accounts the MSW Head creates are already approved. The "New accounts for approval" list shows name, student number, email, member number, a link to the approved application and the registered date.
- **BR-17** *(update, Oct 2026)* Guests and Applicants (who have no account, BR-18) may submit and track welfare concerns through the public welfare form. A case requires a verified email (one-time code) and is tracked with case code + that email. These cases follow the same confidentiality (BR-06), urgent alert (BR-07) and closure (BR-15) rules as member cases.

Validation defaults: uploads PDF/JPG/PNG, max 5 MB each (setting `upload.max_mb`), password ≥ 8 chars with letters and numbers, lists paginated 20 rows.

---

## §8 Status lifecycles (allowed transitions only)

| Record | Transitions |
|---|---|
| Membership application | draft→submitted→under_review→approved / rejected / for_revision / waitlisted ; waitlisted→approved / rejected ; for_revision→submitted ; submitted→withdrawn (applicant) |
| Applicant document | pending→verified / needs_correction / rejected ; needs_correction→pending (re-upload) |
| HR request | submitted→approved / returned ; returned→submitted ; submitted→cancelled |
| Vacancy | draft→open→closed / filled ; closed→open (reopen) ; closed→filled *(addition, the last slot taken by an appointment)* ; draft/open→cancelled |
| Membership | always `active` |
| Officer role | active→expired (MSW Head confirms ineligibility) |
| Welfare case | submitted→in_progress→referred / resolved ; referred→in_progress / resolved ; resolved→closed (MSW Head only). closed is final |
| Content post | draft→for_approval→published / returned ; returned→for_approval ; published→archived |
| Eligibility screening | pending→submitted→eligible / flagged ; pending→incomplete→flagged ; flagged→confirmed_ineligible / cleared |
| Notification | queued→sent / failed ; failed→queued (retry, max 3 attempts) |
| Appointment (addition) | prepared→approved / returned |
| Digital credential *(addition, BR-19)* | pending_approval→approved / returned ; returned→pending_approval (regenerated) ; approved→revoked |

Implement each lifecycle as a small transition map in the related service, e.g. `ApplicationStatus::canTransition($from, $to)`.

---

## §9 Database (MySQL 8, 3NF, InnoDB, utf8mb4, surrogate PKs, all FKs)

`AI` = auto-increment. All tables get `created_at`/`updated_at` DATETIME unless noted. Encrypted columns store AES-256-GCM ciphertext (base64) — use `TEXT`.

**Access control & admin**
- `roles` (role_id PK AI, slug VARCHAR(40) UNIQUE, role_name VARCHAR(50) UNIQUE, description)
- `permissions` (permission_id PK AI, code VARCHAR(80) UNIQUE, description)
- `role_permissions` (role_id FK, permission_id FK) PK(role_id, permission_id)
- `users` (user_id PK AI, role_id FK, email VARCHAR(150) UNIQUE, password_hash, first_name, last_name, student_no VARCHAR(20) UNIQUE NULL, contact_no, photo_path NULL, status ENUM('pending','active','locked','disabled') DEFAULT 'pending', email_verified_at NULL, approved_by FK users NULL *(addition, BR-21)*, approved_at NULL *(addition, BR-21)*, rejection_remarks TEXT NULL *(addition, BR-21)*, failed_logins TINYINT DEFAULT 0, locked_until DATETIME NULL, last_login_at NULL)
- `user_permissions` (user_id FK, permission_id FK) PK both
- `user_tokens` *(addition)* (token_id PK, user_id FK, type ENUM('account_setup','password_reset'), token_hash CHAR(64), expires_at, used_at NULL)
- `rate_limits` *(addition)* (rl_key VARCHAR(120) PK, attempts INT, window_start DATETIME)
- `committees` (committee_id PK, name UNIQUE, description, head_user_id FK users NULL — a deliberate copy of who holds the committee's Committee Head position, kept in sync by MembershipService::changeRole() and checked by `database/report.php`)
- `positions` (position_id PK, committee_id FK NULL (NULL = executive post), title, role_type ENUM('officer','committee_head','deputy','committee_member','source_code','general')) — UNIQUE(committee, title) through a generated `committee_key` (0 for executive posts)
- `audit_logs` (log_id BIGINT PK, user_id FK NULL, action VARCHAR(60), entity_type VARCHAR(50) NULL, entity_id INT NULL, details_json JSON NULL, ip_address VARCHAR(45), user_agent VARCHAR(255), created_at) — **insert-only, no UI edit/delete**
- `settings` (setting_key VARCHAR(80) PK, setting_value TEXT, updated_by FK NULL, updated_at) — *addition, P7:* keys `landing.headline`, `landing.subheadline`, `landing.taglines`, `landing.hero_image`, `landing.join_steps`, `landing.show_sections`. Until saved, the code's defaults apply, so no database change is needed
- `job_runs` *(addition, step 21)* (run_id BIGINT PK, job VARCHAR(60), started_at DATETIME, finished_at DATETIME NULL, result ENUM('running','ok','failed') DEFAULT 'running', summary VARCHAR(255) NULL) — last run of each job, for System health; rows older than 90 days are removed

**Membership & recruitment**
- `members` (member_id PK, user_id FK UNIQUE NULL (set when they register), applicant_id FK UNIQUE NULL *(addition, BR-18)*, position_id FK NULL, member_no VARCHAR(20) UNIQUE e.g. AGILE-2026-0001, membership_type ENUM('general','officer'), status ENUM('active') DEFAULT 'active', role_status ENUM('active','expired') NULL, role_expired_at NULL, joined_at DATE, registered_at NULL *(addition, BR-18)*, registration_reminders TINYINT DEFAULT 0 *(addition, R3)*)
- `membership_status_history` (history_id PK, member_id FK, old_role VARCHAR(60), new_role VARCHAR(60), reason VARCHAR(255), changed_by FK users, changed_at)
- `digital_credentials` (credential_id PK, member_id FK, type ENUM('digital_id','certificate'), serial_no VARCHAR(30) UNIQUE, qr_token CHAR(64) UNIQUE, file_path, status ENUM('pending_approval','approved','returned') DEFAULT 'pending_approval' *(addition, BR-19)*, reason ENUM('new_member','role_change','reissue') *(addition, BR-19)*, issued_at, revoked_at NULL *(addition)*, approved_by FK users NULL *(addition, BR-19)*, approved_at NULL *(addition, BR-19)*, review_remarks TEXT NULL *(addition, BR-19)*, replaces_credential_id FK NULL *(addition, BR-19)*)
- `hr_requests` (request_id PK, committee_id FK, position_id FK, requested_by FK users, slots TINYINT >0, justification TEXT, qualifications TEXT *(addition)*, status ENUM('submitted','approved','returned','cancelled'), remarks TEXT NULL *(addition)*, approved_by NULL, approved_at NULL)
- `vacancies` (vacancy_id PK, request_id FK, position_id FK, title VARCHAR(150), description TEXT, qualifications TEXT NULL, slots TINYINT *(addition)*, slots_filled TINYINT DEFAULT 0, opens_at, closes_at, status ENUM('draft','open','closed','filled','cancelled')) — CHECK slots > 0, slots_filled <= slots, closes_at > opens_at
- `applicants` *(addition, BR-18)* (applicant_id PK, email VARCHAR(150) UNIQUE, first_name, last_name, student_no VARCHAR(20) NULL UNIQUE (BR-02), contact_no NULL, year_level NULL, email_verified_at, user_id FK users NULL (set when they register)) — people without accounts
- `membership_applications` (application_id PK, applicant_id FK applicants NULL *(addition)*, user_id FK users NULL (set when a logged-in member applies for a vacancy; exactly one of applicant_id / user_id is set, enforced by a CHECK constraint), vacancy_id FK NULL (NULL = general membership), tracking_code CHAR(12) UNIQUE, status ENUM('draft','submitted','under_review','for_revision','waitlisted','approved','rejected','withdrawn'), decision_remarks TEXT NULL, decided_by NULL, decided_at NULL, consent_at NULL (required at submit), submitted_at NULL, wizard_step TINYINT *(addition)*, enrollment_confirmed_at NULL *(addition, BR-01)*)
- `application_status_history` *(addition)* (id PK, application_id FK, old_status, new_status, remarks TEXT NULL, changed_by FK users NULL, changed_at) — powers the status timeline
- `application_questions` (question_id PK, role_type VARCHAR(30) — use `common` for questions everyone gets, input_type ENUM('text','textarea','select','checkbox','file'), options_json JSON NULL *(addition)*, question_text, is_required BOOL DEFAULT 1, sort_order)
- `application_answers` (answer_id PK, application_id FK, question_id FK, answer_text TEXT NULL)
- `application_documents` (document_id PK, application_id FK, doc_type VARCHAR(50) e.g. school_id/cor/certificate, original_name, stored_path, mime_type, size_bytes, verification_status ENUM('pending','verified','needs_correction','rejected') DEFAULT 'pending', verification_remarks NULL *(addition)*, verified_by NULL, verified_at NULL)
- `ai_extractions` (extraction_id PK, source_type ENUM('application_document','eligibility_screening') *(addition)*, source_id INT, extracted_json JSON, confidence DECIMAL(4,3) NULL, status ENUM('suggested','confirmed','discarded'), verified_by NULL, verified_at NULL)
- `evaluations` *(history only since Nov 23 2026; replaced by `applicant_assessments`)* (evaluation_id PK, application_id FK, evaluator_id FK users, score DECIMAL(5,2), rubric_json JSON, recommendation ENUM('recommend','hold','not_recommend'), remarks TEXT) — UNIQUE(application_id, evaluator_id); CHECK score BETWEEN 0 AND 100
- `interviews` (interview_id PK, application_id FK, interviewer_id FK users, scheduled_at, mode ENUM('online','onsite'), location VARCHAR(255), result ENUM('pending','passed','failed','no_show') DEFAULT 'pending', notes TEXT NULL, reminder_sent_at NULL *(addition)*)
- `appointments` (appointment_id PK, application_id FK, member_id FK NULL, position_id FK, status ENUM('prepared','approved','returned') *(addition)*, prepared_by FK users *(addition)*, appointed_by FK users NULL, remarks TEXT NULL, effective_date DATE)

**Welfare** (subject, description, closing_remarks, notes, referral and follow-up remarks, actions_taken, summary and attachment files are **encrypted**)
- `welfare_categories` (category_id TINYINT PK 1–7, name UNIQUE): Academic, Financial, Mental Health, Discipline, Scholarship, Harassment/Bullying, Others
- `guest_verifications` *(addition, BR-17/BR-18)* (verification_id PK, email VARCHAR(150), code_hash CHAR(64), purpose ENUM('welfare_submit','application_submit','member_registration'), attempts TINYINT DEFAULT 0, expires_at, verified_at NULL) — 6-digit code, 15-min expiry, max 5 attempts
- `application_access_links` *(addition, BR-18)* (link_id PK, application_id FK, token_hash CHAR(64), purpose ENUM('resume_draft','revise','withdraw'), expires_at, used_at NULL) — emailed links to continue a draft, revise or withdraw; 72-hour expiry
- `welfare_cases` (case_id PK, case_code CHAR(12) UNIQUE, user_id FK **NULL** (NULL = guest case), guest_name TEXT NULL enc *(addition)*, guest_email TEXT NULL enc *(addition)*, guest_email_hash CHAR(64) NULL *(addition: keyed hash to find a guest's cases, M3)*, guest_student_no TEXT NULL enc *(addition)*, category_id FK, priority ENUM('low','medium','high','urgent') DEFAULT 'medium', student_marked_urgent BOOL *(addition)*, subject TEXT enc (max 150 characters before encryption), description TEXT enc, assigned_to FK users NULL, related_case_id FK welfare_cases NULL, status ENUM('submitted','in_progress','referred','resolved','closed'), acknowledged_at NULL *(addition, BR-07)*, closing_remarks TEXT NULL enc *(addition)*, closed_by NULL, submitted_at, closed_at NULL) — CHECK a member or a guest email owns the case; CHECK guest_email and guest_email_hash are set together
- `case_notes` (note_id PK, case_id FK, author_id FK, note TEXT enc, visible_to_student BOOL DEFAULT 0, created_at)
- `referrals` (referral_id PK, case_id FK, referred_to TEXT enc, remarks TEXT enc, status ENUM('pending','accepted','completed','declined'), referred_by FK, referred_at)
- `case_followups` (followup_id PK, case_id FK, due_at, handler_id FK, done_at NULL, remarks TEXT NULL enc, reminder_sent_at NULL *(addition)*)
- `case_resolutions` (resolution_id PK, case_id FK UNIQUE, actions_taken TEXT enc, outcome ENUM('resolved','partially_resolved','referred_out','withdrawn'), summary TEXT enc, resolved_by FK, resolved_at)
- `welfare_case_history` (history_id BIGINT PK, case_id FK, event_type VARCHAR(40) (status_change, category_change, priority_change, assigned, note_added, referral, followup, resolved, closed, viewed; *additions:* acknowledged, disposition, overdue_reminder, linked_to_account), old_value, new_value, actor_id FK, created_at)
- `welfare_attachments` (attachment_id PK, case_id FK, original_name *(addition)*, stored_path, mime_type, size_bytes, disposition ENUM('open','kept','returned','deleted') DEFAULT 'open', disposition_by NULL, disposition_at NULL)

**Content, communications, eligibility**
- `content_posts` (post_id PK, type ENUM('news','event','publication','announcement'), title VARCHAR(200), slug UNIQUE, excerpt VARCHAR(300) *(addition)*, body MEDIUMTEXT (purified HTML), event_start NULL, event_end NULL, venue_or_link NULL *(addition)*, cover_image NULL, status ENUM('draft','for_approval','published','returned','archived'), review_remarks NULL *(addition)*, author_id FK, approved_by FK NULL, published_at NULL)
- `email_templates` (template_id PK, code VARCHAR(60) UNIQUE, subject VARCHAR(200), body_html MEDIUMTEXT, updated_by FK NULL)
- `email_drafts` *(addition, FR-COM-02)* (draft_id PK, subject, body_html, target_group VARCHAR(60) e.g. all_members/officers/applicants/committee:{id}, status ENUM('draft','queued','sent'), created_by FK, sent_at NULL)
- `notifications` (notification_id PK, user_id FK **NULL**, recipient_email VARCHAR(150) NULL *(addition — for guests, email channel only)*, template_id FK NULL, channel ENUM('email','in_app'), title VARCHAR(200) *(addition)*, link VARCHAR(255) NULL *(addition)*, payload_json JSON, status ENUM('queued','sent','failed') DEFAULT 'queued', attempts TINYINT DEFAULT 0, last_error NULL *(addition)*, scheduled_at NULL, sent_at NULL, read_at NULL)
- `academic_terms` (term_id PK, academic_year CHAR(9) e.g. 2026-2027, semester ENUM('1st','2nd','summer'), screening_deadline DATE, grace_days TINYINT DEFAULT 7 *(addition)*, is_current BOOL DEFAULT 0) — UNIQUE(academic_year, semester); only one current term (generated `current_flag`, UNIQUE); CHECK academic_year is YYYY-YYYY+1
- `eligibility_screenings` (screening_id PK, term_id FK, member_id FK, has_failed_subject BOOL NULL, is_officially_enrolled BOOL NULL, result ENUM('pending','submitted','eligible','flagged','incomplete','confirmed_ineligible','cleared'), flagged_at NULL, verified_by NULL, verified_at NULL) — UNIQUE(term_id, member_id)
- `eligibility_documents` *(addition, step 17: an officer may submit several files; it replaced `eligibility_screenings.document_path`, which was dropped)* (document_id PK, screening_id FK, original_name, stored_path, mime_type, size_bytes)
- `eligibility_reviews` (review_id PK, screening_id FK UNIQUE, decision ENUM('confirm_ineligible','clear'), remarks TEXT NOT NULL, decided_by FK, decided_at)

**Additions, November 22–23, 2026** (details and reasons in [database.md](database.md))
- `interview_questions` (question_id PK, applicant_type VARCHAR(30) (core, general, committee_member, deputy, committee_head, source_code), committee VARCHAR(100) NULL, position VARCHAR(100) NULL, code VARCHAR(10), question VARCHAR(500), competency VARCHAR(100), sort_order)
- `applicant_assessments` (assessment_id PK, application_id FK UNIQUE, evaluator_id FK users, ratings_json JSON, application_score, interview_score, role_score, total_score TINYINT, recommendation ENUM('highly_recommended','recommended','recommended_with_considerations','further_evaluation','not_recommended'), strengths/development/remarks TEXT NULL)
- `document_versions` (version_id PK, document_id FK application_documents, version_no, original_name, stored_path, mime_type, size_bytes, verification_status, verification_remarks NULL, uploaded_at, replaced_at) — UNIQUE(document_id, version_no)
- `member_committees` (member_committee_id PK, member_id FK, committee_id FK, joined_at DATE, added_by FK NULL) — UNIQUE(member_id, committee_id)
- `credential_requests` (request_id PK, member_id FK, type ENUM('digital_id','certificate'), note NULL, photo_path NULL, status ENUM('requested','issued','declined','cancelled'), credential_id FK NULL, remarks NULL, handled_by FK NULL, handled_at NULL)
- `officer_terms` (term_id PK, member_id FK, position_id FK, academic_year CHAR(9), year_level, recorded_by FK NULL) — UNIQUE(member_id, position_id, academic_year)
- `election_candidacies` (candidacy_id PK, user_id FK, position_id FK, academic_year, year_level, section, contact_no, statement NULL, cor_path, grades_path, status ENUM('filed','qualified','disqualified','withdrawn','elected'), remarks NULL, decided_by FK NULL, decided_at NULL)
- `event_registrations` (registration_id PK, post_id FK content_posts, member_id FK, status ENUM('registered','cancelled'), walk_in BOOL, registered_at, cancelled_at NULL, attended_at NULL, checked_in_by FK NULL) — UNIQUE(post_id, member_id)
- `notification_preferences` (user_id FK + category VARCHAR(30) PK, email BOOL, in_app BOOL) — no row means all on
- `service_requests` (request_id PK, user_id FK, type ENUM('record_update','document','inquiry','technical','other'), subject VARCHAR(150), status ENUM('open','in_progress','resolved','closed','cancelled'), assigned_to FK NULL, resolved_at NULL)
- `service_request_messages` (message_id PK, request_id FK, author_id FK users, from_staff BOOL, body TEXT)
- New columns: `users.year_level`, `users.section`, `welfare_cases.submitter_contact_no/_year_level/_section`, `members.verify_token`, `application_questions.is_active`, `application_documents.version_no`, `content_posts.registration_open`, `content_posts.capacity`

Seed data: 7 roles (no applicant role), all permission codes + role mapping from §6, 7 welfare categories, AGILE's real structure (update R6): Executive Committee positions (President, Vice President for Internal Affairs, Vice President for External Affairs, Secretary, Assistant Secretary, Treasurer, Auditor), the 7 committees (Academics and Training; Research and Extensions; Creatives, Social Media and Communication; Finance and Fund Raising; Logistics; Membership and Student Welfare; Events and External Relations) each with Committee Head / Deputy Committee Head / Committee Member, and The Source Code with its 11 positions (Editor-in-Chief first) and no committee head, email templates from §10, default settings (incl. `welfare.guest_submission=1`), one sample user per role (password `Password123!`), 3 sample applicants without accounts (with submitted applications in different statuses), 1 approved applicant who has not registered yet, sample application questions.

---

## §10 Notifications & background jobs

All emails are rows in `notifications` (channel `email`), sent by `jobs/send_email_queue.php`. In-app notifications are rows with channel `in_app` (shown in the bell with unread count). `NotificationService::notify($userId, $templateCode, $payload, $link)` creates both. `NotificationService::notifyEmail($email, $templateCode, $payload)` sends email only to a guest (no account). Templates use placeholders `{name}`, `{status}`, `{tracking_code}`, `{case_code}`, `{link}`, etc. Every email is wrapped in the standard AGILE layout *(update, EMAIL E1)*: the banner `public/assets/img/email-banner.jpg` (embedded inline, falling back to `MAIL_LOGO_URL`, then a text header), the body, a signature from Settings (`email.signatory_name`, `email.signatory_position`, `email.signatory_committee`), a thin gold rule, and a maroon footer (`org.full_name`, the committee name, `org.contact_email`, confidentiality note, unsubscribe note for announcements). Every template opens "Dear {name},", then "Greetings from the AGILE OUS Membership and Student Welfare Committee!", one short paragraph, a details block, the action, and "For assistance, contact {contact_email}."

APP_APPROVED for someone without an account says: "Create your AGILE account at {APP_URL}/register using your PUP student number {student_no}." Template codes: ACCOUNT_SETUP (set-password link for officer accounts), PASSWORD_RESET, GUEST_EMAIL_CODE, CREDENTIALS_FOR_APPROVAL (daily digest to the MSW Head), CREDENTIAL_READY (to the member) *(additions, BR-19)*, MEMBER_REGISTRATION_CODE, MEMBER_REGISTERED, MEMBER_REGISTRATION_REMINDER, ACCOUNTS_FOR_APPROVAL, ACCOUNT_APPROVED, ACCOUNT_REJECTED, APP_READY_FOR_DECISION *(additions, BR-18/20/21)*, APPLICATION_LINK (resume draft / revise link), APP_SUBMITTED, APP_FOR_REVISION, APP_APPROVED, APP_REJECTED, INTERVIEW_INVITE, INTERVIEW_REMINDER, APPOINTMENT_NOTICE, ROLE_EXPIRATION_REMINDER, ROLE_EXPIRED, WELFARE_RECEIVED, WELFARE_UPDATE, WELFARE_CLOSED, WELFARE_URGENT_ALERT, ELIGIBILITY_REQUEST, ELIGIBILITY_FLAGGED, ELIGIBILITY_RESULT, CONTENT_FOR_APPROVAL, CONTENT_RETURNED, HR_REQUEST_SUBMITTED, VACANCY_CLOSED, ANNOUNCEMENT *(addition, step 20: email drafts)*.

| Job | Schedule | Purpose |
|---|---|---|
| send_email_queue.php | every 5 min | Send queued emails; on failure attempts+1, retry up to 3 |
| close_vacancies.php | daily 00:05 | Close vacancies past closes_at / filled; notify committee head |
| role_expiration_reminders.php | daily 08:00 in screening | Officers without documents: 7 days and 1 day before deadline |
| interview_reminders.php | hourly | 24 h before interview |
| welfare_followups.php | daily 08:00 + hourly for urgent | Due follow-ups; urgent/harassment cases unacknowledged (BR-07) |
| eligibility_reminders.php | daily 09:00 in screening | Covered members not yet submitted; mark incomplete after deadline + grace |
| application_reminders.php | daily 09:00 | Apps pending > 3 days (one daily digest to MSW staff); For Revision (email to the applicant every 3 days with a fresh edit link); *(R3)* reminder to register 7 and 14 days after approval if still not registered |
| credential_digest.php *(addition, BR-19)* | daily 08:00 | If anything is pending, CREDENTIALS_FOR_APPROVAL to the MSW Head with the count and a link |
| account_digest.php *(addition, BR-21)* | daily 08:00 | If any account is pending, ACCOUNTS_FOR_APPROVAL to the MSW Head with the count and a link |
| backup_database.php | daily 02:00 | Gzipped dump to storage/backups, delete > 30 days |

`php jobs/run.php all` *(addition)* runs them all in turn, or name the jobs to run. Every job: CLI only (refuse web), lock file to avoid overlap, logs to `storage/logs/jobs.log`, idempotent (safe to run twice).

---

## §11 Security & privacy

- HTTPS in production; cookies HttpOnly + Secure + SameSite=Lax; `session_regenerate_id(true)` on login; 30-min idle timeout. `SessionGuard` checks idle time, account status and password on every request, public routes included, and a changed password ends other logins *(update, step 22)*.
- `password_hash()` (PASSWORD_DEFAULT/Argon2id); lockout 15 min after 5 failed logins; generic error messages.
- Email verification + password reset with random tokens (store **SHA-256 hash** only), 60-min expiry, single use.
- PDO with `ATTR_EMULATE_PREPARES=false`, `ERRMODE_EXCEPTION`; never string-built SQL (whitelist ORDER BY columns).
- `e()` = `htmlspecialchars(…, ENT_QUOTES, 'UTF-8')` on every output; CMS HTML through HTMLPurifier allow-list.
- Headers: Content-Security-Policy (self only), X-Content-Type-Options, X-Frame-Options DENY, Referrer-Policy.
- CSRF token per session in every form and in `X-CSRF-Token` header for fetch(); invalid → 419.
- Uploads: check extension **and** finfo MIME, size limit, random file name, store in `storage/uploads/…`, serve only via authorized `FileController` (permission + ownership check, logged).
- Welfare text, attachment files and their file names, and who a case was referred to *(update, step 22)* encrypted with `EncryptionService` (AES-256-GCM, key from `.env` ENCRYPTION_KEY). Encrypted text cannot be searched in SQL, so the case queue filters by case code, category and status, and matches the subject by decrypting it in memory for those allowed to read it *(update, step 16)*. Every welfare case view is logged in audit_logs and welfare_case_history.
- Rate limit: login, track-status, password reset, guest welfare code requests and submissions (e.g. 5 per 15 min per IP+key). Guest form also has a honeypot field against bots.
- Audit: logins, logouts, failed logins, decisions, status changes, exports, confidential views, permission denials.
- Privacy: consent checkbox + notice on all forms (RA 10173 Data Privacy Act); collect only needed data.
- `.env` never committed; `storage/` and `vendor/` ignored.

---

## §12 UI guidelines

- Brand: **maroon** primary (#8B1E24), **gold** highlight (#D4A72C), very light gray canvas with white cards (§12.1 tokens), AGILE logo on every page and email. Body text ≥ 16px. Contrast ≥ 4.5:1 (WCAG 2.1 AA).
- Public area: top navigation. Restricted area: left sidebar (items shown by permission), top bar with notification bell + user menu. Collapsible sidebar on mobile.
- Status badges always **color + text**: Draft, Submitted, Under Review, For Revision, Waitlisted, Approved, Rejected, Withdrawn, Open, Closed, Filled, In Progress, Referred, Resolved, Closed, Flagged, Eligible, etc.
- Confirmation dialog (custom modal, not `confirm()`) for reject, close case, remove role, delete.
- Forms: labels on every field, inline validation messages, keep old input on error, required markers.
- Tables: search + filters + pagination (20), empty states, responsive (stack into cards on mobile).
- Key screens: Home, Application Wizard, Track Status, Welfare Form, Role Dashboard, Applications List, Application Detail, Welfare Queue / Case View, Member Profile, CMS Editor, Eligibility Screening, Dashboards & Reports, Administration.

### §12.1 Design direction: it must look human-made, not AI-generated

**Gentelella-style admin look, recreated in our own CSS/JS, AGILE theme (maroon, gold, ink sidebar, IBM Plex).** Gentelella v4 is only a visual reference. None of its code is used, and nothing needs a build step.

**Use**
- Fonts (self-hosted woff2 in `public/assets/fonts/`, no CDN): **IBM Plex Sans** 400/500/600 and 400 italic for text, **IBM Plex Mono** 400/500 for codes, IDs, numbers, timestamps and eyebrows. Type scale: 12 (eyebrows and table headers only) / 14 / 16 / 18 / 22 / 28 / 36 px.
- Colors: every color is a custom property in `public/assets/css/tokens.css`. The site is light only. Primary maroon `#8B1E24` (hover `#6E161B`, on dark surfaces `#E5676D`); gold `#D4A72C` for the active nav indicator, focus ring and current wizard step; sidebar ink `#0D1117` with `#C9D1D9` text; canvas `#F6F8FA`; muted semantic colors. Every text and background pair is at least 4.5:1.
- No dark mode (removed Nov 19, 2026 at AGILE's request). `theme-init.js` only restores the sidebar state before paint; it is a file because the CSP blocks inline scripts.
- Shell: dark sidebar of about 248px that collapses to a 64px icon rail (remembered), logo at the top, user at the bottom, mono uppercase group labels; active item = maroon-on-dark text + gold left bar. White top bar of about 56px: menu toggle, breadcrumb "Home › Page", search with a `/` hint (`/` or Ctrl+K), bell, inbox, avatar.
- Page header: small uppercase eyebrow above a bold title, actions on the right (one outline + one solid primary).
- Cards: white, 1px border, 8px radius, very soft shadow. Stat cards in a 3-column grid (stacks on mobile). Tables: uppercase gray header on a light band, roomy rows, status pills (color + text), numbers right-aligned, sticky header. Text left-aligned.
- Dashboards use real data only: a trend chip only when computed against the previous period, a sparkline only from real daily counts, a progress bar only with a real total. "Needs your action" comes first for internal roles.
- Long public pages (Privacy Notice, About, post, event and open position details) use `layouts/docs.php` *(update, Nov 1 2026, decided by the project owner)*: full width and anchored left, a sticky "On this page" column of about 260px, and text of about 80ch starting at the left of the main area. Their context card sits at the right from 1600px up.
- Cards for browsing, tables for managing, plain text for reading *(update, Nov 1 2026, decided by the project owner)*. Public lists use `.card--link`, where the whole card is one link. Grids: 3 per row at 1200px up, 2 from 768px, 1 below, gap 24px; officers 4, 3, 2, 1. Forms are one area with headed groups. Detail pages are main content (`.plain-section`) plus one side summary card. Admin lists stay tables.
- Glass (frosted, semi-transparent surfaces with backdrop blur) is allowed ONLY on: the public header when scrolled, the home hero panel, the login/register/track-status card, modal and popover backdrops, and the dashboard topbar when content scrolls under it. Never on tables, forms' input areas, reading text, welfare screens, or the ID/certificate. *(update, Nov 1 2026, decided by the project owner)* Use the `.glass` and `.glass--dark` classes and the `--glass-*` tokens. Text on glass must pass 4.5:1 against the lightest and darkest thing that can sit behind it. At most 2 blurred surfaces show at once, and blur is never animated.
- Pages are fluid and full width *(update, Nov 1 2026, decided by the project owner)*. There is no centered page container. Every page uses the `--gutter` token, `clamp(16px, 4vw, 72px)`. Only text (about 80ch) and inputs (`.field`, about 720px) are capped. Card grids: 1 column, then 2 at 640px, 3 at 1024px, 4 at 1440px, 5 at 1920px; officers auto-fill at 220px. Forms sit on the left with a `form_side` panel filling the rest. Detail pages have a sticky column of about 340px on the right. This replaces the grid sizes in the "cards for browsing" rule above.
- Public header and home band: dark ink, with a faint line grid on the home band only.
- Real organization details: the actual AGILE logo, "Academic Guild of IT Learners and Experts · PUP Open University System", real committee names (MSW Committee, Execomm, The Source Code), "AY 2026–2027, 1st Semester".
- Philippine formats: "October 31, 2026", "2:30 PM", Asia/Manila time. Numbers in tables use tabular figures.
- Motion is allowed on the public home page within a budget *(P7, then made calmer the same day)*: one entrance per section, never two at once in view (fade-up of 12 to 16px), count-ups once, one ambient animation (the hero's grid), one soft glow, and a hover spotlight on the committees. Nothing else moves by itself: no ticker, carousel or canvas. Elsewhere keep motion to short fades. Always honor prefers-reduced-motion. *(update, Nov 1 2026, decided by the project owner)*
- Photos: real AGILE event photos when available (placeholders marked `TODO: real photo`), never stock 3D illustrations.

**Avoid (these make a site look AI-made)**
- Gradients of any kind (backgrounds, buttons, text), glowing shadows. *Exception, decided by the project owner (Nov 1, 2026):* the soft maroon and gold glow shapes behind the glass panels on the home hero, login, registration and Track Status. *P7 adds, on the home page only:* the committee spotlight.
- Emoji anywhere in the UI or emails. "Welcome back! 👋" style greetings.
- Inter / system-font-only look; gradient or oversized hero headlines.
- Purple/indigo/blue SaaS palettes.
- Large rounded corners (12px+, status pills excepted).
- Fake numbers: any figure not from real data, and any trend ("+12% from last month") not computed against a real previous period.
- An icon on every heading; cartoon empty-state illustrations.
- Hover effects that scale, lift or bounce. Animations beyond a short fade.
- Everything centered. The same hero + 3 feature cards on every page.
- Marketing filler: "Empowering students", "Seamless", "Unlock your potential", "All-in-one platform", "Streamline your workflow", "Get started today!", "Oops!".

*Exception, decided by the project owner (Oct 31, 2026):* the robot on the login page stays, with its own rounded shapes and offset shadows.

*Exception, decided by the project owner (Nov 23, 2026):* the dashboard's notice bar (eligibility notice and new notifications) rolls upward one line at a time. It holds on hover or focus, holds still with one line, and shows every line at once with reduced motion. The glass everywhere and the navy-to-maroon gradients tried the same day were removed again to follow this section.

**Copy voice**
Plain, polite, specific campus English, written as AGILE talking to a BSIT-OU student. Buttons say exactly what happens ("Submit application", "Mark as verified", "Send to MSW Head for approval"). Page titles say what the page is ("Applications for review", not "Dashboard Overview"). Empty states say what will appear and what to do ("No open positions this semester. Watch the Announcements page for the next recruitment."). Errors say what went wrong and how to fix it, no "Oops".

**Checklist (every screen)**
1. One clear primary action. 2. Title names the page's real job. 3. No item from the Avoid list. 4. Only the self-hosted IBM Plex files from /assets/fonts. 5. Codes in mono, numbers aligned. 6. Real AGILE terms and PH date format. 7. Works at 360px and 1366px. 8. Keyboard focus visible.

---

## §13 Test cases to satisfy (from SAD §5.2)

TC-01 home renders mobile/desktop · TC-02 track valid code+email · TC-03 track wrong email → generic error + rate limit · TC-04 submit application → Submitted + code + email queued · TC-05 .exe or 10 MB upload rejected · TC-06 MSW Member cannot approve (button hidden, direct POST denied + logged) · TC-07 MSW Head approves → member, member no., ID, certificate · TC-08 role expires → Active General Member, ID updated, notice · TC-09 HR request → MSW Head notified · TC-10 Committee Head cannot create vacancy · TC-11 interview scheduled → invite + 24 h reminder · TC-12 appointment saved only after MSW Head approval, slots updated · TC-13 welfare case in all 7 categories · TC-14 harassment case → urgent alert to MSW Head + President · TC-15 MSW Deputy/Execomm/Committee Head opening case URL → denied + logged · TC-16 President views case + adds note, cannot close/reassign · TC-17 closed case cannot be reopened · TC-18 closure locks attachments, disposition saved, student notified · TC-19 5 wrong passwords → locked 15 min · TC-20 Execomm dashboard read-only · TC-21 missing CSRF → 419 · TC-22 SQL/HTML injection in search is harmless · TC-23 content not public until approved · TC-24 email failure retried up to 3 · TC-25 new term lists only covered officers · TC-26 failed subject → flagged + notified · TC-27 confirm ineligible → role expires, stays member · TC-28 AI suggestion not saved until confirmed · TC-29 *(added)* guest submits a welfare concern after email code verification → case code shown + WELFARE_RECEIVED emailed · TC-30 *(added)* guest tracks the case with code + email and sees only status and student-visible updates · TC-31 *(added)* applicant applies without an account (email code → wizard → tracking code) and revises via the emailed link; when the MSW Head approves, a member row with a member number and no users row is created, and APP_APPROVED has the /register instructions · TC-32 *(replaced, UPDATE R3)* /register with that student number → code to the application email → password + consent → account pending under BR-21, and once the MSW Head approves it they log in as General Member with the profile; a random, unapproved or already-registered student number gets the same generic message with no code and no account; the 6th try in 15 minutes is blocked; registering twice is impossible · TC-33 *(added, UPDATE R2)* approving a membership application creates the ID and certificate as pending, the new member sees the "being reviewed" message and no download button, and the QR shows "Not yet issued"; once the MSW Head approves (single or "Approve all pending"), the PDFs show the approver and date, the member gets one CREDENTIAL_READY email, downloads appear and the QR shows "Valid"; returning requires remarks, and Regenerate creates a new pending file; role expiry revokes the old ID at once and the new one waits; an MSW Member gets 403 and an audit row on the screen and on a direct POST · TC-34 *(added, UPDATE R4)* a general membership application can only be accepted by the MSW Head, and a committee head or msw_member gets 403 on a direct POST; an application to the Events committee can only be accepted by the Events committee head, and the Academic committee head gets 403 and an audit row; a committee with no head, or a head applying to their own committee, falls back to the MSW Head; the decider is notified when the application is ready; after acceptance and the MSW Head's approval of the appointment, the person holds the position and `slots_filled` went up · TC-35 *(added, UPDATE R4)* /register gives a pending account, login shows the waiting message, the MSW Head approves, ACCOUNT_APPROVED is in storage/mail and login works; rejecting without remarks is refused; a rejected person cannot log in and may register again with the same student number.
