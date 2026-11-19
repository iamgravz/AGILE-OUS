-- UPDATE R3. The no_applicant_accounts migration already made members.user_id nullable and added applicant_id,
-- registered_at and the member_registration purpose. This adds what the reminder job needs. Safe to run again.
-- No AFTER clause: setup.php runs files in name order, so this runs before no_applicant_accounts on an old database.

SET @add_column = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'members' AND COLUMN_NAME = 'registration_reminders') = 0,
    'ALTER TABLE members ADD COLUMN registration_reminders TINYINT UNSIGNED NOT NULL DEFAULT 0',
    'DO 0'
);
PREPARE add_column FROM @add_column;
EXECUTE add_column;
DEALLOCATE PREPARE add_column;
