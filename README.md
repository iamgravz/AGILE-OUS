# AGILE OUS — Membership & Student Welfare Management System

**Status:** Full-stack development / documentation foundation. **Not yet production-ready.**  
**Target:** Secure, fully working PHP 8.2+ and MySQL application (not a static prototype).

## Purpose
Integrated web-based system for AGILE OUS, a BSIT student organization at PUP Open University System. Public users see information, vacancies, membership application and welfare intake. Authorized staff handle recruitment, approvals, memberships, welfare cases, communications, academic eligibility, reporting and public content.

## Required modules
- Public portal: mission/vision, events, news, announcements, publications, vacancies and safe tracking.
- Membership: role-specific application, evaluation, approval, member profiles, digital ID/certificates, history.
- Recruitment: HR requests, vacancy capacity, interviews, evaluations, document verification and role assignments.
- Student Welfare: approved categories, confidential cases, priority, notes, attachments, referral, follow-ups and closure.
- Semester-based eligibility checks for officers, committee heads, deputies, committee members and The Source Code; exclude General Members. Human review required; role transitions preserve eligible general membership.
- MSW-branded email center, Gmail server-side integration, queue/retries, CMS and notifications.
- Least-privilege RBAC, President read-only dashboard, audit records, secure storage, backups and selective caching.

## Start developing with Codex
1. Read [AGENTS.md](AGENTS.md), the [implementation plan](docs/IMPLEMENTATION_PLAN.md), and the [contribution policy](CONTRIBUTING.md).
2. Work in a feature branch, produce tested PHP/MySQL services, migrations and secure access controls.
3. Open a PR using the template, with test evidence and no sensitive or real student data.

## Quality and security
GitHub Actions will lint PHP when code exists and check basic repository hygiene. **It does not yet prove a functional MySQL system.** The owner should enforce PR review, required CI and branch protection in Repository Settings. See [SECURITY.md](SECURITY.md).

Never upload real grades, student information, welfare narratives, CORs, Gmail tokens, or keys to this public repository.
