# AGILE OUS — Security & Release Readiness

**Status:** Internal development. Not authorized for public deployment or real student records.

## What the draft branch implements (PHP + MySQL)
- Staff sessions and basic role checks; role-restricted application review; assignment and status history.
- Public membership application, position-specific answers, HR requests, published vacancies, interview/evaluation and appointment with transactional vacancy capacity checks.
- Confidential welfare case intake, private token status tracking, case assignment, notes, referrals and follow-up scheduling.
- Public CMS with editorial approval; member invitation, optional account, printable certificate and opt-in verification.
- Semestral verification with versioned, explicitly approved policy and human adjudication.
- Protected attachments; email outbox, optional Gmail OAuth worker; notification/reminder scheduler; privacy-gated AI grade extraction adapter.

## Verified in disposable GitHub MySQL 8 CI
- Schema migrations; application submission; synthetic staff authentication and logout.
- Position-specific registration and assigned-staff application permissions.
- Recruitment approval -> vacancy -> interview -> evaluation -> membership appointment.
- Welfare privacy (unassigned staff, President), case transition and public token tracking.
- CMS draft -> review -> authorized publication.
- Semestral check, refusal to screen without approved policy, manual review, transition to General Member, vacancy release.
- Notification creation, public verification private by default.
- Explicit role-change account permission revocation and feature gate (after added tests pass).

See `.github/workflows/mysql-integration.yml`; integration scenarios run only with synthetic data against an isolated CI database. Passing these tests is not a security certification.

## Production blockers: resolve before enabling real-data use
1. **Institutional and governance authorization:** Final AGILE Constitution and By-Laws, legitimate authority for data collection, student privacy notice, case procedures, data retention/erasure, appeal process and emergency response policy.
2. **Grade policy:** The supplied 2026-09-24 draft Article VI §2 calls out 5.0/F, W and D **during a candidate's entire institutional stay**, as well as executive year-level and prescribed full-load requirements. This is now seeded as `DRAFT-2026-09-24-ARTICLE-VI-2` with `is_approved=false` and an advisory preview. Semestral monitoring for the wider committee/publication workforce is a proposed working-system extension. INC, retakes, interpretation and due process remain pending approval. See `docs/PROVISIONAL_BYLAWS_POLICY.md`.
3. **Identity linkage:** A staff officer's real account must be explicitly and securely linked to their member record. The current role transition revokes privileges on a *linked* account within the same DB transaction, but it cannot revoke unknown/unlinked external accounts. No production onboarding until identity mapping and reassignment are verified.
4. **Member credentials:** Add MFA for privileged staff, secure account recovery, session rotation/revocation, account verification and rate limits for public token lookups.
5. **Sensitive files:** Perform malware scanning, immutable retention rules, download abuse testing, protected backups and disaster recovery drills. Do not upload academic/welfare records into this public repository.
6. **Public privacy:** Formalize consent/notice, lawful basis, institutional contact and emergency support routing. Synthetic-only notice must be replaced with approved operational wording.
7. **Email & AI:** Obtain authorized Gmail OAuth credentials; test refresh-token rotation, retries/duplicate delivery and delivery failures. External academic-document AI is disabled without formal privacy approval; measure extraction error rates and human corrections.
8. **Access control:** Thorough negative/record-level tests across every route, document type, file owner and administrative role. Audit appointment and privilege lifecycle; introduce department-specific scoping for other committees.
9. **Automation:** Implement encrypted database backups and recovery, operational alerts, queue dead-letter/reconciliation strategy, controlled scheduler rollout and job observability.
10. **UX and domain model:** Finish application status verification, official registration questionnaires, document corrections, detailed vacancy targeting and capacities, secure dedicated domain/HTTPS, accessibility testing, and mobile usability.

## Default-off feature controls
`MAIL_TRANSPORT=disabled`; `AUTOMATION_ENABLED=false`; `ACADEMIC_ROLE_TRANSITIONS_ENABLED=false`; `AI_EXTERNAL_PROCESSING_APPROVED=false`; `AI_EXTRACT_PROVIDER=disabled`.

**Only a disposable CI test database may enable** `ACADEMIC_ROLE_TRANSITIONS_ENABLED=true` for synthetic academic-role tests. Setting the flag in production requires approved bylaws, due-process policy and human oversight.

## Branch strategy
- Keep PR #1 (governance), PR #2 (foundation), PR #3 (registration), PR #4 (modules/automation) reviewed in dependency order.
- Do not merge PR #4 until CI succeeds with extended tests and the outstanding production blockers are addressed or explicitly scoped as launch blockers.
- No secrets or real student data in issues, commits, PR screenshots or workflow logs.
