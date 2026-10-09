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
- **Use the user's actual 2026-09-24 draft AGILE OUS Constitution & By-Laws as the provisional requirements source**, with Article VI §2 for elected officer candidates and Article III membership classes. Consult `docs/PROVISIONAL_BYLAWS_POLICY.md`. Do not present the draft as ratified or university-approved.
- Draft eligibility flags are **advisory only**. No automatic appointment, rejection, role removal or grade-derived sanctions. Keep `eligibility_policies.is_approved=false` for the seeded draft; consequential workflows require authorized approved policy and manual review.
- Semestral review of committee/deputy/publication personnel and General Member exemption are working operational extensions, not clauses explicitly enacted by Article VI §2; review W/D, INC, retakes, full institutional lookback and due-process criteria before any enforcement.
- Confidential academic and welfare attachments outside public web root; access only by authorized reviewers.
- Use MySQL transactions, unique constraints, prepared queries, secure sessions, CSRF, password hashing, and audit logs.
- Final acceptance requires real persisted records, authenticated roles, functional workflows, secure file handling and automated tests. A working HTML-only demo is not completion.
- Do not deploy or send real emails without explicit authorization.

## Workflow
Inspect repo and documents; design ERD and permission matrix; implement secure database/auth foundation; then build modules in tested increments. Report what works and what is still incomplete after each increment. Preserve prior UI/assets when added.
