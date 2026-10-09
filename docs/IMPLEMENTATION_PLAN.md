# AGILE OUS — Full-Stack Implementation Plan

**Goal:** A fully functional, secure PHP/MySQL web-based management system. NOT a prototype.

## Phases
1. Finalize midterm proposal, SRS, bylaws reconciliation, role matrix, ERD and privacy review.
2. Configure PHP 8+, Composer, MySQL, migrations, synthetic seed data, server-side authentication, CSRF, RBAC and tests.
3. Implement public portal, registration wizard, application tracking, recruitment HR requests, vacancies, evaluations, interviews and approval workflow.
4. Implement membership records, profiles, optional accounts, digital IDs, certificates and membership history.
5. Implement restricted Student Welfare case submission, case assignments, referrals, attachments, follow-ups and closure.
6. Implement branded HTML email templates, server-side Gmail OAuth sending, outbox/retries, notification and audit logs.
7. Implement CMS, news/events/publications, editorial approvals and The Source Code.
8. Implement semestral academic eligibility cycle, secure grade upload, rule-based screening, human review and authorized role transitions; optional AI/OCR assistance.
9. Add reporting, selective caching, backups, hardening, accessibility and end-to-end tests.

## Completion requirements
Every feature must persist to MySQL, enforce permissions server-side and pass tests; no simulated login, mailto-only sending, or localStorage-only database. External credentials and institutional authorizations are required before activating live integrations or real-student data.

## Pending policy decisions
Confirm final AGILE bylaws, whether failing-grade criteria include all historical semesters, how W/D/INC/retakes are handled, and authorized role-removal process. Semestral check frequency and General Member exemption are confirmed working requirements.
