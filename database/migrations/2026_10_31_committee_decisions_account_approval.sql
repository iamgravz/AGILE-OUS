-- UPDATE R4 (BR-20, BR-21). Accounts already in use count as approved. Safe to run again.

SET @add_columns = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'approved_at') = 0,
    'ALTER TABLE users
        ADD COLUMN approved_by INT UNSIGNED NULL AFTER email_verified_at,
        ADD COLUMN approved_at DATETIME NULL AFTER approved_by,
        ADD COLUMN rejection_remarks TEXT NULL AFTER approved_at,
        ADD CONSTRAINT fk_users_approved_by FOREIGN KEY (approved_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT',
    'DO 0'
);
PREPARE add_columns FROM @add_columns;
EXECUTE add_columns;
DEALLOCATE PREPARE add_columns;

UPDATE users SET approved_at = COALESCE(email_verified_at, created_at) WHERE status = 'active' AND approved_at IS NULL;

-- Committee heads now hold application.decide itself, limited to their own committee by MembershipService::decider().
DELETE rp FROM role_permissions rp JOIN permissions p ON p.permission_id = rp.permission_id WHERE p.code = 'application.decide_committee';
DELETE up FROM user_permissions up JOIN permissions p ON p.permission_id = up.permission_id WHERE p.code = 'application.decide_committee';
DELETE FROM permissions WHERE code = 'application.decide_committee';

UPDATE email_templates
SET body_html = REPLACE(body_html, '<p>If you have questions, please contact the MSW Committee.</p>', '<p>After the issue is fixed, you may register again with your PUP student number. If you have questions, please contact the MSW Committee.</p>')
WHERE code = 'ACCOUNT_REJECTED' AND body_html NOT LIKE '%may register again%';

-- Demo documents had names the file service refuses to open. Only those two exact paths change.
UPDATE application_documents
SET stored_path = CONCAT('applications/demo/', MD5(CONCAT('demo-', doc_type)), '.pdf')
WHERE stored_path IN ('applications/demo/school_id.pdf', 'applications/demo/cor.pdf');
