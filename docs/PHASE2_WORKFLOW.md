# Phase 2 — Position-Specific Registration and Application Review

## Operational behavior
The public application form supports six AGILE registration categories (General Member; Committee Member; Deputy Committee Head; Committee Head; Executive Officer; The Source Code). Each displays conditional questions, and the backend validates the selected category's required fields. Answers are stored in MySQL in `application_answers`, not browser localStorage.

The Source Code specific selectable positions include Managing Editor, News Editor, Feature Editor, Writer, Head Cartoonist, Cartoonist, Layout Editor, Head Photojournalist, Photojournalist and Videographer/Editor.

## MSW application assignment
- The MSW Head sees all membership applications and can assign each to an active MSW committee member.
- An MSW Member sees **only assigned applications**, including direct-URL access checks.
- President and technical admin have aggregate read-only reporting but no individual applicant record access by default.
- Only MSW Head can record final verification prerequisites and final decisions.
- MSW Members can screen, progress to interview/verification, and add review notes for assigned applications. They cannot approve, reject, waitlist or delete.

## Status workflow
`submitted → screening → for_interview → for_verification → approved`

Screening/interview/verification may transition to rejected or waitlisted only by the MSW Head. Approval requires *both* interview and document verification flags. Every transition writes a reviewer/date/note history record and audit event in the same database transaction.

## Intended next iteration
This Phase 2 workflow is functional for the specified statuses, but not yet the entire recruitment process. Required future integrations: official HR-request/vacancy capacities; detailed committee-role opening selectors; documented interview schedule and evaluation; actual upload verification evidence; academic eligibility checks under final bylaws; member account creation and notification queue; secure applicant tracking.

## Privacy and use restriction
The public form must use **synthetic data only** until official notice, retention period, processing authority, and secure hosting are approved. The minimal privacy information page currently explains these limitations explicitly.

## Setup
From branch `feat/phase2-role-specific-registration`:
1. Configure ignored `.env` credentials or server environment variables.
2. Run `php scripts/migrate.php` to apply all new migrations.
3. Run `php tests/cli_smoke.php`, `php tests/mysql_integration.php`, and `php tests/workflow_integration.php`.
4. Start `php -S 127.0.0.1:8090 -t public public/router.php`.
5. Use the public application; log in as MSW Head, assign an application to an active MSW Member, verify review transitions.
