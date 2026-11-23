# Security and privacy review

Step 22, November 1, 2026. Reviewed against `docs/PROJECT_CONTEXT.md` §6, §7, §11, §13 and the OWASP Top 10 (2021).

**Result:** 8 High or Medium findings, all fixed. 12 Low findings: 4 fixed, 8 open and accepted, with the reason beside each. One of the 8, the retention schedule (L-05), needs a decision from AGILE.

## Findings

Severity: **High** = could expose confidential data or accounts as things stand or in a documented setup. **Medium** = weakens a §11 control, or breaks it in a likely situation. **Low** = hardening, or a risk that needs unusual access.

| ID | Severity | Location | Issue | Fix | Status |
|---|---|---|---|---|---|
| H-01 | High | Project root (no `.htaccess`); `docs/scheduling.md:67` and `README.md:50` use `C:\xampp\htdocs\agile` | XAMPP's default site serves everything in `htdocs`, even with the virtual host in the README. `http://localhost/agile/.env` would give out `ENCRYPTION_KEY` and the SMTP password, and `storage/` would give out backups and uploads. | New root `.htaccess` denies everything. `public/.htaccess` opens that one folder. | Fixed. **Not tested on Apache** (see Testing) |
| M-01 | Medium | `app/Middleware/AuthMiddleware.php` | Idle timeout and account status were only checked on routes with a login check. Public routes such as `/apply/{id}/step/{step}` also act for the logged-in user, so an idle or disabled session could still read and edit that person's applications. | New `app/Services/SessionGuard.php` runs on every request from `public/index.php`. | Fixed |
| M-02 | Medium | `app/Services/AuthService.php`, `ProfileService.php` | Changing, resetting or force-resetting a password left other logins running, so Force password reset did not remove someone who had taken over an account. | The session keeps a fingerprint of the password hash, and `SessionGuard` ends it when the hash changes. The login that changed the password continues. | Fixed |
| M-03 | Medium | `app/Services/WelfareService.php`, `referrals.referred_to`, `welfare_attachments.original_name` | Two welfare fields were plain text, against §11. A file name is written by the person with the concern and can state it, and a referral can name a person. | Both are now encrypted. `database/migrations/2026_11_01_encrypt_welfare_names.php` encrypts existing rows and is safe to run again. | Fixed |
| M-04 | Medium | `config/routes.php:252`, `AuthService::loginAsSeedUser`, `app/Core/ErrorHandler.php:217` | `.env.example` starts with `APP_ENV=local` and `APP_DEBUG=true`. A server left that way would offer passwordless seeded login and full stack traces to anyone. | Seeded login also requires a request from this computer (`dev_tools_allowed()`). The debug page never shows in production. `.env.example` points to the checklist below. | Fixed |
| M-05 | Medium | `AuthService::attempt` | Login limits were per IP and email, so one address could try a common password on every account. | Added a limit of 50 failed logins per IP in 15 minutes, across all emails. | Fixed |
| M-06 | Medium | `public/index.php` | The Secure cookie flag depended only on this server seeing HTTPS, so a host that ends HTTPS at a proxy would send the session cookie without it. No HSTS header. | An `https://` `APP_URL` also sets Secure. HSTS is sent over HTTPS. | Fixed |
| M-07 | Medium | `app/Views/portal/privacy.php`, `eligibility/mine.php`, `app/Services/Ai/ClaudeExtractor.php` | With `AI_PROVIDER` on, officers' grade documents go to an outside company, but the notice said data leaves AGILE only by agreement or for a referral (RA 10173, transparency). | The notice says so, and the submit page warns when the feature is on. | Fixed |
| L-01 | Low | `public/index.php` | No `Permissions-Policy` header. | Added. | Fixed |
| L-02 | Low | `app/Core/Session.php` | Cache headers for personal pages depended on `php.ini`. | Set in code with `session_cache_limiter('nocache')`. | Fixed |
| L-03 | Low | Settings help text | Showed the internal code "BR-17". Found in the P2 pass, listed for completeness. | Removed. | Fixed |
| L-04 | Low | `app/Views/welfare/cases/_referrals.php:22` | Help text said the field was not encrypted. | Reworded after M-03. | Fixed |
| L-05 | Low, **needs a decision** | Whole system; `privacy.php:42` | Nothing deletes old records automatically, though the notice promises deletion when data is no longer needed (RA 10173, storage limitation). Rated Low because it is not a way in, but it is a compliance gap. How long to keep records is AGILE's decision, so no period was guessed. | Periods proposed under Retention. Then it can be a scheduled job. | Open |
| L-06 | Low | `/reset-password`, `/account/setup`, `/apply/continue` | Tokens appear in URLs, so they can reach server logs. They are 256-bit, stored only as hashes, single use and expiring, and `Referrer-Policy` keeps them from other sites. POSTs have no rate limit, because guessing is not practical. | None needed. | Accepted |
| L-07 | Low | `guest_verifications.code_hash` | A 6-digit code is hashed without salt, so someone with database access could reverse it. It expires in 15 minutes and allows 5 tries. | None needed. | Accepted |
| L-08 | Low | `notifications.payload_json` | A one-time link or code is readable in the database until the email is sent, then erased. | Needed so the queue can send it. | Accepted |
| L-09 | Low | `storage/backups` | Backups are not encrypted. Welfare text inside stays encrypted, but other personal data does not. They stay on the same computer. | Keep the folder private and copy backups to protected storage. See `docs/restore.md`. | Accepted |
| L-10 | Low | `audit_logs` | A failed login records the email as typed. People sometimes type a password there by mistake. Only `audit.view` can read it. | Needed to investigate attacks. | Accepted |
| L-11 | Low | Execomm and President dashboards | With few cases, a welfare count by category could hint at who is involved. Counts only, no names. | Required by §6. Be careful when sharing screenshots. | Accepted |
| L-12 | Low | `rate_limits`, `guest_verifications`, `user_tokens` | Expired rows are not removed. | Do it with the L-05 job. | Accepted |

