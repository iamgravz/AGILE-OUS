-- UPDATE R2 (BR-19): a new digital ID or certificate waits for the MSW Committee Head. Credentials already issued become
-- approved, so current members are not affected. Safe to run again.

SET @add_columns = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'digital_credentials' AND COLUMN_NAME = 'status') = 0,
    'ALTER TABLE digital_credentials
        ADD COLUMN status ENUM(''pending_approval'',''approved'',''returned'') NOT NULL DEFAULT ''approved'' AFTER file_path,
        ADD COLUMN reason ENUM(''new_member'',''role_change'',''reissue'') NOT NULL DEFAULT ''new_member'' AFTER status,
        ADD COLUMN approved_by INT UNSIGNED NULL AFTER revoked_at,
        ADD COLUMN approved_at DATETIME NULL AFTER approved_by,
        ADD COLUMN review_remarks TEXT NULL AFTER approved_at,
        ADD COLUMN replaces_credential_id INT UNSIGNED NULL AFTER review_remarks,
        ADD KEY idx_digital_credentials_status (status),
        ADD CONSTRAINT fk_digital_credentials_approved_by FOREIGN KEY (approved_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
        ADD CONSTRAINT fk_digital_credentials_replaces FOREIGN KEY (replaces_credential_id) REFERENCES digital_credentials (credential_id) ON DELETE RESTRICT ON UPDATE RESTRICT',
    'DO 0'
);
PREPARE add_columns FROM @add_columns;
EXECUTE add_columns;
DEALLOCATE PREPARE add_columns;

ALTER TABLE digital_credentials MODIFY status ENUM('pending_approval','approved','returned') NOT NULL DEFAULT 'pending_approval';
UPDATE digital_credentials SET approved_at = issued_at WHERE status = 'approved' AND approved_at IS NULL;

-- These said the credentials were ready at once.
UPDATE email_templates
SET body_html = REPLACE(body_html, 'and your digital ID has been updated.', 'and a new digital ID will be ready after the MSW Committee Head reviews it.')
WHERE code = 'ROLE_EXPIRED';
