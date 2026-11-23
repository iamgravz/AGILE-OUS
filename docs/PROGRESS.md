# Build Progress

## Step 1 of 24: Project skeleton and front controller (Oct 30, 2026)

### What was built
A runnable PHP 8 MVC skeleton with no features yet: Composer autoloading, the §4 folder tree, `.env` loading, a front controller with security headers and secure session cookies, a router (GET/POST, `{id}` params, named routes, nested groups with middleware, 404/405), views with layouts, sections and partials, a global error handler that logs to `storage/logs/app.log`, helpers, error pages, and a temporary home page.

### Files created
- Root: `composer.json`, `composer.lock`, `.gitignore`, `.env.example`, `README.md`
- `public/`: `index.php`, `.htaccess`, `assets/css/app.css`
- `config/`: `app.php`, `routes.php`
- `app/helpers.php`
- `app/Core/`: `Config`, `Request`, `Response`, `Router`, `View`, `Session`, `ErrorHandler`
- `app/Controllers/PortalController.php`
- `app/Views/`: `layouts/public.php`, `portal/home.php`, `errors/403.php`, `404.php`, `405.php`, `419.php`, `500.php`, `debug.php`
- `.gitkeep` in every empty §4 folder

### Additions beyond the step prompt
- `app/Core/ErrorHandler.php` holds the global handler, so `public/index.php` stays short.
- `errors/405.php` and `errors/debug.php` are needed for 405 handling and the APP_DEBUG error page.
- `public/assets/css/app.css` exists because the CSP blocks inline styles. Step 3 replaces it.
- `APP_KEY` is in `.env.example` because §4 lists it. Nothing uses it yet.
- `composer.json` sets `config.platform.php` to 8.2.0, so `composer.lock` also installs on PHP 8.2.

### How to test
```
composer install
copy .env.example .env
php -S localhost:8000 -t public
```
Open http://localhost:8000 and http://localhost:8000/does-not-exist.

### Test results
- Home returns 200, `/does-not-exist` returns 404, and POST to `/` returns 405 with `Allow: GET`.
- All responses from `index.php` carry the CSP, X-Content-Type-Options, X-Frame-Options and Referrer-Policy headers. The session cookie is HttpOnly and SameSite=Lax.
- A forced error shows the detail page with `APP_DEBUG=true` and the friendly page with `APP_DEBUG=false`, with no detail leaked. Both are logged.
- Normal requests write nothing to `app.log`.
- `.env` and `vendor/` are ignored by git.
- A throwaway script confirmed route params, nested group middleware, named routes and `e()`. No PHPUnit tests exist yet.

### Known issues
- Not tested: Apache and `public/.htaccess`, and the Secure cookie flag over HTTPS. Only `php -S` over HTTP was run.
- `php -S` returns its own plain 404 for missing paths that contain a dot, such as `/missing.png`. Apache sends those to the app.
- This machine's `php.ini` keeps sessions in `C:/laragon/tmp`. That folder was missing and has been created. If it is removed, every page returns a 500.
- This machine's `php.ini` sets `curl.cainfo` to `C:\laragon\etc\ssl\cacert.pem`, which does not exist. Nothing uses it yet, but HTTPS calls from PHP curl, such as the Gmail API, will need it fixed.

### Notes for later
- `SESSION_IDLE_MINUTES` is in config, but the idle timeout (§11) is not enforced yet. It belongs with the auth step.
- `upload_max_mb` is read from `.env`, while §7 names the setting `upload.max_mb`. Decide which one wins when the settings table exists.
- The SAD is still v1.2 and lacks BR-17.

## Step 2 of 24: Database schema and seed data (Oct 30, 2026)

### What was built
The full §9 schema (41 tables, 68 foreign keys), a shared PDO layer with transactions, a base repository with whitelisted sorting, seed data, a setup script with `--fresh`, a report that prints the role x permission table and seeded users, and the database reference for the defense.

### Files created
- `database/`: `schema.sql`, `bootstrap.php`, `setup.php`, `seed.php`, `report.php`
- `database/seeds/`: `roles_permissions.php`, `welfare_categories.php`, `committees_positions.php`, `email_templates.php`, `settings.php`, `application_questions.php`, `seed_users.php`
- `app/Core/Database.php`, `app/Models/BaseRepository.php`
- `config/permissions.php`, `docs/database.md`

### Files changed
- `config/app.php` (database settings), `README.md` (database section)
- Removed `database/seeds/.gitkeep`

### How to test
1. Put your MySQL password in `DB_PASS` in `.env`.
2. Run `php database/setup.php --fresh`.
3. Run `php database/report.php` to print the report again.

### Test results
These ran on a temporary MySQL 8.0.45 server, not the real one, because the root password is not available to the assistant:
- `setup.php --fresh` runs without errors. Running without `--fresh` afterward changes nothing. `--fresh` is refused in production.
- `role_permissions` matches the §6 table parsed directly from `PROJECT_CONTEXT.md`: 30 codes, 60 rows.
- Tables and columns match §9. All 31 additions are in `docs/database.md`.
- Foreign key rules and indexes were checked through `information_schema`.
- `Database::transaction` and `BaseRepository` were exercised by a throwaway script, including rejected `orderBy` values. No PHPUnit tests exist yet.

### Decisions made
- **Seeded guest account:** "one per role" plus a deputy only reaches 9 users if the guest role is counted, so `guest@agile.test` exists but is `disabled`, because §5 says guests don't log in.
- **`welfare_case_history.actor_id` is nullable:** otherwise guest events can't be recorded. No `actor_type` column was added; a NULL actor means a guest or the system.
- **Four extra email templates:** see `docs/database.md`.
- **Seeded users are skipped in production.**
- **Seeds are split:** `database/seed.php` loads `database/seeds/*.php`, so seeded data stays in that folder as §2 rule 8 requires.

### Known issues
- Resolved in step 4: the real MySQL server now has the schema. `database/report.php` shows 41 tables and 68 foreign keys there.
- phpMyAdmin was not found at Laragon's usual location, `C:\laragon\etc\apps\phpMyAdmin`.
- `org.mission` and `org.vision` are seeded wording.

### Notes for later
- Still undecided: encryption coverage of welfare data (`subject`, remarks, attachment files), who decides the MSW Head's own eligibility, and an account permission so applicants can edit their profile (FR-MEM-11). The schema and §6 mapping follow `PROJECT_CONTEXT.md` as written until these are decided.
- A later step should fill in `users.contact_no` handling: §9 makes it required, so registration must collect it.

## Step 3 of 24: Design system and page layouts (Oct 30, 2026)

### What was built
The maroon, gold and white design system in mobile-first CSS, the public, dashboard and auth layouts, restyled error pages, vanilla JS behavior, an icon sprite, a placeholder logo, `status_badge()`, and a style guide available only when `APP_ENV=local`.

### Files created
- `public/assets/`: `js/app.js`, `css/styleguide.css`, `img/icons.svg`, `img/logo.svg`
- `config/`: `menu.php`, `statuses.php`
- `app/Controllers/StyleguideController.php`
- `app/Views/layouts/`: `dashboard.php`, `auth.php`
- `app/Views/partials/`: `head`, `alert`, `flash`, `overlays`, `breadcrumbs`, `notification_dropdown`
- `app/Views/styleguide/`: `index.php`, `auth.php`

### Files changed
- `public/assets/css/app.css` (replaced), `app/Views/layouts/public.php`, `app/Views/errors/403, 404, 405, 419, 500`
- `app/helpers.php` (`status_badge()`, `icon()`, `sidebar_menu()`, `is_current_path()`)
- `config/routes.php`, `config/app.php`, `.env.example`, `README.md`
- Removed `.gitkeep` from `app/Views/partials`, `public/assets/img` and `public/assets/js`

### How to test
```
php -S localhost:8000 -t public
```
Open http://localhost:8000/styleguide, `/styleguide/dashboard` and `/styleguide/auth`. No database is needed.

### Test results
Automated with a throwaway script driving headless Chrome over the DevTools protocol. No PHPUnit tests exist yet.
- 429 checks across the three style guide pages, home, 404, 403, 405, 419 and 500 at 360, 768 and 1366px. The final full run passed 428; the other check, the image thumbnail, failed because of a bad test PNG and passed with a valid one in a rerun of the 51 interactive checks. No code changed between those runs.
- No horizontal scroll and no element past the screen edge, at any width.
- Tab reaches every interactive element, and each one shows a focus ring.
- All requests stay on the local server, with no CSP or console errors and no failed assets.
- Measured text contrast is at least 4.5:1, excluding disabled controls.
- Mobile nav, sidebar, dropdowns, confirm modal, double-submit guard, flash, toast, tabs, fetch header, file preview, reduced motion and no-JavaScript fallbacks all work.
- `config/statuses.php` holds all 32 statuses in §8, and all are on the style guide. All 22 sidebar items use real permission codes.
- `/styleguide` returns 200 with `APP_ENV=local` and 404 with `APP_ENV=production`.

### Decisions made
- **File preview uses a canvas:** the CSP blocks `blob:` image URLs, and §11 says self only, so the CSP stays unchanged.
- **Dashboard item has no permission:** no §6 code covers every signed-in role, so a null `permission` in `config/menu.php` means any signed-in user.
- **Fixed sidebar from 1200px:** below that it slides in, so tablets keep the full width for tables.
- **Contact email comes from `ORG_CONTACT_EMAIL`:** error pages must render when the database is down, so the footer does not read the settings table.

### Known issues
- Tested only in headless Chrome. Not tested in Firefox, Safari or a real phone, with a screen reader, or under Apache.
- Most nav, sidebar and footer links return 404 until their steps exist. The paths in `config/menu.php` are placeholders until then.
- The sidebar shows every item until step 6 filters `sidebar_menu()`.
- `contact@agile.test` is a placeholder. The official logo replaced the placeholder after step 7: `public/assets/img/logo.png` (256px, used by the site) and `logo-large.png` (full size).
- `/logout` does not exist yet, and its form in `layouts/dashboard.php` has no CSRF field.

### Notes for later
- The CSRF step should store the token in session key `_csrf_token`, which `partials/head.php` reads for the meta tag, and add `csrf_field()` to the logout form.
- The auth step should pass `currentUser` and `notifications` to the dashboard layout.
- Forms that start a download should use `data-no-guard`, or the double-submit guard leaves their buttons locked.
- `org.contact_email` in the settings table and `ORG_CONTACT_EMAIL` in `.env` can drift apart. Decide on one source when the settings page is built.

## Step 4 of 24: Email and notification engine (Oct 30, 2026)

### What was built
One service for email and in-app notifications, a queue job with three attempts and a lock, log and SMTP mail drivers, branded email rendering, a shared job bootstrap, and a CLI test script.

### Files created
- `app/Services/Mail/`: `MailerInterface`, `LogMailer`, `SmtpMailer`, `MailerFactory`
- `app/Services/`: `TemplateRenderer`, `NotificationService`, `NotificationStatus`, `EmailQueueService`
- `app/Models/`: `NotificationRepository`, `EmailTemplateRepository`, `UserRepository`
- `app/Views/emails/layout.php`
- `jobs/`: `_bootstrap.php`, `send_email_queue.php`, `test_notify.php`

### Files changed
- `composer.json`, `composer.lock` (`phpmailer/phpmailer`)
- `config/app.php` (mail settings), `.env.example` (`MAIL_LOGO_URL`), `README.md`, `database/seeds/email_templates.php` (comment only)
- Removed `app/Services/.gitkeep`, `jobs/.gitkeep` and the empty `app/Services/Mailer/` folder

### Additions beyond the step prompt
- `EmailQueueService` holds the sending rules, because §2 keeps business rules out of scripts. `NotificationStatus` is the §8 transition map.
- The prompt names `app/Services/Mail/`, while §4 says `Mailer/*`. The prompt was followed.

### How to test
```
php jobs/test_notify.php you@example.com
php jobs/send_email_queue.php
```
Then open the newest `.html` file in `storage/mail/`. Job activity is in `storage/logs/jobs.log`.

### Test results
These ran on a throwaway database on the real MySQL server, which was then dropped. `agile_ams` stayed at 0 notifications. No PHPUnit tests exist yet.
- 88 API checks passed: notify, notifyMany, notifyRole, notifyEmail, the bell methods, escaping, link safety, all 27 templates, scheduling, retries and secret erasure.
- A bad SMTP host left the row `queued` with attempts 1 and 2, then `failed` with attempts 3. A fourth run did not touch it.
- A second run during a 10-second send attempt exited because of the lock, and the next run worked.
- SMTP delivery worked against a local test server with no encryption or login.
- All three job scripts return 403 with no job output when reached through a web server.
- The rendered email was checked in Chrome.

### Decisions made
- **Test script:** the email goes to the address you type, with the in-app notification on `applicant@agile.test`, because seeded addresses cannot receive mail.
- **Missing template values throw:** `notify()` fails at once instead of sending an email with a gap.
- **Secret tokens:** `ACCOUNT_VERIFY`, `PASSWORD_RESET` and `GUEST_EMAIL_CODE` payloads are erased from the row after sending or final failure.
- **Email logo:** a text monogram by default, because Gmail does not show SVG. `MAIL_LOGO_URL` switches to a hosted PNG.
- **Second run exit code:** 0, so a skipped run is not reported as a scheduler failure.

### Known issues
- Not tested: real SMTP with TLS and a login, such as Gmail, and the email's appearance inside Gmail itself.
- Tokens and codes are readable in `payload_json` until the email is sent, and in `storage/mail/` with the log driver. This is why those driver files must stay out of production.
- No announcement template exists yet. The unsubscribe note appears when a payload has `announcement` set to true, and it asks the reader to email AGILE because there is no unsubscribe page.

### Notes for later
- The auth step should put token links in the payload, not the `$link` argument, and use the email channel only.
- The auth step should pass `unreadCount()` and `latest()` data to the dashboard bell.
- The template editor should check placeholders on save, because a typo would make `notify()` throw for that template.
- The email drafts step must render rows with no template, which the queue currently marks as failed.
- `jobs/test_notify.php` should be removed or kept local when the project is deployed. It already refuses to run in production.

## Step 5 of 24: Registration, login and password reset (Oct 30, 2026)

### What was built
Registration, email verification, login with lockout, logout, password reset, CSRF protection on every POST, a database rate limiter, insert-only audit logging, idle timeout, and a temporary `/dashboard`.

### Files created
- `app/Core/`: `Csrf.php`, `Validator.php`
- `app/Middleware/`: `CsrfMiddleware`, `AuthMiddleware`, `GuestMiddleware`
- `app/Services/`: `AuditService`, `RateLimiter`, `AuthService`
- `app/Models/`: `AuditLogRepository`, `TokenRepository`, `RoleRepository`
- `app/Controllers/`: `AuthController`, `DashboardController`
- `app/Views/auth/`: `login`, `register`, `forgot_password`, `reset_password`, `resend_verification`
- `app/Views/dashboard/index.php`, `app/Views/partials/form_field.php`

### Files changed
- `app/Core/Router.php` (adds CSRF to every POST), `app/Core/Session.php`, `app/Core/Request.php`
- `app/Models/UserRepository.php`, `app/helpers.php`, `config/routes.php`, `public/assets/css/app.css`
- `app/Views/partials/head.php`, `app/Views/layouts/dashboard.php`, `app/Views/layouts/public.php`
- Removed `.gitkeep` from `app/Middleware`, `app/Views/auth` and `app/Views/dashboard`

### How to test
1. Keep one `MAIL_DRIVER` line in `.env`. Use `log` to get files in `storage/mail/`.
2. Run `php -S localhost:8000 -t public` and open http://localhost:8000/register.
3. After registering, run `php jobs/send_email_queue.php`, then open the newest file in `storage/mail/` and follow its link.

### Test results
These ran on a throwaway database on the real MySQL server, with mail forced to the log driver. It was then dropped, and `agile_ams` was unchanged. No PHPUnit tests exist yet.
- 134 HTTP checks passed, including every DONE WHEN item, TC-19 and TC-21. Expiry was tested by moving dates in the throwaway database, not by waiting 15 or 60 minutes.
- 91 browser checks passed on the auth pages at 360, 768 and 1366px, including real form submissions in headless Chrome.
- No errors were written to `app.log` during the tests.

### Decisions made
- **CSRF is added inside `Router::post()`,** so a POST route cannot be declared without it.
- **Duplicate email:** registering an existing email shows the normal success message and changes nothing, so the form does not reveal who has an account. A pending account gets a new link.
- **Disabled accounts** get the generic login error. Pending accounts get the helpful message only after the correct password.
- **Rate limits:** login, forgot password and resend use IP + email at §11's 5 per 15 minutes. Successful logins do not count. Registration also has a limit of 20 per hour per IP, which the prompt did not list.
- **Consent** is recorded in the `register` audit row, because `users` has no consent column.
- **Student number format** accepts `YYYY-NNNNN-XX-N` with any two letters, because the exact PUP format is an assumption.
- **`tooMany($key)`** also takes optional limits, defaulting to 5 per 15 minutes, because `rate_limits` does not store each key's maximum.
- **A password reset clears the lockout.**

### Known issues
- `/privacy` is linked from the consent checkbox but does not exist until its step.
- A password reset does not end sessions already open on other devices, because the schema has no session version. They end at the idle timeout.
- Verification and reset emails only arrive through the queue job, so `php jobs/send_email_queue.php` must run or be scheduled.
- `rate_limits` rows are never purged yet. They are overwritten when the same key is used again.
- If `.env` cannot be parsed, the Dotenv error message includes the offending value. A value with spaces must be in quotes.

### Notes for later
- Step 6 should pass `unreadCount()` and `latest()` to the dashboard bell, and replace the temporary dashboard.
- `session.idle_minutes` exists in the settings table, while the middleware reads `SESSION_IDLE_MINUTES` from `.env`. Decide which one wins when the settings page is built.
- A backup job or similar should purge old `rate_limits` rows.

## Step 6 of 24: Roles, permissions and the dashboard shell (Oct 30, 2026)

### What was built
Permission checks on routes and in services, a 403 page with `access.denied` audit rows, a sidebar filtered by permission, a dashboard for each role, a live notification bell, a full notifications page, a local-only "Log in as..." switcher, and the first PHPUnit test.

### Files created
- `app/Services/RbacService.php`, `app/Core/ForbiddenException.php`, `app/Middleware/PermissionMiddleware.php`, `app/Models/PermissionRepository.php`
- `app/Controllers/`: `NotificationController`, `PlaceholderController`
- `app/Views/dashboard/`: `applicant`, `general_member`, `committee_head`, `execomm`, `msw_member`, `msw_head`, `president`, `_cards`, `placeholder`
- `app/Views/notifications/index.php`, `app/Views/partials/pagination.php`
- `phpunit.xml`, `tests/bootstrap.php`, `tests/AccessMatrixTest.php`

