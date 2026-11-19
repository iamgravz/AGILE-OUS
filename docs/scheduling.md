# Scheduled jobs

Every job refuses the web, holds a lock so runs cannot overlap, and is safe to run twice. Each one logs its start, counts and errors to `storage/logs/jobs.log`, records its run for **Administration, System health**, and exits with 0 on success or 1 on failure.

| Job | Schedule | Purpose |
|---|---|---|
| `send_email_queue.php` | Every 5 minutes | Sends queued emails and retries failures up to 3 times |
| `close_vacancies.php` | Daily 00:05 | Closes vacancies past their date or filled, and tells the committee head |
| `backup_database.php` | Daily 02:00 | Dumps the database to `storage/backups/agile_{date}.sql.gz` and removes backups older than 30 days. See [restore.md](restore.md). |
| `cleanup_expired.php` | Daily 02:30 | Deletes one-time codes, edit links and account tokens 7 days after they expire or are used, and rate-limit counters after a day (data minimization, RA 10173) |
| `account_digest.php` | Daily 08:00 | If any account is waiting, tells the MSW Committee Head the count (once a day) |
| `credential_digest.php` | Daily 08:00 | If any ID or certificate is waiting, tells the MSW Committee Head the count (once a day) |
| `role_expiration_reminders.php` | Daily 08:00 during screening | Officers without documents, 7 days and 1 day before the deadline |
| `welfare_followups.php` | Daily 08:00 | Due follow-ups |
| `welfare_followups.php --urgent` | Hourly | Urgent or harassment cases not acknowledged after 24 hours |
| `interview_reminders.php` | Hourly | 24 hours before an interview |
| `eligibility_reminders.php` | Daily 09:00 during screening | Officers who have not submitted, and marks incomplete after the deadline and grace days |
| `application_reminders.php` | Daily 09:00 | One daily digest to MSW staff about applications waiting over 3 days; a reminder with a fresh edit link every 3 days for applications For Revision; a reminder to create an account 7 and 14 days after approval |

Not scheduled: `create_user.php`, `generate_key.php` and `test_notify.php` are run by hand.

## Running jobs by hand

```
php jobs/run.php list
php jobs/run.php all
php jobs/run.php send_email_queue close_vacancies
```

`all` runs every job once, in turn, including the urgent welfare check. It exits with 1 if any job failed.

## Backup settings

`backup_database.php` finds `mysqldump` on its own in the usual XAMPP, Laragon and MySQL folders, or on the PATH. If it cannot, set the full path in `.env`:

```
MYSQLDUMP_PATH="C:\xampp\mysql\bin\mysqldump.exe"
```

`BACKUP_DIR` changes where backups go, which is useful for testing. It defaults to `storage/backups`.

## Linux hosting: crontab

Run `crontab -e` and add these lines. Change `/var/www/agile` and the PHP path to match the server.

```
*/5 * * * * cd /var/www/agile && /usr/bin/php jobs/send_email_queue.php >/dev/null 2>&1
5 0 * * * cd /var/www/agile && /usr/bin/php jobs/close_vacancies.php >/dev/null 2>&1
0 2 * * * cd /var/www/agile && /usr/bin/php jobs/backup_database.php >/dev/null 2>&1
30 2 * * * cd /var/www/agile && /usr/bin/php jobs/cleanup_expired.php >/dev/null 2>&1
0 8 * * * cd /var/www/agile && /usr/bin/php jobs/account_digest.php >/dev/null 2>&1
0 8 * * * cd /var/www/agile && /usr/bin/php jobs/credential_digest.php >/dev/null 2>&1
0 8 * * * cd /var/www/agile && /usr/bin/php jobs/role_expiration_reminders.php >/dev/null 2>&1
0 8 * * * cd /var/www/agile && /usr/bin/php jobs/welfare_followups.php >/dev/null 2>&1
0 * * * * cd /var/www/agile && /usr/bin/php jobs/welfare_followups.php --urgent >/dev/null 2>&1
0 * * * * cd /var/www/agile && /usr/bin/php jobs/interview_reminders.php >/dev/null 2>&1
0 9 * * * cd /var/www/agile && /usr/bin/php jobs/eligibility_reminders.php >/dev/null 2>&1
0 9 * * * cd /var/www/agile && /usr/bin/php jobs/application_reminders.php >/dev/null 2>&1
```

The server's clock should be on Asia/Manila, or the times above adjusted to match. Output goes to `storage/logs/jobs.log`, so cron does not need to keep it.

## Windows: Task Scheduler (XAMPP or Laragon)

Open PowerShell **as Administrator**. Change the first two lines to match your computer, then paste the rest. Each line creates one task, and running it again replaces that task. `C:\xampp\php\php.exe` is the usual path for XAMPP.

```
$php = 'C:\xampp\php\php.exe'
$app = 'C:\xampp\htdocs\agile'
function Add-AgileTask($name, $file, $schedule) { schtasks /Create /F /TN "AGILE\$name" /TR "cmd /c cd /d `"$app`" && `"$php`" jobs\$file" @schedule }
Add-AgileTask 'send_email_queue' 'send_email_queue.php' @('/SC','MINUTE','/MO','5')
Add-AgileTask 'close_vacancies' 'close_vacancies.php' @('/SC','DAILY','/ST','00:05')
Add-AgileTask 'backup_database' 'backup_database.php' @('/SC','DAILY','/ST','02:00')
Add-AgileTask 'cleanup_expired' 'cleanup_expired.php' @('/SC','DAILY','/ST','02:30')
Add-AgileTask 'account_digest' 'account_digest.php' @('/SC','DAILY','/ST','08:00')
Add-AgileTask 'credential_digest' 'credential_digest.php' @('/SC','DAILY','/ST','08:00')
Add-AgileTask 'role_expiration_reminders' 'role_expiration_reminders.php' @('/SC','DAILY','/ST','08:00')
Add-AgileTask 'welfare_followups' 'welfare_followups.php' @('/SC','DAILY','/ST','08:00')
Add-AgileTask 'welfare_followups_urgent' 'welfare_followups.php --urgent' @('/SC','HOURLY')
Add-AgileTask 'interview_reminders' 'interview_reminders.php' @('/SC','HOURLY')
Add-AgileTask 'eligibility_reminders' 'eligibility_reminders.php' @('/SC','DAILY','/ST','09:00')
Add-AgileTask 'application_reminders' 'application_reminders.php' @('/SC','DAILY','/ST','09:00')
```

To do it by hand instead: open Task Scheduler, choose **Create Basic Task**, set the schedule from the table above, choose **Start a program**, put the path to `php.exe` in **Program/script**, `jobs\send_email_queue.php` in **Add arguments**, and the project folder in **Start in**. For every 5 minutes or hourly, open the task's **Properties**, then **Triggers**, then **Repeat task every**.

The tasks only run while the computer is on. To stop them, run `schtasks /Delete /TN "AGILE\send_email_queue" /F` for each task, or delete the AGILE folder in Task Scheduler.
