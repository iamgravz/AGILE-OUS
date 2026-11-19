-- UPDATE R1: applicants have no account, and approval creates a member record with no account.
-- Migrations 003 and 004 already created applicants and application_access_links, moved applicant users and removed
-- the applicant role. This file does the rest. setup.php runs it after them. Safe to run again.

SET @add_columns = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'members' AND COLUMN_NAME = 'applicant_id') = 0,
    'ALTER TABLE members
        MODIFY user_id INT UNSIGNED NULL,
        ADD COLUMN applicant_id INT UNSIGNED NULL AFTER user_id,
        ADD COLUMN registered_at DATETIME NULL AFTER joined_at,
        ADD UNIQUE KEY uq_members_applicant_id (applicant_id),
        ADD CONSTRAINT fk_members_applicant FOREIGN KEY (applicant_id) REFERENCES applicants (applicant_id) ON DELETE RESTRICT ON UPDATE RESTRICT',
    'DO 0'
);
PREPARE add_columns FROM @add_columns;
EXECUTE add_columns;
DEALLOCATE PREPARE add_columns;

-- Codes from the open registration form that is gone.
DELETE FROM guest_verifications WHERE purpose = 'account_register';
ALTER TABLE guest_verifications MODIFY purpose ENUM('welfare_submit','application_submit','member_registration') NOT NULL;

-- Members who came through an application without an account.
UPDATE members m JOIN applicants ap ON ap.user_id = m.user_id SET m.applicant_id = ap.applicant_id WHERE m.applicant_id IS NULL;
UPDATE members SET registered_at = created_at WHERE user_id IS NOT NULL AND registered_at IS NULL;

-- Accounts the earlier approval flow made with no password. Nobody could use them, so those people register instead.
-- The rows stay, disabled, because the audit trail may point at them.
UPDATE users u
JOIN members m ON m.user_id = u.user_id
LEFT JOIN applicants ap ON ap.user_id = u.user_id
SET m.user_id = NULL,
    m.registered_at = NULL,
    ap.user_id = NULL,
    u.status = 'disabled',
    u.student_no = NULL,
    u.email = CONCAT('retired-applicant-', u.user_id, '@agile.invalid')
WHERE u.status = 'pending' AND u.password_hash = '';

-- APP_APPROVED now says what to do next, so emails still waiting get the new values.
UPDATE email_templates
SET body_html = '<p>Hello {name},</p><p>Congratulations! Your application <strong>{tracking_code}</strong> was approved. Your member number is <strong>{member_no}</strong>.</p><p>{next_step} <a href="{link}">{link_text}</a></p>'
WHERE code = 'APP_APPROVED' AND updated_by IS NULL AND body_html NOT LIKE '%Greetings from the AGILE OUS%';

UPDATE notifications n
JOIN email_templates t ON t.template_id = n.template_id
SET n.payload_json = JSON_SET(n.payload_json, '$.next_step', 'Your digital ID and certificate of membership are ready.', '$.link_text', 'Open my member profile')
WHERE t.code = 'APP_APPROVED' AND n.status = 'queued' AND JSON_EXTRACT(n.payload_json, '$.next_step') IS NULL;

UPDATE notifications n
JOIN email_templates t ON t.template_id = n.template_id
SET n.payload_json = JSON_SET(n.payload_json, '$.next_step', 'To use your member account, register with your PUP student number.', '$.link_text', 'Register my account', '$.link', '/register'),
    n.template_id = (SELECT template_id FROM (SELECT template_id FROM email_templates WHERE code = 'APP_APPROVED') AS approved)
WHERE t.code = 'APP_APPROVED_ACCOUNT_PENDING' AND n.status = 'queued';

UPDATE notifications SET template_id = NULL WHERE template_id = (SELECT template_id FROM email_templates WHERE code = 'APP_APPROVED_ACCOUNT_PENDING');
DELETE FROM email_templates WHERE code = 'APP_APPROVED_ACCOUNT_PENDING';