### Files changed
- `app/Core/Router.php` (`->middleware()`, `perm:code`, 403 handling), `app/helpers.php`, `config/menu.php`, `config/routes.php`
- `app/Controllers/`: `DashboardController`, `AuthController`, `StyleguideController`
- `app/Services/`: `AuthService`, `NotificationService`. `app/Models/UserRepository.php`
- `app/Views/layouts/dashboard.php`, `app/Views/partials/notification_dropdown.php`, `app/Views/auth/login.php`
- `public/assets/js/app.js`, `public/assets/css/app.css`, `README.md`, `.gitignore`
- Removed `app/Views/dashboard/index.php` and `tests/.gitkeep`

### How to test
```
php -S localhost:8000 -t public
vendor/bin/phpunit tests/AccessMatrixTest.php
```
Open http://localhost:8000/login and use the "Log in as..." buttons.

### Test results
HTTP and browser checks ran on a throwaway database, which was then dropped. PHPUnit also ran on the real database, whose row counts stayed the same.
- `AccessMatrixTest`: 15 tests and 295 assertions pass. It reads the §6 table directly from `PROJECT_CONTEXT.md`.
- 467 HTTP checks pass, covering every seeded user's sidebar and every sidebar URL, 403 pages and audit rows, TC-20, notifications, and the switcher in local and production.
- 98 browser checks pass at 360, 768 and 1366px, including the live bell.
- The Step 5 suite still passes: 134 HTTP and 91 browser checks.
- The last change was one CSS margin on the welcome card, made after these runs and checked only by reading the code.

### Decisions made
- **Denials are handled in one place:** `authorize()` throws, and `Router` writes the audit row and returns the 403, so route and service denials behave the same.
- **`perm:` also runs the login check,** so a guest is sent to the login page, not a 403, and no denial is logged.
- **Placeholder pages:** every sidebar URL and `/admin` has a permission-checked temporary page, so access can be tested before the modules exist. They are declared last, so a real route replaces them. `/admin` uses `admin.users`.
- **No permission for Dashboard and Notifications:** a null `permission` means any signed-in user, because no §6 code covers every role.
- **No `authorize()` calls in services yet,** because no permission-protected service exists. The rule is written at the top of `RbacService.php`.
- **Applicants see no My Profile,** because §6 does not give them `profile.manage_own`. This is still the open FR-MEM-11 question.
- **Dev switcher:** the route and the list exist only when `APP_ENV=local`, it accepts only active `@agile.test` accounts, and it is audited as `login.dev_switch`.

### Known issues
- Sidebar pages other than Dashboard and Notifications are placeholders.
- `/privacy` and the public nav links other than Home still return 404.
- A session that expires while a page is open makes the bell show "We could not load your notifications" until the page is refreshed.
- Tested only in headless Chrome.

### Notes for later
- Every new module must add both `perm:` on its route and `authorize()` in its service.
- Jobs have no logged-in user, so services need a separate method for system work.
- Dashboard cards are empty placeholders until their modules exist.

## Step 7 of 24: Secure file storage, encryption and account profile (Oct 30, 2026)

### What was built
Secure upload and download services, one file route with an access policy, AES-256-GCM encryption with a key generator, the My Account page for every logged-in user, and a reusable upload field with a browser check.

### Files created
- `app/Services/`: `FileStorageService`, `FileRejectedException`, `FileAccessPolicy`, `EncryptionService`, `ImageService`, `ProfileService`
- `app/Controllers/`: `FileController`, `AccountController`
- `app/Models/SettingRepository.php`
- `app/Views/account/index.php`, `app/Views/partials/upload_field.php`, `app/Views/styleguide/upload.php`
- `jobs/generate_key.php`, `tests/EncryptionServiceTest.php`

### Files changed
- `app/Core/Response.php` (file responses), `app/Core/Request.php`, `app/Core/Validator.php`, `app/Middleware/CsrfMiddleware.php`
- `app/Controllers/`: `AuthController`, `StyleguideController`
- `app/Views/layouts/dashboard.php`, `config/routes.php`, `config/menu.php`, `config/app.php`, `.env.example`
- `public/assets/js/app.js`, `public/assets/css/app.css`, `README.md`

### How to test
```
vendor/bin/phpunit tests/EncryptionServiceTest.php
php -S localhost:8000 -t public
```
Open http://localhost:8000/styleguide/upload and http://localhost:8000/account.

### Test results
HTTP and browser checks ran on throwaway databases, which were then dropped. PHPUnit passes 23 tests across both test classes.
- `EncryptionServiceTest`: 8 tests and 16 assertions pass, including round trips and changes to the IV, tag and ciphertext.
- 132 HTTP checks pass with real multipart uploads, including an `.exe` renamed to `.pdf`, a 10 MB PDF, a file of exactly 5 MB, the avatar access rules, direct `storage/` requests, and password changes.
- 31 browser checks pass at 360, 768 and 1366px, including a real photo upload shown in the top bar.
- The Step 5 and Step 6 suites still pass. The Step 3 style guide suite was not rerun.
- Not tested: Apache, and a real phone photo. EXIF rotation was tested with a hand-built JPEG.

### Decisions made
- **My Account is open to every logged-in user at `/account`,** as this step asks, which answers the FR-MEM-11 question for applicants. `profile.manage_own` now only controls the Member details section. The `/profile` menu item and placeholder were removed.
- **Your own avatar is not audit-logged.** The top bar requests it on every page, which would bury the audit trail. Everyone else's views are logged as `file.accessed`.
- **Who can see an avatar:** the owner, and users with `member.view` or `admin.users`.
- **Photos are re-encoded:** the original is never kept. The result is a 400px square JPEG with EXIF data, such as GPS location, removed. Photos over 24 megapixels are refused to protect PHP's memory.
- **`upload.max_mb` in settings is the limit.** The unused `UPLOAD_MAX_MB` setting was removed, which closes the note from step 1. Photos are capped at 5 MB regardless.
- **Oversized POSTs:** PHP drops the whole form when it exceeds `post_max_size`, so the middleware shows a "too large" message instead of a misleading 419.
- **Added beyond the prompt:** a local-only `/styleguide/upload` page, because no module accepts PDFs yet. Accepted files are deleted right away. Also a limit of 5 wrong current-password attempts per 15 minutes.

### Known issues
- The key is not set up automatically. `ENCRYPTION_KEY` must be added to `.env` by hand before the welfare module.
- Changing a password does not end sessions on other devices.
- There is no way to remove a profile photo, only to replace it.
- With `display_errors` on and a POST over `post_max_size`, PHP prints a startup warning before the app runs. Production must keep `display_errors` off.

### Notes for later
- Each module step must replace its stub in `FileAccessPolicy` by asking its own service, and audit the access.
- The welfare encryption coverage question is still open: `subject`, remarks and attachment files.

## Changes after step 7 (Oct 30, 2026)

### Official logo
`public/assets/img/logo.png` (256px, used by the site) and `logo-large.png` (full size) replaced `logo.svg`. Emails keep the text monogram until `MAIL_LOGO_URL` points to a public address.

### Robot login page
The login page now uses "Volt", a robot whose body holds the form, requested from a screenshot of a design by Coding Stella. The code is original, because the screenshot did not include it, and the colors follow §12.
- Files created: `public/assets/css/login-robot.css`, `public/assets/js/login-robot.js`
- Files changed: `app/Views/auth/login.php`, `app/Views/layouts/auth.php`, `app/Controllers/AuthController.php`, `public/assets/img/icons.svg`, `app/Views/styleguide/index.php`
- Login logic, CSRF, lockout and the generic error message did not change. The robot is decorative and hidden from screen readers, and the page works without JavaScript.
- Added: a show or hide button on the password field.
- Tests: 44 new browser checks pass at 360, 768 and 1366px. The Step 5 and Step 6 suites still pass. The Step 3 and Step 7 suites were not rerun.
- If this design is shown or submitted, credit Coding Stella as the inspiration.

### Volt theme across the system
Before-and-after mockups were made, but the theme was not applied. The app keeps the current look outside the login page.

## Step 8 of 24: CMS with publishing approval (Oct 30, 2026)

### What was built
News, events, publications and announcements with a Quill rich text editor, server-side HTML purifying, cover and body images, the §8 approval flow with notifications and audit rows, a review page, public post pages, and 6 seeded posts.

### Files created
- `app/Services/`: `CmsService`, `HtmlSanitizer`
- `app/Controllers/`: `CmsController`, `MediaController`
- `app/Models/ContentPostRepository.php`
- `app/Views/cms/`: `index`, `edit`, `show`, `_article`. `app/Views/portal/post.php`
- `public/assets/js/cms-editor.js`, `public/assets/vendor/quill/` (Quill 2.0.3, checksum compared with npm)
- `database/seeds/content_posts.php`, `tests/ContentRulesTest.php`

### Files changed
- `composer.json`, `composer.lock` (`ezyang/htmlpurifier`)
- `config/routes.php`, `app/helpers.php` (`media_url()`), `app/Services/FileStorageService.php`, `app/Controllers/PortalController.php`
- `app/Views/partials/pagination.php`, `database/seed.php`, `public/assets/css/app.css`
- Removed `.gitkeep` from `app/Views/cms` and `public/assets/vendor`

### How to test
```
php database/setup.php
php -S localhost:8000 -t public
```
`setup.php` without `--fresh` keeps existing data and adds the seeded posts. Log in as Committee Head and open http://localhost:8000/cms. Published posts are at `/posts/{slug}`.

### Test results
These ran on throwaway databases, which were then dropped. `agile_ams` still needs `php database/setup.php` to get the seeded posts.
- PHPUnit: 41 tests and 364 assertions pass across all three test classes, including every §8 transition and 16 kinds of hostile HTML.
- 115 HTTP checks pass, including every DONE WHEN item, record-level access, filters, slugs, covers and media.
- 103 browser checks pass, covering real typing, a real paste, the real file chooser, and all screens at 360, 768 and 1366px.
- Browser testing found and fixed three editor bugs: Tab was trapped in the editor, focus jumped to the editor on page load, and the code inside a pasted script tag stayed as text.
- The Step 5 and Step 6 suites passed before the last small editor fixes, which changed only `cms-editor.js`, one CSS rule and the image alt setting. The Step 3 and Step 7 suites were not rerun.

### Decisions made
- **`/posts/{slug}` was added,** because TC-23 needs a public page to check. The public list pages (News, Events and so on) still belong to the portal step.
- **Remarks are required to publish as well as to return,** because BR-14 lists approval as a decision that needs remarks.
- **`/media/{id}` uses the file's random 128-bit name as the id,** so the schema did not change. A cover or body image is reachable by anyone with its URL as soon as it is uploaded, even before the post is published.
- **Script source pasted as plain text is also removed,** to meet the DONE WHEN item, although it could never run.
- **Pasted outside images are dropped,** in the editor and on the server.
- **Authors can edit only `draft` and `returned` posts.** The MSW Committee Head can open and edit anyone's. Only `content.publish` can archive.
- **The MSW Committee Head goes through the same flow:** a post moves draft, for approval, published. There is no one-click publish.

### Known issues
- Replaced covers are deleted, but body images removed from a post stay in `storage/uploads/media/`.
- Body images have an empty description (`alt`), because the Quill toolbar has no field for it. Cover images are also treated as decorative.
- A cover image cannot be removed, only replaced.
- Audit rows do not record the old and new text of an edit.
- Tested only in headless Chrome.

### Notes for later
- The portal step should build the public lists on `published` posts only and link to `/posts/{slug}`.
- `FileStorageService::findMedia()` searches folders with `glob`, which is fine at this scale. A media table would be better if uploads grow large.

## Step 9 of 24: Public portal and status tracking (Oct 30, 2026)

### What was built
The public website: home, about, news, events, publications, announcements, open positions, an Ask for Help choice page, Track Status, a Privacy Notice, and page titles, descriptions and Open Graph tags.

### Files created
- `app/Services/`: `PortalService`, `TrackingService`
- `app/Controllers/TrackController.php`
- `app/Models/`: `VacancyRepository`, `CommitteeRepository`, `MembershipApplicationRepository`, `WelfareCaseRepository`
- `app/Views/portal/`: `about`, `list`, `positions`, `position`, `ask_for_help`, `coming_soon`, `track`, `privacy`, `_post_card`, `_position_card`

### Files changed
- `app/Controllers/`: `PortalController` (rewritten), `AuthController`
- `app/Views/portal/`: `home`, `post`. `app/Views/partials/head.php`, `app/Views/layouts/dashboard.php`
- `app/Core/Request.php`, `app/Middleware/AuthMiddleware.php`, `app/Models/ContentPostRepository.php`
- `config/routes.php`, `config/menu.php`, `public/assets/css/app.css`

### How to test
```
php database/setup.php
php -S localhost:8000 -t public
```
Open http://localhost:8000. To clear the Track Status block while testing, run `DELETE FROM rate_limits` in MySQL.

### Test results
These ran on throwaway databases, which were then dropped. No PHPUnit tests were added in this step.
- 165 HTTP checks pass, including published-only rules for every status, TC-03, and tracking confidentiality.
- 424 browser checks pass on 14 public pages at 360, 768 and 1366px, covering overflow, contrast, keyboard focus, console errors and outside requests.
- The Step 5, 6 and 8 suites still pass. The Step 3 and 7 suites were not rerun.
- Valid-code tracking was tested with rows inserted directly, because applications and cases are created in later steps. It still needs a real run after step 10.

### Decisions made
- **Tracking never loads confidential fields:** the queries select only status, dates and the owner's email. Application remarks and reviewer names are also left out, because the prompt says to show only the listed items.
- **Every Track try counts,** right or wrong. The limit is per IP, as the prompt says, so people sharing one network share it.
- **A successful welfare lookup is logged** in both `welfare_case_history` and `audit_logs`, per §11.
- **A note that cannot be decrypted** shows "An update was added to your case."
- **Drafts cannot be tracked,** because they have not been submitted.
- **Added beyond the prompt:**
  - After login, people return to the page they wanted, so Apply keeps its vacancy.
  - `/my-submissions` is a placeholder, with a sidebar item, for the Track shortcut to link to.
  - `/welfare/guest` is a "not available yet" page until step 15.
  - The sidebar's Ask for Help now points to `/welfare/new`, as this step names it.
  - The `welfare.guest_submission` setting hides the guest option when off.
  - Dashboard pages have a footer with the Privacy Notice link.
- **Apply buttons are hidden** for logged-in roles without `application.submit`.

### Known issues
- The Privacy Notice is a draft written from `PROJECT_CONTEXT.md`. It needs review by AGILE's adviser or data privacy officer, and its retention wording has no fixed period.
- Mission, vision and committee descriptions are seeded wording, and the contact email is a placeholder.
- `/application` and `/welfare/new` are placeholders until their steps.
- Open Graph images use `APP_URL`, so they only work for outside sites once the system is hosted.

### Notes for later
- Step 10's wizard should read `?vacancy=` to preselect the vacancy.
- Step 15 replaces the `/welfare/guest` page, and should write `status_change` history rows for tracking to show.
- `/my-submissions` needs its real list once applications and cases exist.

## Step 10 of 24: Membership application wizard (Oct 31, 2026)

### What was built
A six-step application wizard with server-side drafts, full validation at submit, BR-02 enforcement, tracking codes and notifications, a My Applications list and detail page with a status timeline, withdrawal, the revision flow, and document downloads through `FileAccessPolicy`.

### Files created
- `app/Services/`: `MembershipService`, `ApplicationStatus`, `DocumentStatus`, `ApplicationIncompleteException`
- `app/Controllers/`: `ApplyController`, `MyApplicationsController`
- `app/Models/`: `ApplicationQuestionRepository`, `ApplicationAnswerRepository`, `ApplicationDocumentRepository`
- `app/Views/membership/`: `index`, `show`, `_summary`
- `app/Views/membership/apply/`: `step1` to `step6`, `success`, `_stepper`, `_actions`, `_question`, `_doc_upload`
- `database/migrations/001_application_wizard_columns.php`, `tests/ApplicationRulesTest.php`

### Files changed
- `database/schema.sql`, `database/setup.php` (runs migrations), `database/seeds/application_questions.php`, `docs/database.md`
- `app/Models/MembershipApplicationRepository.php`, `app/Models/UserRepository.php`, `app/Services/FileAccessPolicy.php`
- `config/routes.php`, `config/menu.php`, `app/Views/portal/home.php`, `app/Views/portal/position.php`
- `public/assets/js/app.js` (copy buttons), `public/assets/css/app.css`
- Removed `app/Views/membership/.gitkeep`

### How to test
```
php database/setup.php
php -S localhost:8000 -t public
```
`setup.php` must run first, because the wizard needs the two new columns. It keeps existing data. Then log in as Applicant and open http://localhost:8000/apply. To see the email file, run `php jobs/send_email_queue.php` with `MAIL_DRIVER=log`.

### Test results
These ran on throwaway databases, which were then dropped.
- PHPUnit: 45 tests pass, including every §8 application and document transition.
- 142 HTTP checks pass with real uploads, including every DONE WHEN item, access rules, the revision flow and vacancies.
- 145 browser checks pass with a full run by real clicks at phone width, and the pages at 360, 768 and 1366px.
- The migration was tested on a database from before this step: it added both columns, kept existing rows, moved the year level question without changing its id, and added nothing on a second run.
- The Step 5, 6, 8 and 9 suites still pass. The Step 3 and 7 suites were not rerun.
- The revision flow and Under Review states were tested by setting statuses directly, because reviewers arrive in step 11.

### Decisions made
- **Two columns were added to `membership_applications`:** `wizard_step` and `enrollment_confirmed_at`. §9 had nowhere to keep the resume point or the BR-01 confirmation. They are in `docs/database.md`, but `PROJECT_CONTEXT.md` §9 still needs them.
- **Year level moved from `common` to a new `academic` role type,** because step 2 asks it. The seeder moves the existing row.
- **The tracking code is created with the draft,** because the column is `NOT NULL`. It is only shown after submitting and drafts cannot be tracked. A resubmission keeps the same code.
- **BR-02 counts submitted, under review, for revision and approved as active.** Rejected and withdrawn applications do not block a new one.
- **Existing members cannot apply for general membership,** only for open positions.
- **One draft per user.** Starting again resumes it.
- **Choosing a type in step 3 counts as completing it,** even with Save draft, so returning resumes at step 4.
- **During a revision:** the membership type is locked, verified documents cannot be changed, and a document marked `needs_correction` must be replaced before resubmitting.
- **`submitted_at` and `consent_at` update on resubmission.** The original date stays in the history.
- **`/my-applications` needs a login, not `application.submit`,** so people appointed to other roles keep their history. It shows only their own records.
- **A crafted POST cannot skip ahead to a later step.** Testing found this gap, and it is fixed.

