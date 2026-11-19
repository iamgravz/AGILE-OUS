# Restoring a backup

`jobs/backup_database.php` writes `storage/backups/agile_YYYY-MM-DD.sql.gz` each day and keeps 30 days.

**Restoring replaces everything in the database you restore into.** Try it on a separate database first.

## What a backup does not contain

A backup holds the database only. Keep copies of these separately, or a restored system will be missing them:

- **`.env`, above all `ENCRYPTION_KEY`.** Welfare text and attachments cannot be read without the same key.
- **`storage/uploads`:** application documents, welfare attachments, eligibility files and photos.
- **`storage/credentials`:** digital ID and certificate PDFs.

## Check a backup into a test database first

Windows (XAMPP or MySQL, in PowerShell). Change the `mysql.exe` path to match your computer:

```
cd C:\laragon\www\agile
$mysql = 'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe'
& $mysql -u root -p -e "CREATE DATABASE agile_restore_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php -r "readgzfile('storage/backups/agile_2026-11-01.sql.gz');" | & $mysql -u root -p --default-character-set=utf8mb4 agile_restore_test
& $mysql -u root -p -e "SELECT COUNT(*) FROM agile_restore_test.users"
```

Linux:

```
mysql -u root -p -e "CREATE DATABASE agile_restore_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
gunzip -c storage/backups/agile_2026-11-01.sql.gz | mysql -u root -p --default-character-set=utf8mb4 agile_restore_test
mysql -u root -p -e "SELECT COUNT(*) FROM agile_restore_test.users"
```

If that looks right, remove the test database with `DROP DATABASE agile_restore_test`.

## Restore the real database

1. Stop the website and the scheduled jobs, so nothing writes while you restore.
2. Make one more backup first: `php jobs/backup_database.php`. Copy that file somewhere safe, because it is what you go back to if the restore is wrong.
3. Run the commands above with your real database name in place of `agile_restore_test`. Skip the `CREATE DATABASE` line.
4. Put back `storage/uploads` and `storage/credentials` from their own copies if they were lost.
5. Run `php database/setup.php` if the backup is older than the current code. It brings the structure up to date and keeps your data.
6. Start the website and the jobs again, then check **Administration, System health**.
