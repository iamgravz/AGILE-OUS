<?php

declare(strict_types=1);

// Alignment report rows 1, 8 and 9. Safe to run again.
return static function (PDO $db): void {
    $db->exec("ALTER TABLE application_access_links MODIFY purpose ENUM('resume_draft','revise','withdraw') NOT NULL");

    $hasCheck = $db->query(
        "SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'chk_membership_applications_owner'"
    )->fetchColumn();

    if ((int) $hasCheck === 0) {
        $db->exec('ALTER TABLE membership_applications ADD CONSTRAINT chk_membership_applications_owner CHECK ((applicant_id IS NULL) <> (user_id IS NULL))');
        echo "Added the one-owner check on membership_applications.\n";
    }

    // Tokens the queue kept by mistake. Emails still waiting to be sent keep theirs.
    $cleared = $db->exec(
        "UPDATE notifications n
         LEFT JOIN email_templates t ON t.template_id = n.template_id
         SET n.payload_json = '{}', n.link = NULL
         WHERE n.channel = 'email' AND (n.status = 'sent' OR (n.status = 'failed' AND n.attempts >= 3))
           AND (t.code IN ('ACCOUNT_SETUP', 'APPLICATION_LINK') OR n.payload_json LIKE '%token=%')"
    );

    if ($cleared > 0) {
        echo "Cleared {$cleared} stored email tokens.\n";
    }
};