### Known issues
- A draft cannot be deleted, only finished. Its files stay until then.
- Student number and name cannot be corrected in the wizard. People contact the MSW Committee, and no screen for that exists until the user management step.
- Tested only in headless Chrome.

### Notes for later
- Step 11 should change statuses through `ApplicationStatus` and `DocumentStatus` and write `application_status_history` with remarks. Those remarks are shown to the applicant for `for_revision`, `rejected` and `approved`.
- `MembershipService::changeStatus()` is private, so the reviewer methods need to live in the same service. Done in step 11.
- Abandoned drafts may need a cleanup job.

## Step 11 of 24: Application processing and decisions (Oct 31, 2026)

### What was built
Internal screens for the MSW Committee: an applications list with filters, search, sorting and pagination; a detail drawer on desktop and full page on mobile; a detail page with Overview, Documents, Evaluations, Interviews and Activity tabs; inline document preview and per-document verification; Mark Under Review, Return for Revision, Approve and Reject through `MembershipService`; member creation on approval in one transaction; and an audit trail that includes every view.

### Files created
- `app/Controllers/ApplicationsController.php`
- `app/Services/CredentialService.php` (no-op `issueFor()` until step 12)
- `app/Models/MemberRepository.php`
- `app/Views/applications/`: `index`, `_drawer`, `show`
- `public/assets/js/applications.js`

### Files changed
- `app/Services/MembershipService.php`, `app/Services/FileStorageService.php`
- `app/Models/MembershipApplicationRepository.php`, `app/Models/AuditLogRepository.php`
- `app/Controllers/FileController.php`, `app/Views/membership/_summary.php`
- `config/routes.php`, `config/menu.php`
- `database/seeds/email_templates.php` (now 28 templates), `docs/database.md`
- `public/assets/css/app.css`

### How to test
```
cd C:\laragon\www\agile
php database/setup.php
php -S localhost:8000 -t public
```
`setup.php` must run first, because Needs Correction uses a new email template. It keeps existing data. Then log in as MSW Committee Member or MSW Committee Head and open http://localhost:8000/applications.

### Test results
These ran on throwaway databases, which were then dropped.
- PHPUnit: 45 tests pass.
- 148 HTTP checks pass, including forced POSTs by other roles, which return 403 with an audit row, the BR-03 block on Approve, and a forced failure proving approval rolls back as one transaction.
- 138 browser checks pass with real clicks at 360, 768 and 1366px, covering contrast, overflow, labels, CSP errors and keyboard focus.
- The queue sent all 13 decision emails with no failures.
- The Step 5, 6, 8, 9 and 10 suites passed earlier in this step, before the final test-script and small CSS and Activity display changes. They were not rerun after those. The Step 3 and 7 suites were not rerun.

### Decisions made
- **Remarks:** required for all four status actions. For document verification they are required only for Needs Correction and Rejected, because a Verified document has nothing to explain.
- **New `APP_DOCUMENT_CORRECTION` email template.** The step asks for a notification on Needs Correction, and no existing template fitted.
- **Committee heads are read-only:** approved or evaluated applications only, with no documents or Activity tab. Other applications return 403 and an audit row.
- **Nobody can act on their own application,** even with the right permission.
- **Documents can be verified only while Submitted or Under Review.** A document marked Needs Correction only reaches the applicant once a reviewer uses Return for Revision.
- **Approval with an existing member:** someone who is already a member and applies for a vacancy keeps their member record and role. Only a new applicant gets a member row and becomes `general_member`.
- **"Ready for recruitment"** means an approved application with a `vacancy_id` and no appointment, plus an audit row. Step 14 picks these up.
- **The drawer opens at 1200px and wider.** Opening it counts as a view and logs `application.viewed`.
- **Inline preview is limited.** `?inline=1` works only for `application_document` files and users with `document.verify`. Only that response relaxes `X-Frame-Options` and the CSP so the browser can show it in a frame on the same site. Normal downloads keep the strict headers.

### Known issues
- `APP_APPROVED` tells the applicant that credentials are ready, but they are not issued until step 12.
- PDF preview was not confirmed visually. Headless Chrome loads the file with status 200 but paints its PDF viewer blank. Check it once in normal Chrome with a real COR.
- Chrome requests a previewed PDF twice, so one preview can log two `file.accessed` rows.
- The calendar icon inside a date field shows Chrome's default focus ring, not the site's. It is clearly visible, but page CSS could not change it.
- Activity rows for `file.accessed` do not name the document, because that audit entry does not record its type.
- Tested only in headless Chrome.

### Notes for later
- Step 12 must implement `CredentialService::issueFor($memberId)`. `MembershipService::approve()` already calls it.
- Step 14 replaces the Evaluations and Interviews placeholders and handles approved vacancy applications.

### After step 11
- `PROJECT_CONTEXT.md` §9 now lists `wizard_step` and `enrollment_confirmed_at`.
- `ENCRYPTION_KEY` is set in `.env` (not committed). Welfare data cannot be read without it, so it needs a safe copy outside the project.
- **Welfare encryption decided:** subject, closing remarks, referral and follow-up remarks, and attachment files are encrypted, along with the fields already listed. `welfare_cases.subject` is now `TEXT` (`database/migrations/002_encrypted_welfare_subject.php`). The service must limit it to 150 characters before encrypting. Staff cannot search by subject.
- **MSW Head eligibility decided:** the President verifies and decides the MSW Head's own screening only, through the new `eligibility.decide_msw_head` permission. The MSW Head must be blocked from their own screening. The eligibility step builds this.
- `welfare_attachments.original_name`, `referrals.referred_to` and `welfare_case_history` values stay plain text. The welfare step must not copy encrypted content into the history.
- The welfare step needs a way to encrypt files: `EncryptionService` handles strings, and `FileStorageService` stores files as they are.

## Step 12 of 24: Member records, digital ID and certificate (Oct 31, 2026)

### What was built
Digital ID and certificate PDFs with QR codes, issued on approval; reissue and revocation; a public `/verify/{qr_token}` page with a rate limit; a Membership section in My Account with owner-only downloads; member records for staff with search, filters, pagination and credentials history; and `MembershipService::changeRole()` for steps 14 and 17.

### Files created
- `app/Controllers/`: `MembersController`, `VerifyController`
- `app/Services/MemberRecordsService.php`
- `app/Models/`: `CredentialRepository`, `PositionRepository`
- `app/Views/credentials/`: `id_card`, `certificate`
- `app/Views/members/`: `index`, `show`, `_profile`
- `app/Views/portal/verify.php`
- `tests/MemberRoleTest.php`

### Files changed
- `composer.json`, `composer.lock` (`dompdf/dompdf` 3.1, `endroid/qr-code` 6.0)
- `app/Services/`: `CredentialService` (no longer a no-op), `MembershipService`, `FileAccessPolicy`, `FileStorageService`
- `app/Core/Database.php` (`onRollback`), `app/Models/MemberRepository.php`, `app/Models/CommitteeRepository.php`
- `app/Controllers/`: `FileController`, `AccountController`; `app/Views/account/index.php`
- `config/routes.php`, `config/permissions.php`, `config/statuses.php`
- `docs/PROJECT_CONTEXT.md` (§6), `tests/AccessMatrixTest.php`, `public/assets/css/app.css`

### How to test
```
cd C:\laragon\www\agile
composer install
php database/setup.php
php -S localhost:8000 -t public
```
`setup.php` must run first, because it adds the new permissions. It keeps existing data. Approve an application as MSW Committee Head, then log in as that applicant and open My Account.

### Test results
These ran on throwaway databases, which were then dropped.
- PHPUnit: 66 tests pass, including every position-to-role mapping and a failed `changeRole()` leaving nothing behind.
- 162 HTTP checks pass. They download the real PDFs, read the QR code from the rendered page, open its URL, and try every access rule.
- 122 browser checks pass with real clicks at 360, 768 and 1366px.
- The PDFs were rendered to images and checked by eye, including a long name with `ñ` and a real photo.
- The Step 5, 6, 8, 9, 10 and 11 suites still pass. The Step 3 and 7 suites were not rerun.

### Decisions made
- **Downloads are owner only, as the step says.** Staff see credentials history but cannot open the PDF, including the MSW Head.
- **Only the current copy can be downloaded.** A revoked PDF still says Active, so it returns 404. The file stays on disk.
- **New `credential.reissue` permission for the MSW Head.** §6 had no code for this. It is marked *(addition)*, making 32 codes.
- **`issueFor()` issues only what is missing,** so a member approved for a vacancy keeps their credentials until step 14 appoints them.
- **Approval stays all-or-nothing.** If anything fails, the PDFs already saved are deleted through `Database::onRollback()`.
- **`changeRole()` does not check permissions itself,** because the expiry job runs with nobody signed in. Its callers must check, and the actor is passed in and recorded. It is the one exception to §2 rule 4.
- **Position to role follows §5:** President to `president`; other executive posts to `execomm`; an MSW head to `msw_head`; MSW deputies and members to `msw_member`; other committee heads to `committee_head`; everyone else to `general_member` with a position.
- **`changeRole()` also keeps `committees.head_user_id` correct,** or a former head would still be linked to the committee.
- **A null position sets `role_status` to `expired`** if they held a position. `status` stays `active` (BR-13).
- **Verification allows 30 checks per 15 minutes per IP address,** above §11's example of 5, because one person may check several credentials at an event. Revoked credentials still show the name and current role, as the step asks.
- **Serial format:** `ID-AGILE-2026-0001-01` and `CERT-AGILE-2026-0001-01`. The last part counts that member's credentials of that type.
- **Opening a member record logs `member.viewed`.**

### Known issues
- QR codes hold `APP_URL` when issued. With `http://localhost:8000` they open only on the computer running the site, so a phone cannot scan them through to the page. Credentials issued before going live must be reissued after `APP_URL` changes.
- Seeded seeded members have no credentials until the MSW Head uses Issue new credentials on their record.
- A later change of name or photo does not update existing PDFs until a reissue. There is no name-change screen yet.
- Certificates have blank signature lines, as the step describes. Nothing signs them digitally.
- `changeRole()` sends no email. Step 17 needs to send `ROLE_EXPIRED`, and step 14 `APPOINTMENT_NOTICE`.
- Tested only in headless Chrome. The PDFs were checked with a separate renderer, not Adobe Reader or a real phone scan.

### Notes for later
- Step 14 calls `changeRole()` on appointment, and step 17 calls it with a null position on expiry. Both must check permission first.
- The Notices block in `app/Views/members/_profile.php` is the placeholder step 17 replaces.

## Step 13 of 24: HR requests and vacancies (Oct 31, 2026)

### What was built
HR requests with committee-only filing, MSW Head approval or return, edit and resubmit, cancel, and a history on each request; vacancies created only from an approved request, saved as draft or published, reopened or cancelled, with the applications received on the detail page; and `jobs/close_vacancies.php`, which closes past-date vacancies, marks full ones Filled and notifies the committee head.

### Files created
- `app/Services/RecruitmentService.php` (transition maps for both records)
- `app/Controllers/`: `HrRequestsController`, `VacanciesController`
- `app/Models/HrRequestRepository.php`
- `app/Views/recruitment/`: `_history`
- `app/Views/recruitment/hr_requests/`: `index`, `form`, `show`
- `app/Views/recruitment/vacancies/`: `index`, `choose_request`, `form`, `show`
- `jobs/close_vacancies.php`, `tests/RecruitmentTest.php`

### Files changed
- `app/Models/`: `VacancyRepository`, `PositionRepository`, `AuditLogRepository`, `MembershipApplicationRepository`
- `config/routes.php`, `config/menu.php`

### How to test
```
cd C:\laragon\www\agile
php database/setup.php
php -S localhost:8000 -t public
```
This step adds no tables or permissions. `setup.php` is only needed if it was not run after step 12. Log in as `head@agile.test` and open HR Requests. To run the job: `php jobs/close_vacancies.php`. To schedule it, run that command daily at 00:05 in Windows Task Scheduler (§10).

### Test results
These ran on throwaway databases, which were then dropped.
- PHPUnit: 84 tests pass, including every §8 transition for both records.
- 212 HTTP checks pass, including forced POSTs by other roles, forged positions from other committees, the job run twice, and the queue sending the new emails.
- 267 browser checks pass with real clicks at 360, 768 and 1366px.
- The Step 5, 6, 8, 9, 10, 11 and 12 suites still pass. Two of them were updated for the new menu URLs. The Step 3 and 7 suites were not rerun.

### Decisions made
- **Sidebar URLs moved to `/recruitment/hr-requests` and `/recruitment/vacancies`,** because the step names `/recruitment/vacancies/create`.
- **History comes from `audit_logs`.** §9 has no history table for these records, and audit rows cannot be changed. Vacancy cancellation remarks are kept there too, because `vacancies` has no remarks column.
- **Committee ownership comes from the chosen position,** whose committee must have the signed-in user as `head_user_id`. Anything else is 403 with an audit row. This also applies to the MSW Head, for their own committee (§6).
- **Approve also requires remarks,** as the step and BR-14 say.
- **The MSW Head can approve a request they filed,** because nobody else can. They are not notified about their own actions.
- **A request has one live vacancy.** Cancelling it allows a new one.
- **Position, slots and qualifications are copied from the request** and ignored if posted. Only drafts can be edited.
- **A closed vacancy with every slot filled cannot be reopened,** and a Filled one has no way back (§8).
- **The `VACANCY_CLOSED` link opens the HR request,** which shows the vacancy's status, because committee heads cannot open vacancy pages. If the committee has no head, the requester gets the notice.
- **`closeDueVacancies()` has no permission check,** because the job runs with nobody signed in. Controllers must not call it. This is a second exception to §2 rule 4, after `changeRole()`.
- **Search was added to both lists,** because §12 asks for it on tables.
- **Any position in the committee can be requested,** including its Committee Head post. §9 does not limit this.

### Known issues
- The job is not scheduled yet. A past-date vacancy leaves the public site at once, but stays Open on staff screens and sends no notice until the job runs.
- Applications still being processed when a vacancy is cancelled stay in the queue. The confirmation dialog says so, and applicants are not notified.
- The `HR_REQUEST_APPROVED` email says the vacancy will be published, which is the MSW Head's next step and is not automatic.
- `VACANCY_CLOSED` says "closed" for a Filled vacancy too, and its "View the vacancy" link opens the HR request.
- Tested only in headless Chrome.

### Notes for later
- Step 14 must raise `slots_filled` on appointment. The public pages do not check it, so a full vacancy stays listed until the job runs unless step 14 also closes it at once.
- §8 has no closed to filled transition, so an appointment on a closed vacancy leaves it Closed even when every slot is filled. Step 14 needs a decision on this. Decided in step 14: closed to filled was added.

## Step 14 of 24: Evaluations, interviews and appointments (Oct 31, 2026)

### What was built
The Evaluations tab with the rubric from settings, a server-calculated weighted score, one editable evaluation per evaluator, and a summary; the Interviews tab with scheduling, rescheduling, invites and results; `jobs/interview_reminders.php`; appointments that are prepared, then approved or returned by the MSW Head, with `config/role_mapping.php` and a role override; and read-only committee and evaluator queue pages.

### Files created
- `app/Services/`: `EvaluationService`, `InterviewService`, `AppointmentService`
- `app/Controllers/`: `EvaluationsController`, `InterviewsController`, `AppointmentsController`
- `app/Models/`: `EvaluationRepository`, `InterviewRepository`, `AppointmentRepository`
- `app/Views/applications/`: `_evaluations`, `_interviews`
- `app/Views/recruitment/evaluations/`: `index`, `committee`
- `app/Views/recruitment/interviews/index.php`
- `app/Views/recruitment/appointments/`: `index`, `prepare`, `show`
- `config/role_mapping.php`, `jobs/interview_reminders.php`, `tests/AppointmentFlowTest.php`

### Files changed
- `app/Services/`: `MembershipService`, `RecruitmentService`
- `app/Models/`: `MembershipApplicationRepository`, `UserRepository`, `VacancyRepository`
- `app/Controllers/ApplicationsController.php`
- `app/Views/applications/show.php`, `app/Views/recruitment/_history.php`
- `config/routes.php`, `config/menu.php`, `config/statuses.php`
- `docs/PROJECT_CONTEXT.md` (§8), `tests/RecruitmentTest.php`, `public/assets/css/app.css`

### How to test
```
cd C:\laragon\www\agile
php database/setup.php
php -S localhost:8000 -t public
```
This step adds no tables, permissions or templates. `setup.php` is only needed if it was not run after step 12. To run the job: `php jobs/interview_reminders.php`. To schedule it, run that command hourly in Windows Task Scheduler (§10).

### Test results
These ran on throwaway databases, which were then dropped.
- PHPUnit: 110 tests pass, including every appointment transition and every position-to-role mapping.
- 220 HTTP checks pass, including forced POSTs by other roles, the reminder job run twice, and a forced failure proving appointment approval rolls back as one transaction.
- 282 browser checks pass with real clicks at 360, 768 and 1366px.
- The Step 5, 6, 8, 10, 11, 12 and 13 suites still pass. The Step 9 suite failed 11 title checks because `APP_NAME` in `.env` changed to `AGILE-OUS` during the run, and passes with the old name set for that one run. The Step 3 and 7 suites were not rerun.

