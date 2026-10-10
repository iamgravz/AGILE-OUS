# AGILE OUS — Phase 13 Browser UAT, Accessibility and Security Review

**Status: SYNTHETIC CI ONLY; NO-GO for actual student data.** This phase depends on [PR #13](https://github.com/iamgravz/AGILE-OUS/pull/13), Phase 12's isolated HTTPS staging environment. Nothing in this phase provisions a real school-approved staging server or performs human acceptance signoff.

## Objective

Verify that the existing **real HTML/CSS/JavaScript interface** works when exercised in Chromium, rather than relying solely on PHP service/integration tests. Browser testing uses a self-signed **disposable CI certificate** and synthetic registration/welfare records only. This is a baseline, not a substitute for qualified user interviews, manual keyboard/screen-reader testing or an independent penetration test.

## Automated checks

The new `tests/browser/browser_uat.cjs` is guarded by all three conditions:
- `CI=true`
- `RUN_E2E_TESTS=yes`
- `AGILE_UAT_BASE_URL=https://127.0.0.1:8443`

Any other environment refuses execution. The script does not log HTML, student data, identifiers, tracking tokens, screenshot contents or recovery codes. It creates one summary JSON with test counts and boolean pass flags.

| Check | Acceptance criterion |
|---|---|
| Desktop browser (1440×900) | Landing, Privacy, Apply, Vacancies, Updates, Welfare, Welfare Tracking and Login load without horizontal page overflow |
| Mobile (390×844) and small mobile (320×690) | The same eight public pages load with one main landmark and keyboard-focusable navigation, without horizontal overflow |
| Automated accessibility | Run axe-core against WCAG 2 A/AA and WCAG 2.1 AA rules on all 24 viewport/page combinations; **any serious or critical violation fails CI** |
| Role-specific Membership UI | General Member fields initially visible; selecting Committee Member hides/disables irrelevant questions, activates required Committee Member fields |
| Browser registration workflow | Submit a complete synthetic application with privacy consent, receive an `AG-...` reference |
| Welfare intake | Submit a synthetic confidential concern and receive reference and one-time tracking token |
| Public Welfare tracking | Correct token returns **status only**; submitted narrative, email and private token never appear in the tracking result; incorrect token returns HTTP 404 |
| Session security | Browser receives Secure, HttpOnly, SameSite=Lax session cookie; HTTPS HSTS, anti-framing, nosniff and restrictive Permissions-Policy headers present |
| Anonymous authorization | Login redirect for unauthorized staff dashboards; protected PHP, backups and private file routes return HTTP 404 |
| CSRF and login | POST without token returns 419, invalid account cannot log in |

The browser uses `ignoreHTTPSErrors: true` **only because CI uses a temporary self-signed certificate**. The independent preceding Phase 12 curl step still verifies the exact `staging.example.invalid` certificate with an explicitly trusted CI test certificate. **Never use that browser setting to waive TLS verification on a live server.**

## Browser API exposure hardening

The Nginx staging templates now advertise restrictive Permissions-Policy (deny camera, microphone, geolocation, payment, USB) and same-origin opener/resource policies; anti-framing, nosniff and HSTS remain unchanged. These headers are a defense-in-depth baseline, **not a complete Content Security Policy or a full security audit**. Inline JavaScript/CSS and future third-party OAuth integrations require separate CSP threat modeling before deployment.

## Pipeline and evidence

`.github/workflows/staging-infrastructure.yml` still installs protected disposable MySQL+PHP+Nginx staging containers and runs the Phase 12 certificate, migration and role protection tests. After the HTTPS smoke, the pipeline installs pinned direct Playwright and axe-core package versions, installs Chromium, runs the browser suite and validates an aggregate-only report. The report is kept as a **7-day GitHub Actions artifact** without page screenshots, trace recordings or raw response bodies.

GitHub runners discard the synthetic credentials, self-signed keys, database and file volumes at teardown. Browser tests must not be run against a database with real student records. The browser package currently specifies exact direct dependency versions; transitive dependencies should be pinned in an npm lockfile before broader deployment or long-term supply-chain certification.

## Manual human User Acceptance Testing — NOT DONE

The following must be reviewed by **actual authorized representatives** on a **school/AGILE-approved staging host** using only synthetic records. No items are pre-approved:

| Reviewer / device | Required manual observation | Signoff |
|---|---|---|
| Membership applicant, Android phone | Apply for each officer/committee/general/publisher role; errors, role-specific questions, privacy text and attachment status | Pending |
| Membership applicant, desktop keyboard only | Complete application without mouse, correct focus/tab order, clear validation instructions and readable errors | Pending |
| MSW committee member | See only assigned applications/cases; can create/read/update authorized cases but cannot delete/resolve | Pending |
| MSW Head | Review assigned/all cases, human screening/approval, private evidence quarantine and download permission | Pending |
| President | Aggregate dashboard only, without any grade, case narrative, applicant record or control action | Pending |
| Technical administrator | Identity oversight only, no academic/welfare content | Pending |
| Publication editor/committee head | HR request, editorial submission, review workflow and no self-publish privilege | Pending |
| Student using screen reader | NVDA (Windows) or TalkBack (Android), every main form including errors, modal/redirect and tracking | Pending |
| Browser/device coverage | Chrome desktop, Firefox desktop, Safari iOS, Android Chromium; zoom 200% and landscape mobile | Pending |
| Security/privacy reviewer | MFA recovery, CSRF session lifecycle, real ClamAV signatures, authorized privacy and emergency procedures | Pending |

## Issue severity and release decision

- **Critical**: confidential grade/welfare data exposed; unauthorized role change/approval; credentials or private files leaked; requires immediate stop.
- **High**: accessible authentication bypass, broken MFA/CSRF, unsafe upload, or inaccessible essential application/concern form; blocks staging signoff.
- **Medium**: significant keyboard navigation, responsive layout or validation problem; fix and retest before general public invitation.
- **Low**: purely decorative or minor copy issue; track and prioritize for fix.

**No claims of WCAG compliance, penetration-test pass or actual institutional user signoff are made here.** Automated axe rules cannot detect all assistive technology, cognitive accessibility and interaction issues. Users' signed acceptance reports should be stored privately with no grades/welfare narratives in GitHub.

## Dependencies and next release gates

Phase 13 is an additive draft PR dependent on Phase 12. PR #10 and #11 are separate Phase 10 drafts incorporated by Phase 11 PR #12; reconcile branch history before any merge. Until organization policy and Philippine privacy requirements, offsite disaster recovery, managed secrets, real signature-scanning, approved hosting and human UAT/security assessments are complete, **production_release_authorized remains false — NO-GO**.
