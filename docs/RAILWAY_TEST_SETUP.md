# Railway test deployment — AGILE OUS

**Purpose:** Explore the PHP/MySQL application with fictional test records only. **Do not enable public application intake or upload real welfare details.**

## 1. Create Railway project
1. Sign in at https://railway.com and create a **New Project**.
2. Add a **MySQL** database service (use Railway's MySQL option), wait for Ready. Keep it private.
3. Add a service **Deploy from GitHub Repo**, choose `iamgravz/AGILE-OUS` and branch `develop/phase-1-secure-foundation`.
4. Set **Root Directory** to `/` (repository root), not `preview`. The service will use `Dockerfile` and `railway.json`.
5. The container listens on Railway's injected `PORT` and exposes `/healthz.php`. Configure public networking for the **web app only**, not the MySQL service.

## 2. Application variables (Web service > Variables)
Use Railway **reference variables** pointing to your MySQL service; the example below assumes that service is named `MySQL` (case-sensitive). Enter each value in Railway, not in GitHub or chat.

```text
APP_ENV=testing
APP_DEBUG=false
APP_TIMEZONE=Asia/Manila
APPLICATIONS_OPEN=false
SESSION_SECURE=true
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_NAME=${{MySQL.MYSQLDATABASE}}
DB_USER=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
```

Generate **two different** cryptographically random keys on your own computer (e.g. `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`) and add:
- `APP_KEY`: random hex string (at least 32 characters)
- `WELFARE_ENCRYPTION_KEY`: a different 64-character random hex string

Never paste private keys or database passwords into ChatGPT, email, or GitHub. The variables are private to the Railway app.

## 3. Initialize an empty MySQL database
The repo contains 001–008 migrations under `database/`. **Migration files are excluded from the runtime Docker image** and must be applied separately from a trusted database administration session before the app will work.

For a fresh, disposable Railway MySQL test database, use a local checkout or Railway's authenticated database shell/CLI to apply the SQL files in filename order. Confirm the list using `ls database/0*.sql`; both `005_recruitment_automation.sql` and `005_vacancies_notifications.sql` are required.

Never rerun old ALTER migrations against a populated database: first check `information_schema` and take a backup.

To create a fictional tester staff account, execute `php scripts/create_user.php` in a secure temporary environment with database connectivity, using only example.test credentials. **Never use a real student or faculty identity in testing.**

## 4. What to test
- Staff sign in/out with test credentials and a persistent session
- Roles: President gets counts only, Membership Head manages applications, Welfare staff see only permitted cases
- Fake applicant form only in an isolated environment with `APPLICATIONS_OPEN=true`, temporarily, then turn it back off
- Interviews, decision transitions, vacancy cap enforcement, notification drafts (not email delivery)
- Welfare encryption and case access with fictional descriptions
- Check logs, CI status, DB backup and test restore before inviting any other users

## Important limitations
- The Railway public URL itself is internet-accessible if public networking is enabled. Protect test accounts, disable public intake and consider Railway service networking access restrictions.
- Default PHP sessions are saved on the container filesystem and may be lost on redeployment; run **one web instance** only for testing. Shared session storage is required before scaling.
- MySQL schema migrations and tester setup are not automated by service start because automatic repeated ALTER statements would risk data loss.
- This is a development build. Production launch requires further security/privacy review, MFA, session durability, retention policies, and full workflow tests.