### Decisions made
- **`closed` to `filled` was added to §8,** chosen by the project owner. The last slot taken by an appointment makes the vacancy Filled, even after its closing date, and the committee head gets `VACANCY_CLOSED`.
- **Committee heads can now open and evaluate position applications that are still being decided,** because BR-05 names all committee heads as evaluators. This changes the step 11 rule. They still see no documents, Activity or Interviews, and cannot open or evaluate general membership applications unless these were approved or already evaluated.
- **The score is the weighted average on the 1 to 5 scale.** `rubric_json` keeps the labels and weights used, so a later rubric change does not alter past evaluations.
- **Evaluations stay open during Submitted, Under Review and For Revision,** and lock at approved, rejected or withdrawn. Remarks are required, because the column is `NOT NULL`.
- **One pending interview per application.** Rescheduling edits it, sends a new invite and allows a new reminder. Another can follow once a result is recorded.
- **The reminder goes out 20 to 25 hours before the interview.** Hourly runs land at about 24 hours, and a job that runs late still reminds. An interview booked with less notice only gets its invite, because the email says "tomorrow".
- **Online interviews must have an `http` or `https` link.**
- **The appointment's position always comes from the vacancy,** whose slots it takes, so the form shows it instead of asking.
- **A returned appointment is final (§8),** and a new one can be prepared.
- **The role override is limited to six roles and needs a reason.** The reason is kept in the audit log and the member's role history, because `appointments` has no column for it. Committee heads are still recorded by position, so an override does not change `committees.head_user_id`.
- **New sidebar item, My Committee,** for `hr_request.submit`. Evaluations, Interviews and Appointments moved under `/recruitment/`.
- **`sendDueReminders()` has no permission check,** because the job runs with nobody signed in. Controllers must not call it. This is a third exception to §2 rule 4, after `changeRole()` and `closeDueVacancies()`.

### Known issues
- The role changes when the appointment is approved, even if the effective date is later. Nothing applies it on that date.
- Appointing a new head to a committee replaces `head_user_id`, but the former head keeps their role and position until someone changes them. There is no screen for that until the user management step.
- Nobody is notified when an appointment is prepared or returned. No template exists for it, and the step does not ask for one. The MSW Head sees prepared ones on Appointments.
- Interview results are not sent to the applicant, and applicants see their interview only in the invite and reminder.
- Approving a vacancy application changes nothing about slots. Only an approved appointment does.
- The reminder job is not scheduled yet.
- `tests/AppointmentFlowTest.php` expects `applicant@agile.test` to have no member record, so it needs a freshly seeded database. It fails on a database where that applicant was already approved, without changing anything.
- Tested only in headless Chrome.

### Notes for later
- `APP_NAME` is now `AGILE-OUS` in `.env`, so page titles use that name. `.env.example` still says `AGILE`.
- Step 17 calls `changeRole()` with a null position on expiry, and should send `ROLE_EXPIRED`.

## Step 15 of 24: Welfare concern form, guest submission and case tracking (Oct 31, 2026)

### What was built
The welfare form for logged-in users, including Applicants, with category cards, a character counter, attachments and a related closed case; encrypted case text and files; urgent alerts; My Welfare Cases; the three-step guest flow with an emailed code; guest tracking through `/track`; and linking guest cases to an account when its email is verified.

### Files created
- `app/Services/`: `WelfareService`, `GuestVerificationService`
- `app/Controllers/`: `WelfareController`, `GuestWelfareController`
- `app/Models/`: `WelfareAttachmentRepository`, `WelfareCategoryRepository`, `GuestVerificationRepository`
- `app/Views/welfare/`: `_concern_form`, `new`, `index`, `show`
- `app/Views/welfare/guest/`: `start`, `verify`, `form`, `submitted`
- `public/assets/js/welfare-form.js`, `tests/WelfareRulesTest.php`

### Files changed
- `app/Services/`: `AuthService`, `FileAccessPolicy`, `FileStorageService`, `TrackingService`
- `app/Models/WelfareCaseRepository.php`
- `app/Controllers/`: `FileController`, `PortalController`
- `app/Views/partials/upload_field.php`, `app/Views/portal/track.php`
- `config/routes.php`, `config/menu.php`, `public/assets/css/app.css`

### How to test
```
cd C:\laragon\www\agile
php -S localhost:8000 -t public
```
This step adds no tables, permissions, settings or templates. To see emails, including the guest code, run `php jobs/send_email_queue.php` and open the newest file in `storage/mail/`.

### Test results
These ran on throwaway databases, which were then dropped.
- PHPUnit: 126 tests pass, including every §8 welfare transition and all 7 categories.
- 219 HTTP checks pass, including real file uploads and downloads, forced access by other roles, code expiry and lockout, rate limits, the 30-minute limit, and account linking through a real verification email.
- 218 browser checks pass with real clicks at 360, 768 and 1366px.
- The Step 5, 6, 8, 9, 10, 11, 12, 13 and 14 suites still pass. The Step 9 suite ran with `APP_NAME=AGILE` set for that run, because it expects that name in titles. Three older suites were updated for the new sidebar item, Track page and Evaluations tab. The Step 3 and 7 suites were not rerun.

### Incident during this step
A throwaway test script had a mistyped path, so its list of existing files came back empty. Its cleanup then deleted every file in `storage/uploads`, which held 16 files, permanently. Only one was referenced by `agile_ams`: the profile photo of `mswmember@agile.test` (`users.photo_path`). No application documents, post images or welfare files were referenced. `.gitkeep` was restored. The script now stops if its list is empty. Application code did not cause this.

### Decisions made
- **Subject and attachment files are encrypted too,** as decided after step 11. Files are stored as `.enc` in `storage/uploads/welfare/` and decrypted in memory for an allowed download.
- **The first history row is `status_change` with the new value `submitted`,** not a new event type, because §9 has no `submitted` event and tracking's timeline reads status changes.
- **The owner's case page shows the subject, status, updates and their files, but not the description,** because §6 gives submitters the status of their cases. The step lists the subject on My Welfare Cases.
- **A failed form is shown again from the same request,** with status 422, so confidential text never enters the session. Browsers cannot keep chosen files, so the page asks to choose them again.
- **The "this email has an account" hint appears only after the code is confirmed,** so nobody can use the form to test whose email has an account.
- **Rate limits:** 5 code requests per 15 minutes per IP and email, plus 20 per IP across all emails, so one address cannot mail many strangers. 5 guest submissions per 15 minutes per IP and email.
- **A filled honeypot gets the normal next page,** but no email is sent and no code works.
- **Limits:** subject 150 characters, description 5000, up to 5 attachments.
- **A guest may reference any earlier case of the same email.** A logged-in user chooses from their closed cases, as the step says.
- **Closed guest cases on `/track` also offer a new concern,** with the code prefilled through the steps.
- **No reopen route exists,** so a direct POST gets 404 or 405. `WelfareService::assertCanChange()` also refuses any change to a closed case, for step 16 to call.
- **`/welfare/{id}` is a placeholder for `welfare.view`,** so the links in urgent alerts work now. Step 16 replaces it.
- **The Track page for logged-in users links to My Applications and My Welfare Cases,** because My Submissions is still a placeholder.

### Known issues
- The profile photo of `mswmember@agile.test` is gone, so that account shows a broken image until a new photo is uploaded in My Account.
- A queued `GUEST_EMAIL_CODE` email holds the code in `notifications.payload_json` until it is sent, then the queue erases it. In `guest_verifications` only the hash is kept.
- The code email waits for `send_email_queue.php`, which runs every 5 minutes once scheduled, so some of the 15 minutes can pass before it arrives.
- A guest case whose email already has a verified account stays a guest case, because linking happens only when an email is verified. It can still be tracked at `/track`.
- A verified guest can submit more than once during the 30 minutes, up to the rate limit.
- Old `guest_verifications` rows are never removed.
- `/my-submissions` is still a placeholder.
- Tested only in headless Chrome.

### Notes for later
- Step 16 should change cases through `assertCanChange()`, write `status_change` history rows, and use `notifySubmitter()`.
- Step 16 replaces the `/welfare/{id}` placeholder, and must decide what happens to the owner's download after closure (TC-18). Done in step 16.

## Step 16 of 24: Welfare case workspace (Oct 31, 2026)

### What was built
The confidential workspace for the MSW Committee Head and the President: a queue sorted by priority then age, with filters, search and an overdue badge; a case page with acknowledge, classification, assignment, encrypted notes, referrals, follow-ups, resolution, closure, attachment disposition and a full timeline; a `welfare.access_denied` audit row on denied access; and `jobs/welfare_followups.php` with an hourly `--urgent` mode.

### Files created
- `app/Controllers/WelfareCasesController.php`
- `app/Models/`: `CaseNoteRepository`, `ReferralRepository`, `CaseFollowupRepository`, `CaseResolutionRepository`
- `app/Views/welfare/cases/`: `index`, `show`, `_overview`, `_notes`, `_referrals`, `_followups`, `_closure`, `_timeline`
- `jobs/welfare_followups.php`, `tests/WelfareCaseTest.php`

### Files changed
- `app/Core/Router.php`, `app/Services/WelfareService.php`
- `app/Models/`: `WelfareCaseRepository`, `WelfareAttachmentRepository`, `UserRepository`
- `config/routes.php`, `config/statuses.php`
- `docs/PROJECT_CONTEXT.md` (§9, §11), `public/assets/css/app.css`

### How to test
```
cd C:\laragon\www\agile
php -S localhost:8000 -t public
```
This step adds no tables, permissions, settings or templates. To run the jobs: `php jobs/welfare_followups.php` and `php jobs/welfare_followups.php --urgent`. To schedule them, run the first daily at 08:00 and the second hourly in Windows Task Scheduler (§10).

### Test results
These ran on throwaway databases, which were then dropped.
- PHPUnit: 136 tests pass.
- 234 HTTP checks pass, including forced access by five other roles, forced POSTs by the President, every action refused on a closed case, both job modes run twice, and a scan of emails, history and audit rows for case text.
- 288 browser checks pass with real clicks through a full case at 360, 768 and 1366px.
- The Step 5, 6, 8, 9, 10, 11, 12, 13, 14 and 15 suites still pass. The Step 9 suite ran with `APP_NAME=AGILE` set for that run. The Step 3 and 7 suites were not rerun.
- After the runs, every file referenced by `agile_ams` was checked against the disk. The only file still missing is the photo from the step 15 incident.

### Decisions made
- **`welfare.access_denied` is written in addition to `access.denied`,** by the router, for any denied `welfare.*` permission and for welfare attachment downloads.
- **Classification, assignment, resolution, closing and disposition use `welfare.assign_close`,** which only the MSW Committee Head has. No new permission was needed.
- **Acknowledge uses `welfare.handle`,** so the President can do it too. §7 and the step do not limit it to the MSW Head.
- **Subject search decrypts in memory,** because the step asks for it and SQL cannot search ciphertext. §11 now says so. It decrypts every case matching the other filters, which is fine at this size.
- **Referred and resolved are reached through in_progress.** §8 has no direct move from submitted, so a new case gets two status rows.
- **Classification remarks are saved as an encrypted internal note,** because history rows hold no free text. They do not count as the first note, so the case stays Submitted.
- **Only a visible note and closure notify the submitter,** as the step lists. Other status changes appear when they track the case, with no email.
- **A disposition is recorded once only.** `returned` and `kept` leave the file in place and downloadable, by the owner and handlers. `deleted` removes the file after the database commit, and the record stays.
- **"Read-only" after closure** means nothing about the attachments can change except recording that disposition. No screen adds files to a case.
- **Follow-ups are due until the end of the chosen day,** and handlers are limited to active MSW Heads and Presidents.
- **The overdue reminder is marked with an `overdue_reminder` history row,** because `welfare_cases` has no `reminder_sent_at`. It is sent once per case. §9 now lists the added event types.
- **Referral statuses can change in any direction,** because §8 has no map for them. A finished referral does not move the case back to in_progress.
- **Notes may still be added to a resolved case,** until it is closed. A referral may not, because §8 has no move from resolved to referred.

### Known issues
- **An MSW Head or President who submits their own case can open it in the workspace** and read internal notes on it. The same applies to the other handler's own case. Blocking it would leave an MSW Head's own case with nobody able to close it, so this needs a decision.
- The handler is not notified when assigned, because no template exists for it. They see it under Assigned to me.
- The timeline logs every view, as §11 requires, so views can outnumber other events on a busy case.
- A form that fails server checks does not keep what was typed, so confidential text stays out of the session. The browser's own required checks catch most of this first.
- `referrals.referred_to` is not encrypted, as decided after step 11. The form says so.
- The jobs are not scheduled yet.
- **While the throwaway test suites run, they share `storage/` with the real site.** Their cleanup removes files added during a run, including emails in `storage/mail`. The site should not be used during a run.
- Tested only in headless Chrome.

## Step 17 of 24: Semestral academic eligibility screening (Oct 31, 2026)

### What was built
Academic terms with one current term; Start Screening, which shows the covered officers before asking them for documents; a My Eligibility page with multi-file upload; a review screen with documents beside the verification form, applying BR-09; Confirm Ineligible or Clear decisions with `MembershipService::expireRole()`; `jobs/eligibility_reminders.php` and `jobs/role_expiration_reminders.php`; and notices on the dashboard and member profile.

### Files created
- `app/Services/EligibilityService.php`, `app/Controllers/EligibilityController.php`
- `app/Models/`: `AcademicTermRepository`, `EligibilityScreeningRepository`, `EligibilityDocumentRepository`, `EligibilityReviewRepository`
- `app/Views/eligibility/`: `index`, `start`, `mine`, `reviews`, `review`
- `jobs/eligibility_reminders.php`, `jobs/role_expiration_reminders.php`, `tests/EligibilityTest.php`

### Files changed
- `database/schema.sql` (new `eligibility_documents` table), `docs/database.md`, `docs/PROJECT_CONTEXT.md` (§9)
- `app/Services/`: `MembershipService`, `MemberRecordsService`, `FileAccessPolicy`
- `app/Models/NotificationRepository.php`
- `app/Controllers/`: `DashboardController`, `FileController`
- `app/Views/dashboard/_cards.php`, `app/Views/members/_profile.php`, `app/Views/members/show.php`
- `config/routes.php`, `config/menu.php`, `public/assets/css/app.css`

### How to test
```
cd C:\laragon\www\agile
php database/setup.php
php -S localhost:8000 -t public
```
`setup.php` must run first, because it creates the new table. It keeps existing data. To run the jobs: `php jobs/eligibility_reminders.php` daily at 09:00 and `php jobs/role_expiration_reminders.php` daily at 08:00, in Windows Task Scheduler (§10).

### Test results
These ran on throwaway databases, which were then dropped.
- PHPUnit: 150 tests pass, including every §8 screening transition and every BR-09 combination.
- 188 HTTP checks pass, including real uploads, forced access by other roles, a forced failure proving Confirm Ineligible rolls back as one transaction, a real QR check after it, and both jobs run twice.
- 236 browser checks pass with real clicks at 360, 768 and 1366px. The upload in that run was sent by script with a real file, because a browser test cannot pick a file. The HTTP checks cover the upload rules.
- The Step 5, 6, 8, 9, 10, 11, 12, 13, 14, 15 and 16 suites still pass. The Step 9 suite ran with `APP_NAME=AGILE` set for that run. The Step 6 and Step 12 suites needed their checks updated, for the new sidebar URLs and the replaced Notices placeholder. The Step 3 and 7 suites were not rerun.

### Decisions made
- **New `eligibility_documents` table,** because the step allows several files and `eligibility_screenings.document_path` holds one. That column stays unused. It is in §9 as an addition.
- **BR-10 is enforced:** the President verifies and decides any screening whose owner has the `msw_head` role, using `eligibility.decide_msw_head`. An MSW Head cannot open their own, and the President's own stays with the MSW Head. The President's review list shows only MSW Head screenings.
- **`/eligibility/review` has no route permission,** because two permissions can reach it. `EligibilityService` checks them, per screening.
- **Uploading again replaces the earlier files,** until verification. Uploads stay open through the grace days.
- **Eligible officers also get `ELIGIBILITY_RESULT`,** so they learn the outcome. The step lists only the flagged notice.
- **Starting a screening again adds only officers appointed since,** and nobody is asked twice.
- **The daily job's reminder reuses `ELIGIBILITY_REQUEST`.** `NotificationRepository::sentToday()` keeps a second run on the same day from sending twice, and the day a screening starts counts as already asked.
- **A role expiration reminder goes out only when the job runs exactly 7 or 1 days before the deadline.** If the job misses that day, that reminder is skipped.
- **An officer flagged by the job has no answers recorded.** The MSW Head can still Confirm Ineligible or Clear.
- **Member-record staff do not see eligibility notices on someone's record,** because §6 gives eligibility to the MSW Head only. The member sees their own.
- **`runReminders()` and `runRoleExpirationReminders()` have no permission check,** because the jobs run with nobody signed in. Controllers must not call them. `expireRole()` also leaves the check to its caller, like `changeRole()`.
- **Consent is required on the upload form** (§11).

### Known issues
- Switching the current term leaves anything still pending in the old term as it is. The jobs and My Eligibility follow only the current term. The confirmation dialog says so.
- A term's deadline cannot be edited on screen, and a term cannot be deleted.
- Grades and enrollment files are stored like application documents, not encrypted, because §9 lists only welfare data as encrypted.
- PDF preview in the side-by-side viewer was not confirmed visually. Headless Chrome paints its PDF viewer blank. Check it once in normal Chrome.
- The jobs are not scheduled yet.
- `tests/EligibilityTest.php` adds a term and starts a screening, so on a real database with a screening already started it could fail. It rolls back and changes nothing.
- Tested only in headless Chrome.

### Notes for later
- Step 18 fills the empty "Suggest values (AI)" panel in `app/Views/eligibility/review.php`. Done in step 18.

## Step 18 of 24: AI-assisted document extraction (optional) (Oct 31, 2026)

### What was built
Optional suggestions on the eligibility review: `ExtractorInterface` with `NullExtractor` (the default) and `ClaudeExtractor`; `AiExtractionService::suggest()`, which saves a suggested row only; suggested values and confidence beside the manual fields, each with "Use this value"; and marking the suggestion confirmed or discarded when the reviewer saves.

### Files created
- `app/Services/Ai/`: `ExtractorInterface`, `ExtractionResult`, `NullExtractor`, `ClaudeExtractor`, `ExtractorFactory`
- `app/Services/AiExtractionService.php`, `app/Models/AiExtractionRepository.php`
- `app/Views/eligibility/_ai_panel.php`, `public/assets/js/eligibility-review.js`, `tests/AiExtractionTest.php`

### Files changed
- `composer.json`, `composer.lock` (`anthropic-ai/sdk` 1.3, `symfony/http-client` 7.4, `nyholm/psr7` 1.8)
- `config/app.php`, `config/routes.php`, `.env.example`
- `app/Services/EligibilityService.php`, `app/Controllers/EligibilityController.php`, `app/Views/eligibility/review.php`
- `docs/PROJECT_CONTEXT.md` (§3), `public/assets/css/app.css`

### How to test
```
cd C:\laragon\www\agile
composer install
php -S localhost:8000 -t public
```
AI stays off with no `.env` change, and the review screen says so. To turn it on, add `AI_PROVIDER=anthropic` and `AI_API_KEY=...` to `.env`. Use seeded documents only, because this sends them to Anthropic and each call costs money.