**Also check on your side:** the SMTP password is in `.env`, which is ignored by git, but it should still be changed as noted before.

## What was checked and found clean

| Check | Result |
|---|---|
| SQL built from strings | Every variable in SQL is placeholders, a fixed fragment, or an integer. Sorting is whitelisted: `MembershipApplicationRepository:250`, `ContentPostRepository:35-49`, `AuditLogRepository:63`, `BaseRepository::orderClause`. Emulated prepares are off (`Database.php`). |
| Unescaped output | Raw output is limited to: purified CMS HTML, set at `CmsService:104` and shown at `cms/_article.php:41`; `emails/admin/draft_form.php:32`, cleaned on output; the email wrapper in `emails/layout.php`, whose values `TemplateRenderer` escapes; page sections and helpers that escape their values. `applications.js:25` only inserts this site's own escaped HTML. |
| CSRF | `Router::add` attaches it to every POST route, so one cannot be declared without it. `tests/SecurityReviewTest.php` now proves it for all routes. |
| Missing permission checks | Every route was read. Every public service method that writes was listed by script and read. Those without `authorize()` are scheduled jobs, internal helpers called after a check, or flows proven by a token or code. A new test fails if a route has no login check and is not on the public list. |
| IDOR | All child records are checked against their parent: document (`MembershipService:678`, `:347`), interview (`InterviewService::pendingInterview`), referral, follow-up and attachment (`WelfareService`), notification (`NotificationRepository:106`, by `user_id`). Files: `FileAccessPolicy` checks owner or permission per type. Applications: `MembershipService::isOwner`. Eligibility: `EligibilityService::openDocument`. Credentials: owner, approved, not revoked. |
| Welfare data | Encryption and decryption happen only in `WelfareService` (the migration above is the one exception, to encrypt old rows). Emails and notifications carry the case code, status and due date only. History and audit rows hold no case text. |
| Uploads | All go through `FileStorageService::validate` (extension, finfo MIME, size), get random names and live outside `public/`. They leave only through `FileController` and `MediaController`, which reaches only `media/` by a 128-bit id. `nosniff` is on every response. |
| Session | HttpOnly, SameSite=Lax, strict mode, cookies only, new ID at login and at every guest verification. |
| Rate limits | Login, forgot password, track, verify page, guest codes and submissions, registration, edit links, password change. Each code allows 5 tries. |
| Errors | With the database down: `APP_ENV=production` shows the plain page and no details, with `APP_DEBUG` on or off. `zend.exception_ignore_args` keeps arguments out of logs. |
| CSP | `default-src 'self'` with no inline code, confirmed in a real browser with no CSP errors on every page in the suite. Previews set their own stricter policy. |
| Secrets | `.env`, `storage/*` and `vendor/` are ignored by git. The only password in the repo is the seeded one, and seeded users are not seeded in production. |
| Other OWASP items | Open redirect: `intended_url` is a local path only. SSRF: the AI endpoint comes from `.env`, and Dompdf has remote files off. CSV injection: `ReportService::safeCell`. Components: `composer audit` found no advisories. Timing: the dummy hash takes as long as a real one (45 ms each). Host header: links use `APP_URL`. |

A lead that turned out fine: notifications do not poll the server, so they cannot keep a session alive past the idle timeout.

## Testing

| What | How | Result |
|---|---|---|
| Existing and new tests | PHPUnit, HTTP and browser suites on a scratch database. See `docs/PROGRESS.md`. | Pass |
| Route rules, session rules, login limit, seeded login, encryption, migration | `tests/SecurityReviewTest.php` | Pass |
| Other logins ending, disabled accounts on public routes, headers, encrypted file names | HTTP suite | Pass |
| Error page with the database down | Three `APP_ENV` and `APP_DEBUG` combinations | Pass |
| H-01 `.htaccess` | **Not run.** This computer's Laragon has no Apache program. Check it on your XAMPP (below). | Open test |

## Production checklist

