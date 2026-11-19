<?php

declare(strict_types=1);

// AGILE's Waitlisted decision for membership applications (from the Applicant Evaluation sheet's FINAL DECISION list).
// Safe to run again.
return static function (PDO $db): void {
    $type = $db->query(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'membership_applications' AND COLUMN_NAME = 'status'"
    )->fetchColumn();

    if (is_string($type) && !str_contains($type, "'waitlisted'")) {
        $db->exec("ALTER TABLE membership_applications
            MODIFY status ENUM('draft','submitted','under_review','for_revision','waitlisted','approved','rejected','withdrawn') NOT NULL DEFAULT 'draft'");
        echo "Applications can now be Waitlisted.\n";
    }
};