### Test results
These ran on throwaway databases, which were then dropped.
- PHPUnit: 158 tests pass, including that a suggestion leaves the screening row identical and that saving uses the form, not the suggestion.
- 71 HTTP checks pass, with AI off and against a local stand-in for the API: the request sent, the screening row unchanged until Save, bad JSON, a refusal, a 500, a bad key, a real 30-second timeout, and a misspelled provider sending nothing.
- 31 browser checks pass with real clicks at 360, 768 and 1366px.
- **The real Anthropic API was not called,** because no key is configured. The stand-in shows what the app sends and how it handles replies, not that the API accepts the request or how accurate the values are.
- The Step 5, 6, 8, 9, 10, 11, 12, 13, 14, 15, 16 and 17 suites passed on the full run. After a small display fix, the Step 17 and 18 suites were rerun and pass. The Step 3 and 7 suites were not rerun.

### Decisions made
- **The provider is Anthropic's Claude,** called through its official PHP SDK, with `claude-opus-5-5` by default (`AI_MODEL`). The step leaves the choice open.
- **Three new Composer libraries,** used only by this optional feature. `symfony/http-client` and `nyholm/psr7` are there because the SDK needs an HTTP client, and it leaves the timeout to that client. §3 lists them as additions.
- **Server-side refusal fallback is on** (`fallbacks: 'default'`), so a declined request can retry on another Claude model. A final refusal falls back to manual entry.
- **No retries,** so the wait stays at 30 seconds per document.
- **Every document in the screening is read,** one call each. In the merged result a failing grade in any document counts, and the lowest confidence stands. If any document fails, there are no suggestions.
- **Confidence is the model's own estimate,** and the screen says so. The step's JSON list did not include it, so it was added to the request.
- **`msw_head` only, as the step says,** using `eligibility.manage`. The President sees no AI panel, and an MSW Head cannot use it on their own screening.
- **"Confirmed" means the reviewer pressed "Use this value" before saving.** Otherwise the suggestion is marked discarded. Either way the saved answers come only from the form.
- **Any provider other than `anthropic` means off,** so a typo cannot send a document anywhere. A suggestion saved earlier is not offered once AI is off.
- **The model is told to ignore instructions inside a document,** and its answer can only fill the fixed fields, shown escaped.
- **Failures show one friendly message.** The audit log records only the failure type, never document content.

### Known issues
- Not tried against the real API, as above.
- **Using it with real student data needs approval from AGILE and the University.** The screen warns about this. It also needs an entry in the Privacy Notice, which does not yet mention an outside AI provider.
- `ai_extractions.extracted_json` holds names and grades in plain text, like the documents it came from. Nothing deletes old rows.
- Each document can take up to 30 seconds, so a screening with five files can take over two minutes, and the page waits.
- Tested only in headless Chrome.

### After step 18
AI was turned off in `.env` at the owner's request (`AI_PROVIDER=none`, no key saved). The code and libraries stay in the project.

## Step 19 of 24: Personalized dashboards, search and reports (Oct 31, 2026)

### What was built
Real dashboards for all seven roles, with number cards, pending tasks, latest notifications and announcements; self-hosted Chart.js charts with counts only; an All Modules page; a top bar search limited to the modules each user can open; and reports for the MSW Committee Head (membership, recruitment, welfare statistics, eligibility per term) with CSV and PDF exports that are audit-logged.

### Files created
- `app/Services/`: `DashboardService`, `ReportService`, `SearchService`
- `app/Models/`: `DashboardRepository`, `ReportRepository`
- `app/Controllers/`: `ReportsController`, `SearchController`
- `app/Views/dashboard/`: `_numbers`, `_feeds`, `_charts`, `_overview`, `_welfare_status`, `overview`
- `app/Views/reports/`: `index`, `pdf`; `app/Views/search/index.php`
- `public/assets/js/dashboards.js`, `public/assets/vendor/chartjs/` (Chart.js 4.4.9, from npm), `tests/ReportServiceTest.php`

### Files changed
- `app/Controllers/DashboardController.php`, `app/Models/WelfareCaseRepository.php`
- `app/Views/dashboard/`: `_cards`, `applicant`, `general_member`, `committee_head`, `msw_member`, `msw_head`, `president`, `execomm`
- `app/Views/layouts/dashboard.php`, `config/routes.php`, `public/assets/css/app.css`

### How to test
```
cd C:\laragon\www\agile
php -S localhost:8000 -t public
```
This step adds no tables or permissions. Log in as each seeded role and open the dashboard. As MSW Committee Head, open Reports.

### Test results
These ran on throwaway databases, which were then dropped.
- PHPUnit: 166 tests pass.
- 295 HTTP checks pass, comparing dashboard numbers with the database, checking every role's access, 5 attack strings on 10 lists for TC-22, tampered sort values, and real CSV and PDF exports with their audit rows.
- 376 browser checks pass at 360, 768 and 1366px, including charts drawing real pixels under the site's strict CSP, and no script running from a search for `<img src=x onerror=alert(1)>`.
- All four PDFs were read for the AGILE header, and the membership PDF was checked by eye.
- The Step 5, 6, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17 and 18 suites still pass. The Step 9 suite ran with `APP_NAME=AGILE` set for that run. The Step 6 suite's check for placeholder cards was updated. The Step 3 and 7 suites were not rerun.
- The CSV was checked by its bytes and contents, not opened in Excel itself.

### Decisions made
- **Search shows only what the user's own lists show,** because it calls the same services. Execomm, General Members and Applicants have no search module, so they get no box, and `/search` returns 403.
- **Welfare search matches the case code only** and returns only the code and status. Searching does not count as viewing a case.
- **Exports are POST requests with a CSRF token,** so a link cannot create an export or an audit row. On-screen previews are not logged as exports.
- **CSV cells starting with `=`, `+`, `-` or `@` get a leading apostrophe,** so a name typed as a formula cannot run in Excel.
- **The welfare report has counts by category, status and priority only.** Small counts could still point to someone in a small group, so exports should be shared with care.
- **The eligibility report has results and dates, not decision remarks.**
- **The membership report leaves out email and contact numbers,** to collect only what is needed.
- **All Modules (`/dashboards`) is now a real page,** because its placeholder sat beside the real dashboards.
- **Charts are horizontal bars,** and each has a "Show the numbers" table for screen readers.
- **Execomm sees welfare counts only,** by status and category, with no case codes or links. President and MSW Head also get counts by priority, plus their own notifications, which can hold a case code.
- **Every list was checked for prepared statements and fixed sort columns.** The only sort taken from a URL is on Applications, which uses a fixed list. No code change was needed.

### Known issues
- Seeded data has no applications or welfare cases, so many seeded cards and charts show 0 until records exist.
- A dashboard's numbers come from separate queries on each load. This is fine at this size.
- The dashboard "View only" badge stays on the President's page, as in step 6, although the President handles welfare cases from the queue.
- Long chart labels are shortened on phones. The table beside each chart has them in full.
- `/my-submissions` is still a placeholder.
- Tested only in headless Chrome.

## Rework before Step 20: applying without an account, who decides, account approval, and the look (Oct 31, 2026)

The owner uploaded a new PROJECT_CONTEXT and then added rules for who decides and for account approval. The new file is merged into `docs/PROJECT_CONTEXT.md`; everything built earlier stays, as marked additions.

### What changed
- **Apply without an account (BR-18).** `/apply` is public: name, email and student number, then a 6-digit code, then the same six steps. New tables `applicants` and `application_access_links`. `membership_applications.user_id` is now nullable, with `applicant_id` beside it. The `applicant` role and its dashboard are gone.
- **Drafts and revisions by emailed link.** A draft link goes out when the draft is created and when one is saved with no live link. `/track` has "Email me an edit link" for an application returned For Revision. Links open one application, work once and expire in 72 hours. Opening a link shows a button first, because mail scanners open links. Code and email alone never allow editing.
- **Who decides (BR-20).** The head of the vacancy's committee decides its applications. The MSW Head decides general membership, executive and MSW positions, committees with no head, and heads applying to their own committee. Others see no buttons and a forced attempt returns 403 and is logged. The decider is emailed when the application is under review and every required document is verified. Accepting is not appointing: the MSW Head still approves the appointment.
- **Account approval (BR-21).** `/register` is back, on the owner's decision, with an email code first. Every new account is pending, including those created when an application is approved. The MSW Head uses "New accounts for approval" at `/accounts/approvals`. A rejection needs a reason.
- **Design (section 12.1).** Three self-hosted fonts in `public/assets/fonts/`, one type scale, PUP maroon with one gold rule, 4px corners, shadows only on dropdowns, modals and toasts. Sections are separated by rules and space, not cards. Dashboard tiles became one list of figures. No welcome card, no sidebar or button icons. Dense tables with sticky headers. PH date format. Page titles name the job. The login robot stays, as the owner decided.
- **Migration.** `database/migrations/003_accounts_only_on_approval.php` moves people with the old applicant role to `applicants`. Their user rows stay, disabled, with a placeholder email and no student number, because the audit trail points at them and the real email must be free.

### Decisions made
- **People without an account apply for general membership only.** Positions need a login, as section 6 says.
- **A registered account with no membership yet has the general_member role and no member record.** It can log in once approved and apply for general membership from there. Its list row says "No approved application".
- **/register confirms the email with a code before saving anything,** so nobody can hold someone else's address and later receive their account.
- **Someone pending who applies through /apply keeps that account:** approval uses it instead of creating a second one.
- **A person's earlier guest welfare cases join their account when the account is approved,** since both sides proved the email with a code.
- **An extra seeded user, `nomember@agile.test`,** shows a registered account with no membership. The tests use it too.

### Known issues
- A committee head who decides cannot open the documents themselves, since that needs `document.verify`. They see which ones are still missing.
- An applicant with an approved application and a pending account has no password yet, so they cannot see the login message. Their email and `/track` tell them instead.
- PDFs and emails keep their own fonts, because their renderers do not load the site's.
- Real event photos are not in yet, so the home page has none.

## Alignment check and fixes (Oct 31, 2026)

Full findings are in `docs/alignment-report.md`. Row numbers below refer to it.

### What changed
- **Row 1:** the email queue now erases set-password and edit-link tokens after sending. Migration 004 clears ones already stored.
- **Row 2:** `/track/edit-link` obeys and counts toward the same limit as `/track`.
- **Row 4:** BR-02 for general membership checks email and student number across accounts and applicants.
- **Row 5:** the `/apply` start form has a required consent checkbox, recorded in the audit log. Step 6 still records `consent_at`.
- **Row 6:** applicant sessions end after the same 30 idle minutes as a login.
- **Row 7:** applicants' uploads are served by `FileController`, at `/apply/files/{id}`.
- **Row 8:** while an application is Submitted, `/track` offers "Email me a withdrawal link". The link works once, expires in 72 hours and shows a confirmation first.
- **Row 9:** a CHECK constraint requires exactly one of `applicant_id` and `user_id`.
- **Row 10:** rejecting an account revokes any ID and certificate. The member record stays, as BR-13 says.
- **Row 13:** `php jobs/run.php all` runs every job in turn. Job names can be given instead of `all`.
- **Row 16:** tests added for tokens, the limit, BR-02, the CHECK constraint, revoking, and guest cases joining an account. The suites that stopped early were updated.

### Files
- **New:** `database/migrations/004_alignment_fixes.php`, `jobs/run.php`, `app/Views/membership/apply/withdraw.php`, `docs/alignment-report.md`.
- **Changed:** `EmailQueueService`, `TrackingService`, `TrackController`, `MembershipApplicationRepository`, `MembershipService`, `ApplicantAccessService`, `ApplyController`, `FileController`, `FileAccessPolicy`, `AccountApprovalService`, `config/routes.php`, `app/helpers.php`, `database/schema.sql`, `portal/track`, `membership/apply/start`, `step5`, `_summary`, `tests/AccountsAndDecisionsTest.php`, `docs/PROJECT_CONTEXT.md`.

### Test results
PHPUnit 179 tests pass. Rework suite: HTTP 157 and browser 688 pass. Migrations 003 and 004 were tested on a copy of the real database. The earlier suites are summarized in the report.

### Incident
An old Step 7 test runner, rerun without its delete lines being read, removed everything in `storage/uploads`. The welfare attachment for case `574ZHA8FZ239` and an eligibility upload were restored from the owner's Downloads folder and verified. The line was removed from every copy of the runner.

### Decisions made
- **Rows 8, 10 and 13 used the recommended options,** since "fix" did not name others.
- **Row 11 keeps the code:** approval creates a pending account, as BR-18 and BR-21 say.

### Known issues
- **The real database needs `php database/setup.php`** (row 3). Until then `/apply`, `/register` and approvals fail.
- **Row 12** (credentials approval), **row 14** (BR-19, TC-33) and **row 15** (R and P prompts) need text from the owner.
- The Low rows in the report are still open, including `docs/database.md` and the README.
- The test suites share `storage/` with the real site, so nobody should upload while they run.

## UPDATE R1: Applicants and guests have no account and no profile (Oct 31, 2026)

Most of this was already built in the rework before Step 20: no applicant role, public `/apply`, draft, edit and withdrawal links, BR-02. R1 added the rest.

### What changed
- **Approval creates a member with no account.** `members.user_id` can be NULL, with new `applicant_id` and `registered_at`. Member pages, lists, reports and credentials read the name from the application until they register. APP_APPROVED tells them to register.
- **`/register` is only for approved applicants.** It asks for the PUP student number, sends MEMBER_REGISTRATION_CODE to the email on the application, then asks for a password and consent. It creates the general_member account and links the member record, the applicant and earlier guest welfare cases. Every number gets one message, and it is rate limited. The open form is gone.
- **Officer accounts:** `AuthService::createAccount()` and `php jobs/create_user.php email first last role [student_no]`, with the ACCOUNT_SETUP set-password link.
- **Public wording:** the nav says "Apply" and "Member log in". Logged out, Ask for Help goes straight to the guest form. `/track` tells an approved applicant to register.
- **Migration:** `database/migrations/2026_10_31_no_applicant_accounts.sql`. `setup.php` now runs `.sql` migrations too.

### Files
- **New:** `app/Services/MemberRegistrationService.php`, `app/Views/auth/register_password.php`, `jobs/create_user.php`, the migration above.
- **Changed:** `AuthController`, `AuthService`, `AccountApprovalService`, `GuestVerificationService`, `EmailQueueService`, `MembershipService`, `MemberRecordsService`, `TrackingService`, `PortalController`, `MemberRepository`, `MembershipApplicationRepository`, `ReportRepository`, `auth/register`, `auth/register_verify`, `auth/login`, `layouts/public`, `membership/apply/start`, `members/show`, `members/_profile`, `portal/track`, `config/routes.php`, `database/schema.sql`, `database/setup.php`, migration 003, `database/seeds/email_templates.php`, `jobs/run.php`, `tests/AccountsAndDecisionsTest.php`, `docs/PROJECT_CONTEXT.md`, `docs/database.md`, `README.md`.

### Decisions made
- **Registered accounts wait for the MSW Head.** R1's DONE WHEN says "logged in as General Member", but BR-21 in the context file says the account is pending, and the context file wins. They log in once it is approved.
- **R1 replaces the owner's earlier choice to bring back the open `/register`,** because it is newer and explicit.
- **The `.sql` migration holds only what migrations 003 and 004 had not done.** Those two already created the tables, moved applicant users and removed the role.
- **Accounts the earlier approval flow made with no password are retired,** and those people register instead.
- **Rejecting an account leaves that person unable to register again,** because the member record still points at the disabled account.

### Known issues
- `nomember@agile.test` stays in the seeded users because the tests need a user with no member record. A logged-in user with no member record can still apply for general membership, which should no longer occur for real people.
- Rows 12 and 14 in `docs/alignment-report.md` still need text from the owner. R2, R3, R4 and P1 have not been received as prompts, though the rework may already cover parts of them.

## UPDATE R3: Approved applicants register their own account (Oct 31, 2026)

R1 already built approval with no account and the three-step `/register`. R3 added the rest.

### What changed
- `AuthService::registerMember()` creates the account. The generic message and APP_APPROVED use R3's wording. The login page and `/track` use its wording too.
- `/register` is limited per IP and per student number: the 6th try in 15 minutes is blocked.
- Members list: filter "Not yet registered". Member page: "Resend registration instructions", for the MSW Head.
- `jobs/application_reminders.php` reminds people to register 7 and 14 days after approval. `members.registration_reminders` keeps it from sending twice.
- Seeded one approved applicant who has not registered: Lito Ramos, `2024-00114-OU-0`.
- Migration: `database/migrations/2026_10_31_member_self_registration.sql`.

### Decisions made
- **Registering creates a pending account, not an active one.** R3 says active and logged in, but the owner chose to keep BR-21 and TC-35 when asked. After the MSW Head approves, the approval email links to the profile, through the login page.
- **The reminder job holds only the registration reminder.** The other two parts of `application_reminders.php` in section 10 (applications pending over 3 days, For Revision) belong to a later step.
- **Resending uses `admin.users`,** the MSW Head's permission for managing accounts, because section 6 has no separate code for it.

### Known issues
- The seeded applicant has no ID or certificate, because seeding does not generate PDFs. The MSW Head can issue them from the member page. Real approvals generate them.
- R2, R4 and P1 have not been received as prompts. Credentials still appear in the profile with no separate approval step (R3 item 7 applies only if R2 is done).

## UPDATE R2: Digital ID and certificate need MSW Head approval (Oct 31, 2026)

### What changed
- **New credentials wait.** Approval, appointment, role expiry and manual reissue save them as pending, with no email to the member.
- **`CredentialService::approve()`** regenerates each PDF with "Approved by {name}, {date}", sets the issue date to the approval date, revokes what it replaces, and emails each member once. There is an audit row per credential plus one `credential.bulk_approve`.
- **`returnForCorrection()`** needs remarks. **`regenerate()`** makes a new pending file from the member's current details.
- **Replacement rules:** a role change or expiry revokes the old ones at once. A manual reissue keeps the approved one valid until the new one is approved.
- **Screen:** "IDs and certificates for approval" at `/credentials/approvals`, with a sidebar count, filters, preview beside the list, row actions, "Approve selected" and "Approve all pending (N)".
- **Member side:** the review message while anything is pending or returned, and downloads with issue date and serial for approved ones.
- **Files:** the owner gets approved, unrevoked copies only. The MSW Head can open any status.
- **`/verify`:** "Valid", "Not Yet Issued" with no personal details, or "Revoked on {date}".
- **`jobs/credential_digest.php`** tells the MSW Head the count, once a day. `docs/scheduling.md` lists every job.
- **Migration:** `database/migrations/2026_10_31_credential_approval.sql`. Existing credentials become approved.