1. Point the site at `public/` only, then confirm `http://your-site/agile/.env` gives 403 or 404.
2. `.env`: `APP_ENV=production`, `APP_DEBUG=false`, an `https://` `APP_URL`, `MAIL_DRIVER=smtp`.
3. Use a database user with only the rights it needs on `agile_ams`, not `root`, with a password.
4. Keep a separate copy of `ENCRYPTION_KEY`. Without it, welfare text and files cannot be read. Never change it on a live system.
5. Run behind HTTPS. If a proxy sits in front, the rate limits see the proxy's address, so limits per IP are shared until the host passes the real one to `REMOTE_ADDR`.
6. Make sure no `@agile.test` seeded users exist. Do not run setup with a non-production `APP_ENV` on the live database.
7. Copy backups off the computer to protected storage, along with `storage/uploads` and `storage/credentials`.

## Privacy (RA 10173, Data Privacy Act of 2012)

### What each module stores and who can see it

Roles follow §6. People without an account see their own records through a code or link. The MSW Committee Head can see everything except where noted (BR-16).

| Module | Personal data | Who can see it |
|---|---|---|
| **Accounts** | Name, email, student number, contact number, photo, password hash, last login | The person. MSW Head (`admin.users`). MSW Member sees member records (`member.view`). |
| **Applications** | Name, email, student number, contact number, year level, answers, documents, status history | The applicant. MSW Member and Head. Committee heads read approved applications, those they evaluated, and position applications they can evaluate; they open documents only for their own committee. |
| **Evaluations and interviews** | Scores, remarks, schedule, result, notes | MSW Member and Head. Committee heads, as evaluators. The applicant sees status, not scores. |
| **Member records** | Member number, type, position, role history | The person. MSW Member and Head. |
| **Digital ID and certificate** | Name, member number, role, serial number | The person, once approved. MSW Head. Anyone with the QR link sees name, member number, role and validity only. |
| **Welfare cases** | Subject, description, notes, referrals, follow-ups, resolution, file names and files, all encrypted. Guests also give a name, email and optional student number, not encrypted, because tracking matches the email. Category, priority and status are not encrypted, so the queue can filter them. | **Only the MSW Head and President** (BR-06). The submitter sees status and student-visible updates. Execomm sees counts only. The technical maintainer cannot read the text. |
| **Eligibility** | Grades and enrollment documents, results, remarks | The officer. The MSW Head, or the President for the MSW Head's own screening (BR-10). |
| **AI suggestions** (only if turned on) | Values read from eligibility documents | The reviewer. The documents go to the outside AI provider set in `.env`. |
| **Notifications and email** | Email address, name, message values | The recipient. Failed sends show on System health as counts only. |
| **Audit log** | User, action, IP address, browser, details | MSW Head (`audit.view`). |
| **Reports** | Membership, recruitment and eligibility lists. Welfare statistics are counts only. | MSW Head (`report.export`). Every export is logged. |

### Retention

What the system does today:

- One-time links and codes are erased from emails once sent, and expire in 15 minutes to 72 hours.
- Database backups are removed after 30 days, and job run history after 90 days.
- Closed welfare attachments follow the disposition the MSW Head records (BR-15). Deleted means the file is gone.
- **Everything else is kept until someone removes it.**

Proposed schedule, for AGILE to decide (L-05). These are suggestions, not legal requirements:

| Data | Suggested period |
|---|---|
| Rejected or withdrawn applications and their documents | 1 year after the decision |
| Abandoned drafts | 90 days |
| Member records | While a member, plus 3 years |
| Eligibility documents | 1 year after the term |
| Closed welfare cases | 5 years after closure, then delete or anonymize |
| Audit logs | 3 years |
| Expired codes, tokens and rate-limit rows | 30 days |

### Consent points

| Where | What the person does | How it is recorded |
|---|---|---|
| Application start (`membership/apply/start.php`) | Ticks consent before the email code | `audit_logs`, `applicant.email_verified` |
| Application submit (`membership/apply/step6.php`) | Ticks consent to submit | `membership_applications.consent_at` |
| Guest welfare form (`welfare/guest/start.php`) | Ticks consent before the email code | Required by `GuestWelfareController` |
| Member welfare form (`welfare/_concern_form.php`) | Ticks consent to submit | Required on submit |
| Registration (`auth/register_password.php`) | Ticks consent to create the account | `audit_logs`, `member.registered` |
| Eligibility documents (`eligibility/mine.php`) | Ticks consent to submit, with an AI warning if the feature is on | Required by `EligibilityController` |

Every one links to the Privacy Notice at `/privacy`, which lists the rights under RA 10173 and the right to complain to the National Privacy Commission.

### Rights without a screen yet

People can read and correct some of their own details under My Account, but these are handled by request to the MSW Committee: objecting, withdrawing consent, erasure and data portability. The notice gives the contact. AGILE should name who answers these and how quickly. Also consider whether AGILE should name a Data Protection Officer, which RA 10173 expects of organizations that process personal data.
