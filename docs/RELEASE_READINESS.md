# AGILE OUS — Release Readiness (Development Handoff)

## Current decision
**NO-GO for real student data and production deployment.** These checkboxes are acceptance gates, not claims that tests have passed.

## Functional acceptance
- [ ] Fresh MySQL 8 database runs migrations 001–008 without errors.
- [ ] A role-created test account can authenticate, logout, and re-login.
- [ ] Applicant submission with fictitious data works only with APPLICATIONS_OPEN=true.
- [ ] Duplicate applicant in the same period is blocked, across-period applications follow approved rules.
- [ ] Head assigns term-specific position, schedules interview, records evaluation and decides outcome.
- [ ] Two concurrent approvals into the last slot result in exactly one approval.
- [ ] Reporting totals reflect applications and capacity; CSV fields and escaping are reviewed.
- [ ] Notification draft approval never sends an email without authorized transport integration.
- [ ] Welfare head can assign a test case; welfare member sees only owned/assigned cases.
- [ ] President sees aggregate dashboards but no individual welfare records.
- [ ] Deactivated users lose access immediately at the next authorized request.
- [ ] Authentication/session expiry, lost cookie and CSRF handling tested across browsers.

## Security and privacy gates
- [ ] Independent high-entropy APP_KEY and WELFARE_ENCRYPTION_KEY are in a secure secret store; never committed.
- [ ] TLS, HSTS, secure session cookies, reverse-proxy rate limiting, IP restrictions for admin, and CSP configured.
- [ ] MFA implemented for privileged accounts, password recovery and session revocation validated.
- [ ] Export/read audit trails, retention/deletion workflows, account approval and least-privilege audited.
- [ ] Philippine Data Privacy Act assessment, lawful basis, privacy notice, retention periods, complaint procedure and DPO review approved.
- [ ] Real welfare intake and referral authority established; no cases received without an approved process.
- [ ] Application and database backups encrypted; access is logged; successful restore drill documented.
- [ ] Vulnerability/dependency scan, SQL injection, XSS, CSRF, IDOR, CSRF bypass, path disclosure, and email enumeration tests passed.
- [ ] Email delivery templates/consent and SMTP/Gmail integration security reviewed before enabling any send.
- [ ] Production error handling does not expose database details or secrets.

## Operations and deployment gates
- [ ] CI lint + permissions + migrations + integration smoke tests green on final commit.
- [ ] Deployment pipeline to **staging** reviewed with pinned dependencies and approval gates.
- [ ] Staging end-to-end UAT signed by the AGILE committee; supervisor/organization approves workflow.
- [ ] Rollback migration plan, restore plan, operations owners, logging alerts and incident response tested.
- [ ] Source branch reviewed and merged through PR only after independent code review.
- [ ] Production change request approved before enabling APPLICATIONS_OPEN.
- [ ] 30-day post-launch bug triage and support owner defined.

## Important boundaries
The repository contains an incremental PHP/MySQL implementation, not a certified production system.
The Docker configuration is for local development only. Do not expose the PHP built-in server publicly.