### Files
- **New:** `CredentialApprovalsController`, `app/Views/credentials/approvals.php`, `public/assets/js/credential-approvals.js`, `jobs/credential_digest.php`, `docs/scheduling.md`, `tests/CredentialApprovalTest.php`, the migration.
- **Changed:** `CredentialService`, `CredentialRepository`, `FileAccessPolicy`, `FileController`, `MemberRecordsService`, `MembersController`, `DashboardService`, `MembershipService`, `config/routes.php`, `config/menu.php`, `config/permissions.php`, `database/schema.sql`, `database/seeds/email_templates.php`, `layouts/dashboard`, `members/_profile`, `members/show`, `portal/verify`, `credentials/id_card`, `credentials/certificate`, `app.css`, `tests/MemberRoleTest.php`, `tests/AccessMatrixTest.php`, `docs/PROJECT_CONTEXT.md`, `docs/database.md`.

### Decisions made
- **Added `digital_credentials.reason`,** because the screen shows and filters by it and R2's column list had nowhere to keep it.
- **A credential under review shows no name on `/verify`,** because it does not count yet.
- **The approved file keeps its serial number and QR token,** so anything already printed from the preview still resolves.
- **Someone approved but not registered still gets CREDENTIAL_READY,** with a link to create their account, since that is how they will see the files.
- **Returning a replacement does not touch the approved one it would replace,** which stays valid.

### Known issues
- Previews rely on the browser's built-in PDF viewer, so a phone may download the file instead.
- The preview frame is out of the tab order, because Chrome shows no focus ring on a frame. Keyboard users load a preview from the list, and can open that link in a new tab to scroll the PDF.
- Dashboards show the pending count as a figure in the MSW Head's list. The Step 6 and 19 suites check older titles, as noted in the report.

## UPDATE R4: Committee heads accept their own applicants; MSW Head approves new accounts (Oct 31, 2026)

### What changed
- **`application.decide`** now includes committee heads, and `application.decide_committee` is gone. `approve()` and `reject()` check it and that the user is `MembershipService::decider()`.
- **Application page:** "Decision by: {name}, {committee} Committee Head" while it is open. Only the decider gets Approve and Reject.
- **"Applications to my committee"** at `/applications/my-committee`, with status and position filters. The head of the committee can read its documents and interviews.
- **Wording:** "Accepted by the {committee} Committee" on `/track` and in the approval email.
- **New accounts:** Approve selected, email and registered date on the list, a count in the sidebar, and `users.approved_by`, `approved_at`, `rejection_remarks`.
- **Rejecting an account** disables and frees it, so the student number may register again. Guest concerns go back to guest tracking. Approving a later registration generates whatever credentials are missing, for review.
- **`jobs/account_digest.php`** sends ACCOUNTS_FOR_APPROVAL once a day when anything is pending.
- **Seeds:** Rosa Garcia, a registered account waiting (`rosa.garcia@agile.test`), and `DEMVACANCY01`, a member's application to an Academic position, ready for the committee head.
- **Migration:** `database/migrations/2026_10_31_committee_decisions_account_approval.sql`.

### Decisions made
- **Acceptance is not the appointment.** R4 says it is, but its note asked the owner to confirm, and the owner answered "No, MSW Head still approves". So the head accepts, then the MSW Head approves the appointment, and the position and `slots_filled` change at that point.
- **The button says "Accept" on committee applications and "Approve" on the MSW Head's,** matching how R4 words each rule.
- **Committee heads can still evaluate.** R4 says their view is read-only except the decision panel, but BR-05 keeps them as evaluators.

### Known issues
- Older step suites still check earlier wording and flows, as the alignment report notes.
- Fixed along the way: the seeded seeded documents could not be opened, because the file service refuses their old names. The migration corrects those rows.
- Dashboard counts of applications to decide use the committee's head even if that account is disabled, while `decider()` falls back to the MSW Head. This should be rare until the Administration step.

## POLISH P1: Make the design system look hand-made (Oct 31, 2026)

### What changed
- **Fonts:** IBM Plex Sans 400/500/600 and 400 italic, IBM Plex Mono 400/500, self-hosted in `public/assets/fonts/`. The earlier Libre Franklin and Source Sans files are gone.
- **Tokens:** ink chrome, canvas and surface, cool grays, maroon, maroon-on-dark, gold and muted semantic colors in `:root`. Radius 6px.
- **Components:** flat buttons, dense tables with hairline rows and right-aligned numbers, badges as a dot and sentence case text, cards only for real objects, one-sentence empty states with an action where one exists.
- **Layouts:** dark public header and home band with a line grid, footer with TODO markers, dark sidebar with small icons and a gold active bar, white top bar with mono path and search with the `/` shortcut.
- **Helpers:** `date_ph()`, `time_ph()` and `datetime_ph()`, used in all views.
- **`/styleguide`** shows the new type and ink colors.
- **`docs/PROJECT_CONTEXT.md` §12.1** now records this direction, so the file and the code agree.

### Decisions made
- **Followed P1 where it differed from §12.1,** because P1 is newer. The file had no ink, canvas or maroon-on-dark values, so these were chosen and written into §12.1. They all live in one `:root` block.
- **`logo.png` stays.** P1 mentions `logo.svg`, but the project only has `logo.png` and `logo-large.png`.
- **The robot keeps its shapes and offset shadows,** as the owner decided. Its gradients became an SVG and a plain hand.
- **`--radius-full` is now 6px,** so nothing is a pill. Small round things such as stepper markers, avatars and badge dots stay round.

### Notes for later
- The footer has `TODO` markers for the PUP OUS address and the Facebook page. The home band has `TODO: real photo`.
- `git diff --stat` cannot run, because the repository has no commits. Changes stayed within CSS, JS, views, fonts, images, `helpers.php` and the docs.
- PDFs and emails keep their own fonts, because their renderers do not load the site's.
- Chart.js labels still use its default font, which needs a small change in `dashboards.js`.
- Older step suites check earlier wording and badge capitals.
- Chart labels and some filter dropdowns still show capitals such as "In Progress", because services and config supply those words. Changing them is a logic change.
- `grep gradient` still finds `public/assets/vendor/chartjs/chart.umd.js`, and `blur(` finds `public/assets/vendor/quill/quill.js`. These are library code, not styling, and were left unedited.
- Results: PHPUnit 195 tests OK, HTTP 232 passed, browser 805 passed, all on the scratch database.

## UI G1: Gentelella v4 look with our stack (Oct 31, 2026)

### What changed
- **CSS split:** `tokens.css` (fonts, all colors, light and dark), `components.css`, `layout.css`. `app.css` is gone, along with the rules these replace.
- **Shell:** sidebar with a 64px icon rail (remembered), top bar with breadcrumb, search, dark-mode toggle, bell, inbox and avatar. Page header with eyebrow.
- **JS:** `theme-init.js` (before paint), dark mode, sidebar rail, `/` and Ctrl+K in `app.js`, charts in theme colors in `dashboards.js`.
- **Dashboards:** "Needs your action" first, stat cards, area chart with a real month-over-month chip, Recent activity from the user's own notifications.
- **Helpers:** `time_ago()` and `initials_avatar()`.
- **Docs:** §12 and §12.1 in `PROJECT_CONTEXT.md`; new `docs/sad-updates.md`.

### Decisions made
- **The theme script is a file, not inline.** The CSP blocks inline scripts, and loosening it for this would weaken §11. A blocking file does the same job.
- **The range toggle shows 3 or 6 months, not 7/30/90 days.** No JSON endpoint or daily data exists, and services are out of scope. It only narrows real months already on the page.
- **Sparklines are in the style guide only,** because no dashboard has daily counts yet.
- **Gold focus ring has a thin dark inner line.** Gold alone is too faint against white.

### Notes for later
- Daily counts in `DashboardService` would allow sparklines, and an endpoint would allow 7/30/90 days.
- Recent activity uses the user's own notifications. Audit log rows need a service method with permission filtering.
- `pending_approval` has no entry in `config/statuses.php`, so its pill shows as neutral with a generated label.
- `/my-submissions` behind the inbox icon is still a placeholder page.

- Results: PHPUnit 195 tests OK, HTTP 236 passed, browser 897 passed, all on the scratch database.

### Data correction (Oct 31, 2026)
- At 3:31 PM the President account confirmed Elena Ramos (MSW Committee Head, user 8, member 6) ineligible by accident, so no account held the `msw_head` role. The account itself was not deleted.
- Undone directly in the database, because only an MSW Head can approve an appointment: role, position and role status restored, and screening 5 returned to `submitted`. History and audit rows were kept, and one row of each records the correction. Backup: `storage/backups/before_msw_head_restore_20261031_133405.sql`.
- Still open: credentials 1 and 2 (General Member ID and certificate, `pending_approval`) came from that decision and should be returned, not approved.
- Notes for later: nothing stops the system from ending the only MSW Committee Head's role. A guard belongs in `EligibilityService` or `MembershipService::expireRole()`.

## EMAIL E1: AGILE's email header, footer and wording (Oct 31, 2026)

### What changed
- **Wrapper:** `app/Views/emails/layout.php` now has the banner, body, signature, gold rule and maroon footer, 600px wide, tables and inline styles only.
- **Banner:** `App\Services\Mail\EmailBanner`. `SmtpMailer` embeds it with `addEmbeddedImage()`, and `LogMailer` previews use a relative path. If the file is missing it falls back to `MAIL_LOGO_URL`, then a text header.
- **Settings:** `org.full_name`, `org.facebook_url`, `email.signatory_name`, `email.signatory_position`, `email.signatory_committee`. `org.contact_email` already existed.
- **Templates:** every body rewritten in the house style in `database/seeds/email_template_text.php`. Codes, subjects, placeholders, recipients and timing are unchanged. Migration `2026_10_31_email_house_style.php` updates older databases and skips anything already in the new style.
- **`jobs/test_notify.php`:** now uses `member@agile.test`, because the seeded applicant it looked for was retired in UPDATE R1.
- **Tests:** `tests/EmailWrapperTest.php`.

### Decisions made
- **Templates use the values callers already pass.** The renderer fails an email with a missing value, and new values would need service changes. So INTERVIEW_INVITE shows `{scheduled_at}`, `{mode}` and `{location}`, not separate date, time and interviewers, and it does not name the position.
- **HR_REQUEST_SUBMITTED still goes to the reviewers,** because who gets which email must not change. It is written for them and has no `{request_code}`.
- **Guest code emails open "Dear Student," or "Dear AGILE Member,"** because their callers pass no name.
- **No "Full name" line in the details block,** because `{name}` holds only the first name.
- **`2026_10_31_no_applicant_accounts.sql` now skips APP_APPROVED once it is in the house style.** It sorts after the new migration and would have restored the old wording on every setup.

### Notes for later
- `public/assets/img/email-banner.jpg` was not in the project when this was built, so emails currently use the text header.
- The signatory is the seeded `Elena Ramos`, and `org.contact_email` is the seeded `contact@agile.test`. `/admin/settings` is still a placeholder, so these are edited in the `settings` table for now.
- `org.facebook_url` is stored but not shown, because the banner already carries the Facebook page name.

## Second alignment check and fixes (Oct 31, 2026)

The full report is in `docs/alignment-report.md`. Verdict: ready for step 20. The owner fixed all High and Medium rows by replying "fix".

### What changed
- **Lost files:** the eligibility PDF and credentials 3 and 4 are back. No database rows changed.
- **BR-10:** the only active MSW Committee Head cannot lose the role, by ineligibility or by appointment. Decided by the owner, and recorded in `docs/PROJECT_CONTEXT.md`.
- **`jobs/application_reminders.php`:** now also reminds MSW staff about applications waiting over 3 days, and reminds people whose application is For Revision. Each at most once a day. Without an account, the person gets a fresh edit link, which replaces the old one.
- **`authorize_any()` in `app/helpers.php`:** a permission check on `CredentialService::issueFor` and `reissue`, and `MembershipService::changeRole`, `expireRole` and `ensureMember`.
- **Tests:** new `tests/RemindersAndGuardsTest.php`.

### Incident
Test cleanup deleted real files because it treated anything new since the run started as a test leftover, and the owner was using the site during those runs. Lost: an eligibility PDF, a profile photo and two credential PDFs. Cleanup in the current runner now moves leftovers to a holding folder and never moves a file the real database references. Older runner copies in the scratchpad still contain deletes, so they must not be rerun without changing them.

### Known issues
- The owner must upload the `mswmember@agile.test` photo again.
- *(Step 21 changed this: the For Revision reminder now comes every 3 days.)*
- The 15 Low rows in the report are still open.

## POLISH P2: Human-made pass, all eight areas (Oct 31, 2026)

Views, CSS, JS and visible text only. No logic, routes, permissions or database changes. The one PHP text change outside a view is the News intro in `PortalController.php`.

- **Wording:** page titles and headings in sentence case. Buttons say what happens ("Mark as verified", "Approve appointment", "Save and finish later", "Email me a reset link", "Clear this officer").
- **Layout:** HR request, vacancy, appointment, member record and My account pages have a main column and a side summary at 1200px and up. Articles are left-aligned. Label and value rows line up. Long table values are cut with a tooltip.
- **Details:** "Last login" on My account, "Last updated" on the Privacy Notice.
- **Emails:** no wording change, because EMAIL E1 rewrote them today.

### Notes for later
- Sidebar and public navigation labels ("My Account", "HR Requests", "Track Status") still use capitals, because they are in `config/menu.php` and tests and §12 refer to them.
- Service error messages were not reworded.
- The Privacy Notice's last-updated date is fixed text, so change it when the notice changes.

## Step 19 update: gaps against the revised prompt (Oct 31, 2026)

The owner kept the UI G1 look, so stat cards, the area chart, the trend chip and four charts stay, as §12.1 says. Only what was missing was added.

- **Greeting:** "Good morning, Ana", today's date and the current semester, on each role dashboard.
- **Needs your action:** each task says how long it has waited, oldest first ("3 documents waiting for verification, oldest 4 days"). Unacknowledged welfare cases now appear for the MSW Head and the President.
- **Latest applications** table for the MSW Head and MSW Members.
- **Charts:** the applications chart title gives the time range and follows the 3 or 6 month toggle. "View as table" replaces "Show the numbers". Axes are labeled.
- **Unchanged and checked:** reports with BOM CSV, PDF header and audit log; global search; execomm read-only.
- **Files:** `DashboardRepository.php`, `DashboardService.php`, `dashboard/_cards.php`, `_charts.php`, new `_latest_applications.php`, `msw_head.php`, `msw_member.php`, `dashboards.js`.

### Known issues
- Step 19's "at most 3 charts" and "bar charts" are not followed, by the owner's choice to keep G1.

## Step 20 of 24: Administration and communications (Oct 31, 2026)

### What was built
- **Users** (`/admin/users`): search and filters, create officer accounts through `AuthService::createAccount()`, change role, disable and enable, unlock, force a password reset, extra permissions. At least one active MSW Committee Head always stays, and an MSW Committee Head cannot remove their own access. "Members not yet registered" links to the existing Members filter and resend action.
- **Roles and permissions** (`/admin/roles`): a matrix with sticky headers. The MSW Committee Head's `admin.users`, `admin.settings`, `audit.view` and `backup.manage` are locked on, also on the server. Changes are logged with before and after.
- **Committees and positions** (`/admin/committees`): add, edit, delete, set the head. Setting a head goes through `MembershipService::changeRole()`, so the position, role, history and new credentials follow, and the BR-10 guard applies. Anything in use cannot be deleted, and a position in use keeps its type.
- **Email templates** (`/email-templates`): edit subject and body with Quill or Edit HTML, the placeholders for each template, a preview in the real wrapper, and Send test to me. Placeholders the code cannot fill are refused on save.
- **Email drafts** (`/emails`): write to all members, officers, applicants being processed, or a committee; save; see the recipient count; send, which queues one email per person using the new `ANNOUNCEMENT` template with the unsubscribe note.
- **Settings** (`/admin/settings`): organization, email signature, system values and a rubric editor, checked by type. `session.idle_minutes` now takes effect, with `SESSION_IDLE_MINUTES` as the fallback.
- **Audit log** (`/admin/audit-logs`): filters, pretty details and a logged CSV export. No route changes or removes a row.

### Files
- **New services:** `UserAdminService`, `RoleMatrixService`, `CommitteeAdminService`, `SettingsService`, `EmailTemplateService`, `EmailDraftService`.
- **New controllers:** `AdminUsersController`, `AdminCommitteesController`, `AdminSystemController`, `EmailAdminController`.
- **New:** `app/Models/EmailDraftRepository.php`, `app/Views/admin/*`, `app/Views/emails/admin/*`, `public/assets/js/email-editor.js`, `tests/AdministrationTest.php`.
- **Changed:** `config/routes.php`, `config/menu.php`, `TemplateRenderer`, `HtmlSanitizer`, `AuthService`, `MembershipService`, `CredentialService`, the user, permission, committee, position, setting, template and audit repositories, `app/helpers.php`, `AuthMiddleware`, `ApplicantAccessService`, `components.css`, the template seed, §10.

### How to test
Run `php database/setup.php` to add the `ANNOUNCEMENT` template. Then follow the manual checklist.

### Known issues
- Backups (`/admin/backups`) are still a placeholder.
- Changing a role on the user page does not change their position. Positions change through Committees and positions, or an appointment.
- Editing a template in the rich text editor flattens the details block and button; Edit HTML keeps them.
- A sent draft shows as Sent when its emails are queued, not when the job finishes sending.
- Public pages read `ORG_CONTACT_EMAIL` from `.env`, not `org.contact_email`.


### Step 20 test results (Nov 1, 2026)
PHPUnit 227 tests and 2273 assertions, HTTP 277 passed, browser 1176 passed, all on the scratch database. `config/statuses.php` also gained `locked`, `disabled` and `pending_approval`. Not tested with a real inbox: the announcement and test emails ran on the log driver.

## Step 21 of 24: Background jobs, scheduling and backups (Nov 1, 2026)

