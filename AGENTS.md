# AGILE OUS — Codex Development Contract

## Required deliverable
Build a **fully working, integrated, tested, database-driven web-based system**, not an HTML prototype, static website, mockup, or localStorage simulation. Keep every existing agreed feature; no feature removal without approval. Implement incrementally, with each finished module functional end to end.

## Stack
PHP 8+, MySQL, HTML5, CSS3, vanilla JavaScript, Composer; modular monolith. Gmail API/OAuth only with securely configured credentials. Use synthetic data for tests. No secrets or real student records in Git.

## Functional modules to preserve and implement
1. Public portal: mission/vision, events, news, publications, announcements, vacancies, application and welfare submission, secure status tracking.
2. Membership: conditional application wizard, evaluation, approvals, records/history, optional member accounts, profile, digital ID, certificates, expiration reminders.
3. Recruitment: HR requests, approved openings/capacity, screening, interviews, evaluations, academic documents, decisions and assignments.
4. Welfare: eight approved categories, confidential case files, priority, assignments, notes, referrals, follow-ups, resolution and closure.
5. Administration: login/logout, role- and record-based permissions, President read-only oversight, MSW Head operational control, MSW members CRUD excluding delete and final decisions, reports and audit logs.
6. Communications: branded AGILE maroon/gold email templates with header/footer, drafts, queued Gmail sending, retries and logs.
7. CMS: editorial drafting, review, publishing and The Source Code content.
8. Semestral academic eligibility: check officers, committee heads/deputies/members and The Source Code personnel every semester; exclude General Members; human-verify failing grades; preserve General Membership after authorized role transition.
9. Automation: reminders, workflows, optional AI document extraction (advisory only), selective caching, backup and testing.

## Critical constraints
- Enforce all authorization in PHP per action and record; deny by default.
- Never automatically disqualify/demote a student based on AI/OCR. Reconcile draft bylaws with final approved policy first (including W/D, INC, retakes, historic scope).
- Confidential academic and welfare attachments outside public web root; access only by authorized reviewers.
- Use MySQL transactions, unique constraints, prepared queries, secure sessions, CSRF, password hashing, and audit logs.
- Final acceptance requires real persisted records, authenticated roles, functional workflows, secure file handling and automated tests. A working HTML-only demo is not completion.
- Do not deploy or send real emails without explicit authorization.

## Workflow
Inspect repo and documents; design ERD and permission matrix; implement secure database/auth foundation; then build modules in tested increments. Report what works and what is still incomplete after each increment. Preserve prior UI/assets when added.
