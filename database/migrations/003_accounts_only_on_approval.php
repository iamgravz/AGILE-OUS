<?php

declare(strict_types=1);

// BR-18: applicants need no account. Moves people with the old applicant role to the applicants table. Their user
// rows stay, disabled and under a placeholder email, because the audit trail points at them and the real email must
// be free for the account created on approval. Safe to run again.
return static function (PDO $db): void {
    $hasColumn = $db->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'membership_applications' AND COLUMN_NAME = 'applicant_id'"
    )->fetchColumn();

    if ((int) $hasColumn === 0) {
        $db->exec('ALTER TABLE membership_applications
            ADD COLUMN applicant_id INT UNSIGNED NULL AFTER application_id,
            MODIFY user_id INT UNSIGNED NULL,
            ADD KEY idx_membership_applications_applicant_id (applicant_id),
            ADD CONSTRAINT fk_membership_applications_applicant FOREIGN KEY (applicant_id) REFERENCES applicants (applicant_id) ON DELETE RESTRICT ON UPDATE RESTRICT');
        echo "Added membership_applications.applicant_id.\n";
    }

    $db->exec("ALTER TABLE guest_verifications MODIFY purpose ENUM('welfare_submit','application_submit','account_register','member_registration') NOT NULL");

    $db->exec("DELETE FROM user_tokens WHERE type = 'verify_email'");
    $db->exec("ALTER TABLE user_tokens MODIFY type ENUM('account_setup','password_reset') NOT NULL");

    // Notifications already sent keep their title and body, so only the link to the old template goes.
    $db->exec("UPDATE notifications SET template_id = NULL WHERE template_id = (SELECT template_id FROM email_templates WHERE code = 'ACCOUNT_VERIFY')");
    $db->exec("DELETE FROM email_templates WHERE code = 'ACCOUNT_VERIFY'");
    $db->exec("UPDATE email_templates SET body_html = REPLACE(body_html, 'Enter it on the welfare form within 15 minutes.', 'Enter it on the AGILE form within 15 minutes.') WHERE code = 'GUEST_EMAIL_CODE'");

    $roleId = $db->query("SELECT role_id FROM roles WHERE slug = 'applicant'")->fetchColumn();

    if ($roleId === false) {
        return;
    }

    $guestRoleId = $db->query("SELECT role_id FROM roles WHERE slug = 'guest'")->fetchColumn();
    $people = $db->prepare('SELECT * FROM users WHERE role_id = ?');
    $people->execute([$roleId]);
    $moved = 0;

    foreach ($people->fetchAll(PDO::FETCH_ASSOC) as $user) {
        $db->beginTransaction();

        $db->prepare(
            'INSERT IGNORE INTO applicants (email, first_name, last_name, student_no, contact_no, email_verified_at) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$user['email'], $user['first_name'], $user['last_name'], $user['student_no'], $user['contact_no'], $user['email_verified_at']]);

        $find = $db->prepare('SELECT applicant_id FROM applicants WHERE email = ?');
        $find->execute([$user['email']]);
        $applicantId = $find->fetchColumn();

        $db->prepare('UPDATE membership_applications SET applicant_id = ?, user_id = NULL WHERE user_id = ?')->execute([$applicantId, $user['user_id']]);

        // Their concerns stay trackable with the case code and the same email.
        $db->prepare('UPDATE welfare_cases SET user_id = NULL, guest_name = ?, guest_email = ?, guest_student_no = ? WHERE user_id = ?')
            ->execute([trim($user['first_name'] . ' ' . $user['last_name']), $user['email'], $user['student_no'], $user['user_id']]);

        $db->prepare('UPDATE user_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([$user['user_id']]);
        $db->prepare("DELETE FROM notifications WHERE user_id = ? AND channel = 'email' AND status = 'queued'")->execute([$user['user_id']]);
        $db->prepare("UPDATE users SET role_id = ?, status = 'disabled', email = ?, student_no = NULL WHERE user_id = ?")
            ->execute([$guestRoleId, "retired-applicant-{$user['user_id']}@agile.invalid", $user['user_id']]);

        $db->commit();
        $moved++;
    }

    $db->prepare('DELETE FROM role_permissions WHERE role_id = ?')->execute([$roleId]);
    $db->prepare('DELETE FROM roles WHERE role_id = ?')->execute([$roleId]);
    echo "Removed the applicant role and moved {$moved} people to applicants.\n";
};