### What was built
- **Every job** now runs through `job_run()` in `jobs/_bootstrap.php`: CLI only, lock, start and result lines in `storage/logs/jobs.log`, a row in the new `job_runs` table, exit code 0 or 1.
- **`application_reminders.php`:** one daily digest to MSW staff for applications waiting over 3 days, and a reminder with a fresh edit link every 3 days for applications For Revision.
- **`backup_database.php`:** mysqldump, gzipped, to `storage/backups/agile_{date}.sql.gz`, then removes backups over 30 days old. The password goes through the environment, never the command line. A failed dump leaves the last good backup in place.
- **`jobs/run.php`:** `list`, `all`, or job names. `all` includes the urgent welfare check.
- **System health** (`/admin/system-health`, `backup.manage`): a plain table of each job's last run and result, queued and failed email counts with a Retry button, and the latest backup. It replaces the Backups placeholder in the menu.
- **Docs:** `docs/scheduling.md` (crontab and Task Scheduler for every job), new `docs/restore.md`, §9, §10, `docs/database.md`.

### Decisions made
- **Pruning touches only files named `agile_YYYY-MM-DD.sql.gz`,** and runs only after a good backup, so other files in that folder stay.
- **`BACKUP_DIR` setting:** test runs send backups elsewhere, so a test database dump can never replace a real backup.
- **Emails whose link or code was erased cannot be retried,** because the queue removes those secrets after the last attempt. The page says how many.

### Files
- **New:** `app/Services/BackupService.php`, `SystemHealthService.php`, `app/Models/JobRunRepository.php`, `app/Controllers/SystemHealthController.php`, `app/Views/admin/system_health.php`, `jobs/backup_database.php`, `docs/restore.md`, `tests/JobsAndBackupTest.php`.
- **Changed:** all jobs, `jobs/_bootstrap.php`, `jobs/run.php`, `config/app.php`, `config/routes.php`, `config/menu.php`, `.env.example`, `database/schema.sql`, `NotificationRepository`, `MembershipService`, `docs/scheduling.md`, `docs/database.md`, `docs/PROJECT_CONTEXT.md`.

### How to test
Run `php database/setup.php` to add `job_runs`, then `php jobs/run.php all` twice, then open System health.

### Known issues
- A backup holds the database only. `.env` (above all `ENCRYPTION_KEY`), `storage/uploads` and `storage/credentials` need their own copies. `docs/restore.md` says so.
- Backups stay on the same computer unless someone copies them elsewhere.
- Windows tasks only run while the computer is on.
- A member with two applications For Revision gets one reminder per 3 days, not one per application.


### Step 21 test results (Nov 1, 2026)
PHPUnit 234 tests, HTTP 289 passed, browser 1192 passed, all on the scratch database. The backup job ran on the scratch database, with backups going to a test folder. It has not yet run against the real database.

## POLISH P2, area 7: Dashboards, reports, administration and system health (Nov 1, 2026)

Views, CSS, JS and visible text only. No routes, permissions or database changes. The one PHP text change outside a view is the guest welfare help text in `SettingsService.php`, which no longer shows "BR-17".

- **Dashboards:** stat cards have no decorative icons. The MSW Head's two card grids are now one, showing only counts above zero, because "Needs your action" already lists the rest. Charts with no data show a sentence instead of empty axes. Names are no longer in the mono font.
- **Reports:** the title names the report, dates use the PH format, codes are mono, number columns are right-aligned, and export buttons sit beside the row count and appear only when there are rows.
- **Administration:** Committees and positions has each table beside its add form. A user's page has one primary action, extra permissions are collapsed and grouped with their descriptions showing. A position in use says it cannot be deleted instead of offering the button. Settings has short number fields and a live rubric total (new `public/assets/js/rubric-total.js`). The audit log shows readable entity names.
- **System health:** one line says whether everything ran on time, each job says what it does in plain words, and all jobs show without inner scrolling. Email queue and latest backup are side by side.
- **Email screens:** the preview frame stays hidden until asked for, and a template's page title is "Edit this email" with its code above.

### Notes for later
- A refused settings save keeps typed values for the other fields, but rubric rows go back to the saved values, because `AdminSystemController::saveSettings` keeps only string input. Fixing it is a one-line logic change.
- The first breadcrumb says "Home" on dashboards and "Dashboard" on other pages. It is in the shared layout and controllers.
- Sidebar labels still use capitals ("All Modules") while page titles use sentence case.
- Delete committee always shows, because the page has no usage count. The service refuses when the committee is in use, and the page now says so.

## Step 22 of 24: Security and privacy review (Nov 1, 2026)

### What was built
- **`docs/security-review.md`:** every finding with severity, location, fix and status, what was checked and found clean, a production checklist, and the privacy section (data per module, who can see it, retention, RA 10173 consent points).
- **8 High and Medium findings fixed:**
  - **H-01:** new root `.htaccess` keeps `.env`, `storage/` and the source off the web if the project sits under a server's document root.
  - **M-01:** `SessionGuard` checks idle time and account status on every request, public routes included.
  - **M-02:** a changed, reset or force-reset password ends other logins.
  - **M-03:** welfare file names and `referrals.referred_to` are encrypted, with a migration for existing rows.
  - **M-04:** seeded login works only from this computer, and the debug page never shows in production.
  - **M-05:** at most 50 failed logins per IP in 15 minutes, across all emails.
  - **M-06:** an https `APP_URL` also sets the Secure cookie flag, and HSTS is sent over HTTPS.
  - **M-07:** the Privacy Notice and the eligibility page disclose the outside AI provider.
- **`tests/SecurityReviewTest.php`:** fails if a POST route lacks CSRF, or a route has no login check and is not on the public list.

### Decisions made
- **The rating of L-05 (no automatic deletion of old records) is Low,** because it is not a way in, but it is a compliance gap. How long to keep records is AGILE's decision, so no period was guessed. `docs/security-review.md` proposes a schedule.
- **Existing logins end once** after this update, because they have no password fingerprint yet. The message says the password was changed, even though it was not.

### Files
- **New:** `.htaccess`, `app/Services/SessionGuard.php`, `database/migrations/2026_11_01_encrypt_welfare_names.php`, `tests/SecurityReviewTest.php`, `docs/security-review.md`.
- **Changed:** `public/index.php`, `public/.htaccess`, `app/Core/Session.php`, `app/Core/ErrorHandler.php`, `app/Middleware/AuthMiddleware.php`, `CsrfMiddleware.php`, `app/helpers.php`, `AuthController`, `AuthService`, `ProfileService`, `WelfareService`, `app/Views/welfare/cases/_referrals.php`, `portal/privacy.php`, `eligibility/mine.php`, `database/schema.sql`, `.env.example`, `docs/database.md`, `docs/PROJECT_CONTEXT.md` §11.

### How to test
Run `php database/setup.php` first, because the two encrypted columns need widening. Then follow the checklist in the step's reply, and `docs/security-review.md` for what each fix does.

### Known issues
- **The root `.htaccess` was not tested on Apache,** because this computer's Laragon has no Apache program. Check it on XAMPP: `http://localhost/agile/.env` must not open.
- 8 Low findings stay open and accepted, listed with reasons in `docs/security-review.md`. L-05 needs AGILE's decision.

### Step 22 test results (Nov 1, 2026)
PHPUnit 241 tests, 2498 assertions; HTTP 303 passed; browser 1516 passed; all on the scratch database. With the database down, `APP_ENV=production` showed no details with `APP_DEBUG` on or off. `composer audit` found no advisories. The migration was tested from the old `welfare_attachments` column type with a plain name, and a second run changed nothing. It has not yet run on the real database.

## Step 23 of 24: Automated tests and the test-case checklist (Nov 1, 2026)

### What was built
- **Separate test setup:** `phpunit.xml` sets `DB_NAME_TEST=agile_ams_test`. `tests/Support/TestEnvironment.php` drops and rebuilds that database before every run, and refuses any name that does not end in `_test` or that matches `DB_NAME`. Files go to a temporary folder through a new `STORAGE_DIR` setting, used only by tests, so `storage/` is not touched. Tests send no email and use their own encryption key, whatever `.env` says.
- **`composer test`:** runs everything, with no time limit.
- **`tests/TransitionMapsTest.php`:** every pair of statuses for all ten §8 maps, allowed and forbidden.
- **`tests/BusinessRulesTest.php`:** the rules no test covered directly, plus TC-18 and TC-24.
- **`tests/HttpAccessTest.php`:** every protected route for every seeded role over real HTTP, compared with the §6 table in `docs/PROJECT_CONTEXT.md`. Also: not logged in goes to login, and every POST route answers 419 without a valid token.
- **`tests/HttpFlowsTest.php`, `HttpWholeFlowsTest.php`:** test cases that need real requests, including applying without an account and the guest welfare flow, start to finish. They use a second database, `agile_ams_test_http_test`.
- **`docs/test-cases.md`:** TC-01 to TC-35 with steps, expected and actual results, and a table of which tests cover each business rule. `tests/TestCasesDocumentTest.php` fails if a case is missing or a named test does not exist.

### Bugs the new tests found, now fixed
- **Five admin pages crashed with a 500 for a record that does not exist:** `/admin/users/{id}`, `/admin/committees/{id}`, `/admin/positions/{id}`, `/email-templates/{id}` and `/emails/{id}`. They now give 404.
- **`database/seeds/seed_applicants.php` wrote to a hardcoded path in the real `storage/uploads`,** which would have touched real files on every test rebuild. It now goes through `Config::basePath`.

### Files
- **New:** `tests/Support/TestEnvironment.php`, `RouteList.php`, `TestServer.php`, `tests/TransitionMapsTest.php`, `BusinessRulesTest.php`, `HttpAccessTest.php`, `HttpFlowsTest.php`, `HttpWholeFlowsTest.php`, `TestCasesDocumentTest.php`, `docs/test-cases.md`.
- **Changed:** `phpunit.xml`, `composer.json`, `README.md`, `tests/bootstrap.php`, `tests/AccessMatrixTest.php`, `tests/SecurityReviewTest.php`, `app/Core/Config.php`, `app/Services/CredentialService.php` (a public `canTransition`), `app/Controllers/AdminUsersController.php`, `AdminCommitteesController.php`, `EmailAdminController.php`, `database/seeds/seed_applicants.php`.

### How to test
Run `composer test`, then follow `docs/test-cases.md` for the parts marked Manual.

### Known issues
- **Not sent to allowed roles:** `POST /admin/roles` and `POST /logout`, because an empty form would clear every role's permissions, and the other logs the test out. Roles not allowed on `/admin/roles` still get a real 403. `AdministrationTest` covers saving roles.
- **Routes that only need a login** are checked for sending people to log in and for no server errors. Who may open a particular record there is checked by the service tests.
- **Most allowed requests use record ids that do not exist,** so they prove the permission check is passed, not that the action works. The flow and service tests cover the actions.
- **`composer test` has no browser tests.** Layout, dark mode, keyboard focus and real email are manual, listed in `docs/test-cases.md`.
- **Not re-run for this step:** the throwaway browser script from earlier steps, because its cleanup moves new files out of `storage/` and the real site was in use. It last passed in Step 22, before this step's small changes to 404 pages.
- **The database user needs permission to create and drop databases,** for the two test databases, which stay in MySQL between runs.

### Change after Step 23: docs layout for long public pages (Nov 1, 2026)
- **`layouts/docs.php`:** a left "On this page" column and the content in all the remaining width, used on the Privacy Notice, About, news, publication, announcement and event pages, and open position pages. The header and footer moved to `partials/site_header.php` and `site_footer.php`, unchanged.
- **Contents list:** built on the server from each page's h2 and h3, so it works without JavaScript and nothing moves on load. `public/assets/js/docs.js` highlights the current section.
- **Context cards:** At a glance, post details with related posts, event details with Add to calendar, Apply, and Quick facts. The calendar file travels inside the link, so it needs no route.
- **Outside views:** `PortalController::page()` takes a layout, and a new helper `org_contact_email()` reads the contact email from Settings. `cms/_article.php` has a `bodyOnly` option, and the CMS review preview keeps its old look.
- **Known issues:** posts now show their author's name publicly, as asked. The footer still uses `ORG_CONTACT_EMAIL` from `.env` while the new column uses Settings, so keep the two the same. At the top of a page on a short screen, the column's last link can sit just below the fold until you scroll.

### Change after Step 23: cards for browsing, tables for managing, plain text for reading (Nov 1, 2026)
- **Card grids:** News, Events, Publications, Announcements, Open Positions and About's new officers section. `.card--link` makes the whole card one link. Grids follow §12.1.
- **Plain sections:** detail pages and forms keep at most one summary card, and their other sections became `.plain-section`. Only the outer class changed, so their forms are untouched.
- **Reading pages:** the mission and vision on About and Home are plain text.
- **Credentials:** My Account shows the digital ID and certificate as cards with Download buttons.
- **Known issues:**
  - About now lists officers' names, positions and committees publicly, as asked.
  - The public sees initials, because `FileAccessPolicy::avatar` serves photos only to staff and the owner.
  - Credential cards show real details, not a picture of the PDF, because `FileAccessPolicy::credential` frames PDFs only for reviewers.
  - On Open Positions, Apply is part of the card's one link and opens the position page, where the application starts.

### Change after Step 23: glass on chosen surfaces (Nov 1, 2026)
- **Rule:** §12.1 now allows glass only on the public header when scrolled, the home hero panel, the login, registration and Track Status cards, modal and popover backdrops, and the dashboard topbar when content scrolls under it.
- **Code:** `--glass-*` tokens in `tokens.css`, `.glass` and `.glass--dark` in `components.css`, and a passive scroll listener in `app.js`. The public header is now sticky, and the docs column sits below it.
- **Opacity:** raised from the suggested 70% and 60% so text passes 4.5:1 with white or black behind it: `.glass` 86% light and 90% dark, `.glass--dark` 78%.
- **No glass at all:** welfare and credential screens, including the bars and dialog backdrops. A tracked result, which can be a welfare case, also has none. Forgot, reset and set password pages keep their solid card.
- **Fallbacks:** browsers without blur get 95% solid. Reduced transparency and forced colors get fully solid surfaces and no glow.
- **Known issues:** the fallback for browsers without blur was checked by confirming the rule loads, because Chrome cannot switch blur off. Scrolling was tested at phone width in headless Chrome, not on a real phone. There is no real AGILE event photo yet, so the hero uses the glow shapes, marked TODO.

### Change after Step 23: fluid, full-width pages (Nov 1, 2026)
- **Rule:** §12.1 now says pages have no centered container. `--gutter` (`clamp(16px, 4vw, 72px)`) sets the side space for the header, hero, sections, footer and dashboard content.
- **Patterns:** forms use a 45rem column on the left with a side panel (`partials/page_content.php`, `partials/side_panel.php`, the `form_side` section). Detail pages use `.detail-layout` with a 340px sticky column. My Account uses `.profile-layout`. Lists keep filters in one row with the table at 100%. Reading text stops at 80ch.
- **Grids:** cards go 1, 2, 3, 4, 5 columns at 640, 1024, 1440 and 1920. Officers and dashboard tiles use auto-fill.
- **Staff pages moved to the detail pattern:** `cms/show.php` keeps remarks and review actions in the side column, and `.review-grid` uses the 340px column.
- **Side fix:** `.table-wrap` is now `position: relative`, because hidden header text could escape it and make the audit log scroll sideways at 768px.
- **Test results:** `composer test` OK, 306 tests, 3072 assertions. Browser checks at 2560, 1920, 1366, 768 and 360: 2027 passed, 3 failed. Docs 377, glass 375 and cards 292 all passed.
- **Known issues:**
  - `cms-editor.js` does not label Quill's hidden link input, which `email-editor.js` does. This is older than this change and needs JavaScript, so it was left alone. It accounts for 2 of the 3 failures.
  - Committee page at 2560: the content in the last column ends 40px short of the gutter, though the grid itself fills the width. This is the third failure.
  - The auth side panels sit outside `<main>`, as complementary content.
  - Not opened in the browser at these widths: apply steps 2 to 6, guest welfare verify, form and submitted, set and reset password, 403, 419 and 500, the HR request detail page, and the eligibility review page, because the test database has no review rows. They use the same layouts.

### FEATURE P7, then made calm: the landing page (Nov 1, 2026)
- **Order now:** hero, numbers, What AGILE does, How to join, Positions and events, Latest announcements, then the closing band running into the footer. Gallery is off by default and shows up to 6 photos when turned on.
- **Removed after P7:** ticker, events carousel and countdown, canvas network, rotating tagline, orbit ring and float, button shine, the closing band's glow, and mission and vision on Home (About already shows them, and Home links to them).
- **Motion:** the hero's grid drift is the only loop, with one still glow and a Pause animation button. Entrances take turns, so two never run at once. With reduced motion the page is static, and with JavaScript off all of it is visible.
- **Settings > Landing page:** headline, sub-headline, taglines, hero image, join steps and sections to show. Values saved during P7 still work: a saved list containing `intro` loses Gallery, and the old default step wording counts as the new default. No database change.
- **Files:** `LandingService.php`, `portal/home.php`, `portal/landing/*`, `css/landing.css`, `js/landing.js`, `img/logo-hero.webp`, `img/logo-small.png`, `tests/LandingPageTest.php`. `theme-init.js` adds a `js` class before paint. The header and post cards use the smaller logo.
- **Results:** `composer test` OK, 314 tests, 3118 assertions. Landing browser checks 106 of 106. Regressions: glass 368 and cards 292 all pass, fluid 2025 passed and 4 failed, docs 376 passed and 1 failed.
- **Those 5 failures are not page faults:** one fluid check measured the first "View all" link rather than the last, and the last one is on the gutter. The docs check wants the heading near the top, but with the thinner footer the page ends first, and the heading is in view at full scroll. The other three are in Known issues.
- **Lighthouse mobile, home page:** on `php -S` without compression, 87 to 88, LCP 3.5 to 3.6s, CLS 0, TBT 0. Through a local gzip proxy, standing in for a compressing web server, 96, LCP 2.7s, CLS 0.
- **Known issues:**
  - The database has 5 committees, not 8, and the page shows what is there.
  - "Upcoming events" counts every published future event, because `academic_terms` has no semester dates.
  - `cms-editor.js` still does not label Quill's hidden inputs (2 fluid failures, older than this work). The committee page at 2560 ends 40px short inside its last column (1 fluid failure).
  - Without JavaScript the public header wraps to two rows at 1366 on every public page. Older than this work.
- **Notes for later:** turn on compression in Apache, which is what raises the mobile score above 90.
- **P10 (poster style) is not applied.** It was prepared in the session's scratch folder only, and `docs/design/landing-reference.png` was not found.

### Step 23 test results (Nov 1, 2026)
`composer test`: OK, 306 tests, 3072 assertions, about 2 minutes. Real `storage/` was compared before and after: the only change was two credential PDFs from approving on the live site during the run, matched to real database records and left alone.

### D1 — Database audit fixes (Nov 18, 2026)
Re-audit 10/10 (was 7/10). Details and evidence in [database-audit.md](database-audit.md).
- **New migration** `2026_11_18_dba_hardening.php`: real committees (R6 data), guest identity encrypted with `guest_email_hash`, 6 new CHECKs and 4 new UNIQUE rules, composite indexes, unused indexes and `eligibility_screenings.document_path` dropped. It reports bad rows instead of deleting them.
- **`setup.php`** records migrations in `schema_migrations` and runs only new ones. `member_self_registration.sql` no longer uses `AFTER registered_at`, so old databases upgrade.
- **Seeds:** 7 committees with Committee Head / Deputy Committee Head / Committee Member; The Source Code with its 10 positions and no head.
- **Code:** `EncryptionService::blindIndex()`; `WelfareService` and `WelfareCaseRepository` encrypt and decrypt guest details; `ApplicantRepository` refuses a student number another applicant has, with a message on the apply page; `committeeDecider()` sends Committee Head and Source Code applications to the MSW Committee Head.
- **New job** `cleanup_expired.php` (daily 02:30), on System health and in scheduling.md.
- **Tests:** `tests/DatabaseIntegrityTest.php` added; tests use the real committee names. `composer test`: OK, 330 tests, 3144 assertions.
- **To apply on Laragon:** `php jobs/backup_database.php`, then `php database/setup.php` (not `--fresh`), then `php database/report.php` ends with three 0s.
- **Still for R6:** the President deciding Executive Committee applications (permission matrix change). The landing page now has 8 committees to show.

### Design: Iskobot on the registration steps (Nov 19, 2026)
- The three `/register` steps (student number, email code, password) use Gravz's Iskobot design: animated maroon gradient, moving motto watermark, Iskobot holding the step banner, a step tracker, and a bold white card. The flow and rules are unchanged (R3).
- **Own stack:** Fredoka is self-hosted (`public/assets/fonts/fredoka-latin-*.woff2`, OFL), icons come from `icons.svg`, and there is no CDN or inline style (the CSP blocks inline styles).
- **Accessibility:** real `<button>` show/hide password with `aria-pressed`; the step tracker uses `aria-current="step"`; Iskobot is `aria-hidden`; all motion stops under `prefers-reduced-motion`.
- **Files:** `layouts/auth.php` (an `auth_style` section set to `iskobot`), `partials/iskobot_page.php`, `partials/iskobot.php`, `auth/register*.php`, `css/register-robot.css`, `js/register-robot.js`.
- **Results:** `composer test` OK, 330 tests. Checked in a browser at 1366 and 390 wide: no console errors and no sideways scrolling.

### Design: PUP campus photos behind the landing hero (Nov 19, 2026)
- Four PUP campus photos from Gravz crossfade behind the hero with a slow zoom, 7 seconds each, in pure CSS (`landing.css`, "Campus photos behind the hero"). A dark-to-maroon overlay keeps the text readable; the hero text stays on its glass panel.
- Photos are WebP in `public/assets/img/campus/` (about 30–240 KB each; phones get smaller copies of the two large ones). The tall creek photo was cropped to a wide strip.
- The hero's "Pause animation" button stops the slideshow; with reduced motion the first photo stays still. The photos are `aria-hidden` (decorative). No inline styles (CSP).
- Photos 1 and 3 are only about 680px wide, so they look soft on large screens. Larger originals can replace them with the same file names.
- **Results:** `composer test` OK, 330 tests. Checked at 1366 and 390 wide: no console errors and no sideways scrolling.

### Change: dark mode removed (Nov 19, 2026)
- Gravz asked to remove the dark theme. The site is light only.
- Removed: the moon/sun button (`partials/theme_toggle.php`) from the public header and the dashboard top bar, the dark color block in `tokens.css`, the toggle code in `app.js`, the chart recolor in `dashboards.js`, the dark progress-bar and icon rules in `components.css`, and the dark seeded in the style guide.
- `theme-init.js` now only restores the sidebar state, and deletes a dark choice saved earlier in `localStorage`.
- Docs updated: `PROJECT_CONTEXT.md` §12, `sad-updates.md`, `test-cases.md`.

### Change: committees in the nav bar (Nov 19, 2026)
- "Committees" is in the public nav bar after About, linking to About, "Our committees" (`config/menu.php`).
- The home page section "What AGILE does" is off by default (`LandingService::OFF_BY_DEFAULT`); Settings > Landing page can still show it. Migration `2026_11_19_committees_in_nav.php` unticks it once in a saved list.
- `composer test`: OK, 330 tests.

### Design: full-screen hero like the BHMS landing page (Nov 19, 2026)
- The home hero fills the first screen (`100svh`); the campus photos cover it, and the logo, a pill badge, the title, taglines, lead and buttons sit centered on the photo with no box. A round arrow at the bottom scrolls to the rest of the page (`#explore`).
- Files: `portal/landing/hero.php`, `portal/home.php`, `css/landing.css` ("Full-screen hero"). Reduced motion stops the arrow and smooth scrolling.
- `composer test`: OK, 330 tests. Checked at 1366 and 390 wide with no console errors.

### Change: AGILE's real positions (Nov 20, 2026)
- From the AGILE-OUS Applicant Assessment Report (Positions Dashboard): the Executive Committee is President, Vice President for Internal Affairs, Vice President for External Affairs, Secretary, Assistant Secretary, Treasurer and Auditor; The Source Code adds Editor-in-Chief (11 positions).
- Migration `2026_11_20_real_positions.php` renames the old "Vice President" to "Vice President for Internal Affairs", keeping its holder; the seeder adds the rest.
- The Source Code still has no committee head in the system: the MSW Committee Head decides its applications (BR-20). The sheet has the Editor-in-Chief reviewing them; that rule waits for AGILE's decision.
- Target headcounts per position (the sheet's TARGET column) come with the positions dashboard.
- `composer test`: OK, 330 tests.

### Data: existing members imported (Nov 20, 2026)
- `jobs/import_members.php` reads `storage/imports/members.csv` (local only, not committed), taken from the AGILE-OUS DIRECTORY sheet: 16 officers and members besides the 7 committee heads, each with their AGILE record ID as member number and their current position. With the heads, 23 active members.
- They register at /register with their PUP student number. `AuthService::registerMember()` now gives a member who already holds a position that position's role (President, MSW member, and so on) instead of General Member, and sets a committee's head if it has none. The MSW Committee Head still approves each account (BR-21).
- Not imported: Assistant Secretary (Gumapac), Treasurer (Cabugnason) and Auditor (Alvarez) have no PUP webmail or record ID in the sheet; the Finance Deputy Head is still "WHENG" (pending); the 6 applicants still being evaluated wait for Waitlisted (#3). Roseanne Fernandez is also Editor-in-Chief and Katherine Gozum also interim Managing Editor; the system holds one position per member, so they are imported with their Executive Committee position.
- `composer test`: OK, 330 tests.

### Feature: Waitlisted decision (Nov 20, 2026)
- From the Applicant Evaluation sheet's final decisions (Accepted, Waitlisted, Not Accepted). The decider under BR-20 can Waitlist an application that is Under Review, with remarks; the applicant gets the APP_WAITLISTED email and Track Status explains it. A waitlisted application can later be approved or rejected by the same decider.
- Waitlisted counts as an active application (one per person, BR-02), shows on vacancy pages and as a card on the MSW dashboard.
- Files: `ApplicationStatus.php`, `MembershipService::waitlist()`, `ApplicationsController::waitlist()`, route `applications.waitlist`, `applications/show.php`, `portal/track.php`, `config/statuses.php`, `DashboardService.php`, `MembershipApplicationRepository.php`, `recruitment/vacancies/show.php`, seed `email_template_text.php`, migration `2026_11_20_waitlisted_status.php`, `schema.sql`.
- Tests: 2 new (waitlist then approve; transitions). `composer test`: OK, 332 tests.

### Feature: interview checklist, BR-25 (Nov 20, 2026)
- From the INTERVIEW_CONTROL sheet. On a pending interview, MSW staff confirm: identity and AGILE record verified, role confirmed, privacy reminder given, and whether the interviewer has a conflict of interest. Who confirmed it and when is saved and shown in the interview list.
- Passed or Failed can only be recorded after the checklist; No show needs none. A declared conflict blocks the result and stops that interviewer from scoring the applicant; rescheduling (with another interviewer) starts a new checklist.
- Database: 6 columns on `interviews` and `chk_interviews_checklist` (migration `2026_11_20_interview_checklist.php`, `schema.sql`). Upgraded and fresh databases have the same structure.
- Files: `InterviewService::saveChecklist()`, `recordResult()`, `reschedule()`; `EvaluationService::submit()`; `InterviewRepository::hasConflict()`; `InterviewsController::checklist()`; route `applications.interviews.checklist`; `applications/_interviews.php`.
- Tests: 4 new in `AppointmentFlowTest`. `composer test`: OK, 336 tests.

### Change: seeded people instead of real data (Nov 20, 2026)
- No real person's details are kept in the project. `jobs/import_committee_heads.php` and `jobs/import_members.php` (which read a local CSV copied from AGILE's sheets) were removed, and the local CSVs were deleted.
- New seeder `database/seeds/seed_organization.php` (not in production): made-up people for every real position, with accounts for the committee heads so BR-20 decisions can be tried. `seed_applicants.php` adds a waitlisted applicant.
- Kept: a member who registers gets their position's role (`AuthService::registerMember()`).
- Real members and officers are added through the app: officers by the MSW Committee Head (Administration › Users) and members through approved applications and /register.
- `composer test`: OK, 336 tests.

### Data: 100 more seeded people (Nov 20–21, 2026)
- `database/seeds/seed_bulk.php`, on with `SEED_BULK=true` in `.env` (off by default, never in production, forced off in tests): 7 former Executive Committee officers, 3 deputies, 7 Source Code staff, 21 committee members, 22 approved general members (6 registered) and 40 applicants at every stage. Details in `docs/database.md`.
- Checked on a throwaway database, run twice: the stage counts match and nothing is added twice. No single-holder position gets two people.

## Step 24 of 24: Going live (Nov 21, 2026)

### What was built
- **`database/remove_seed_data.php`** with `App\Services\SeedDataRemover`: removes every `@agile.test` account and applicant and what belongs to them, for a database that was used for testing. Without `--yes` it only lists what it would do. With `--yes` it needs `APP_ENV=production` (or `--any-env`), makes a backup first (unless `--no-backup`), and works in one transaction that is rolled back if any link would break.
  - The foreign keys are read from `information_schema`, so tables from later migrations are covered.
  - Real records are never deleted. A seeded account's name in a who-did-it column that may be empty (approved by, decided by, the audit log's user) is cleared. If it may not be empty and the row belongs to a real record, such as a seeded evaluation of a real applicant, nothing is removed and the row is listed.
  - It refuses if no real MSW Committee Head account would be left (`--allow-no-admin` overrides).
  - Files only those rows used are moved to `storage/removed-seed-data/<time>/`, never deleted.
- **`docs/deployment.md`:** the go-live steps in order: server, files, production `.env`, database, the first MSW Committee Head (`jobs/create_user.php ... msw_head`), scheduled jobs, backups and last checks. Linked from the README, whose second, outdated Tests section was replaced.
- **Compression and caching** in `public/.htaccess` (`mod_deflate`, a week's `Cache-Control` on assets). `asset()` adds `?v=<file time>`, so a changed CSS or JS file is fetched at once.

### Fixes
- **Public header without JavaScript (1200px and up):** the `@media (scripting: none)` rule set `.site-nav` to `display: block`, which beat the desktop row and put Member log in and Apply on a second line (98px tall instead of 73px). It now applies only below 1200px, where the menu sits behind the toggle. Measured at 1366: one row, 73px, the same as with JavaScript.
- **Committee page (`/admin/committees/{id}`):** the Delete form was a bare third column with no heading. It is now a section like the other two. The 40px measured at 2560 was the 80ch reading limit on its one-line help text, which is intended, not a layout fault.
- **`cms-editor.js`:** Quill's link box and the heading select it hides now have labels, as in `email-editor.js`.
- **`phpunit.xml`:** `APP_DEBUG` and `SEED_BULK` are forced with `"0"`, not `"false"`. PHPUnit turns `"false"` into an empty value, Windows removes an empty variable, and the database rebuild and test server then read `.env` instead. With `SEED_BULK=true` in `.env`, the 100 seeded people reached the test database and two tests failed.
- **4 PHP warnings in the test run:** `use DomainException;` in `tests/AccountsAndDecisionsTest.php` does nothing in the global namespace and printed a warning first, so the session tests found headers already sent. Removed.

### Test results
- `composer test`: OK, 341 tests, 3216 assertions, no warnings. Includes `tests/SeedDataRemoverTest.php` (5 tests: the plan covers every seeded person and no reference data, it refuses with no real MSW Head, removal leaves no broken links, a real application decided by a seeded account is kept with the name cleared, a seeded evaluation of a real application stops everything).
- The real `storage/` was listed before and after the run: the same 73 files.

### Checked on the real database (read-only)
`php database/remove_seed_data.php` on `agile_ams` lists only seeded data and stops because no real MSW Committee Head exists yet, as intended. Nothing was changed.

### Known issues
- `public/.htaccess` compression and caching only work under Apache with `mod_deflate` and `mod_headers`; `php -S` ignores them. Not tested under Apache.
- `AI_API_KEY` is forced empty in `phpunit.xml`, which Windows also removes, so child processes see the key from `.env`. Harmless while `AI_PROVIDER=none` is forced, which it is.
- The Step 3 to Step 23 browser suites were not rerun; only the header and committee pages were measured.

### After Step 24 (Nov 22, 2026)
- **Footer:** one thin line from 1200px, with AGILE's real contacts from `.env` (`ORG_CONTACT_EMAIL`, `ORG_MSW_EMAIL`, `ORG_FACEBOOK_URL`).
- **Login:** in the site's own layout like Track Status, with Iskobot standing on the card and holding its corners; the seeded switcher folds away.

### Feature: membership requirements and choosing a committee (Nov 22, 2026)
- **Requirements (from AGILE):** General Membership needs the current COR only. Joining a committee, or any position, needs the current COR and the previous semester copy of grades. 1st years in a 1st semester give the COR only; 1st years joining in the 2nd semester give their 1st semester grades. School ID is no longer asked for (old uploads still show).
- **Where:** `MembershipService::requiredDocuments()`, `documentsToAsk()`, `needsGrades()`, `isFirstYearFirstSemester()` (year level from step 2, semester from the current academic term; with no current term, grades are asked for). The documents step, the approval check and uploads all follow it. `membership/_requirements.php` shows AGILE's table on the apply page and in step 3.
- **Choosing a committee (step 3):** General Membership, or Committee Member with a committee to choose, for people without an account and for members who hold no position. Open positions stay for logged-in members. The Source Code has no Committee Member position, so it is not listed; its positions open as vacancies.
- **Who decides:** that committee's head (BR-20), or the MSW Committee Head for the MSW committee. The application shows as "Committee Member, <committee>" in every list, and in the committee head's list. Staff can filter by Committee Member.
- **On approval:** the member becomes that committee's Committee Member (`membership_type` officer). With an account, they get the position's role at once; without one, `/register` gives it later. Someone who meanwhile got a position keeps it.
- **Database:** `membership_applications.committee_id` with a CHECK that a vacancy and a committee are never both set (migration `2026_11_22_committee_applications.php`, `schema.sql`). Applied to `agile_ams` after a backup.
- **Seeded data:** seeded applicants give a COR only; with `SEED_BULK=true`, every third applicant not yet approved applies to a committee (on fresh seeds).
- **Tests:** `tests/MembershipRequirementsTest.php` (8). Test data for position applications now has COR and grades. `composer test`: OK, 349 tests, 3236 assertions.
- **Checked in the browser** as `nomember@agile.test`: the apply page's table, step 3 (no committee chosen gives "Please choose the committee you want to join."), and step 5 asking for COR and grades for Logistics. This left one draft application for that seeded account.
- **Not built:** Year-Level Representative is in AGILE's table but is not a position in the system yet.

### Feature: Open for application, and the apply page asks everything at once (Nov 22, 2026)
- **Open for application** (Recruitment, MSW Committee Head, `/admin/open-positions`): every position from Committee Head to The Source Code, by committee, with AGILE's dashboard numbers: Target (editable, empty for no limit), Filled (active holders), Remaining and Status (Open, Closed, Filled), and an Open tick. Saving is audited (`positions.open_updated`). `OpenPositionsService`, `OpenPositionsController`, `admin/open_positions.php`.
- **Starting values:** targets from the dashboard (Committee Head 1, Deputy 1, Committee Member 4; The Source Code: Editor-in-Chief 1, Managing Editor 2, News Editor 2, Writer 3, Head Cartoonist 1, Cartoonist 3, Layout Editor 3, Head Photojournalist 1, Photojournalist 3, Videographer/Editor 3; Feature Editor none). Committee Members start open; the rest start closed.
- **Apply page card:** Applying for (General Membership, or an open position that still has slots, by committee, with slots left), Requirements (AGILE's table as a dropdown, `MembershipService::REQUIREMENT_GROUPS`), the required documents for that row, and the upload fields it needs: the COR always, and the previous semester (or 1st semester) grades when the row asks for them. General Membership takes General members or a 1st year row; a position takes any row but General members.
- **Files from the apply page** are checked and saved in `storage/uploads/applications` once the code is sent, kept in the session, and attached to the draft after the email is confirmed (`MembershipService::attachFirstPageDocuments`). If something on the page needs fixing, files are not kept and the page says to choose them again. Files of someone who never confirms their email stay in that folder; nothing deletes them.
- **Replaces the committee choice:** `membership_applications.position_id` instead of `committee_id` (the earlier migration was removed before being pushed; `2026_11_22_open_positions.php` converts `agile_ams`). Approval gives the position, makes a Committee Head the committee's head when they have an account, and is refused when the position is full. A Committee Head position is decided by the MSW Committee Head (R6); The Source Code by the MSW Committee Head (BR-20); the rest by the committee's head.
- **Tests:** `MembershipRequirementsTest` (14), `HttpWholeFlowsTest` (the apply page needs the row and the COR, and the COR joins the application). `composer test`: OK, 355 tests, 3258 assertions.
- **Checked in the browser:** each Requirements row shows its documents and shows or hides the grades field (COR only for General members and 1st year 1st semester; "1st semester copy of grades" for 1st years in the 2nd semester); the admin page at 1366. With the seeded data most positions are full, so only one shows on the apply page.
- **Applied to `agile_ams`** after a backup (the morning's backup was copied to `%TEMP%\agile_2026-11-22_before-open-positions.sql.gz` first).