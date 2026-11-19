<?php

declare(strict_types=1);

// BR-25: AGILE's interview checklist (from the INTERVIEW_CONTROL sheet): identity and record verified, role confirmed,
// privacy reminder given, conflict of interest declared, and who confirmed it when. Safe to run again.
return static function (PDO $db): void {
    $has = $db->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'interviews' AND COLUMN_NAME = 'checklist_at'"
    )->fetchColumn();

    if ((int) $has > 0) {
        return;
    }

    $db->exec('ALTER TABLE interviews
        ADD COLUMN identity_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER reminder_sent_at,
        ADD COLUMN role_confirmed TINYINT(1) NOT NULL DEFAULT 0 AFTER identity_verified,
        ADD COLUMN privacy_reminder_given TINYINT(1) NOT NULL DEFAULT 0 AFTER role_confirmed,
        ADD COLUMN conflict_of_interest TINYINT(1) NULL AFTER privacy_reminder_given,
        ADD COLUMN checklist_by INT UNSIGNED NULL AFTER conflict_of_interest,
        ADD COLUMN checklist_at DATETIME NULL AFTER checklist_by,
        ADD KEY idx_interviews_checklist_by (checklist_by),
        ADD CONSTRAINT chk_interviews_checklist CHECK (checklist_at IS NULL OR (identity_verified = 1 AND role_confirmed = 1 AND privacy_reminder_given = 1 AND conflict_of_interest IS NOT NULL AND checklist_by IS NOT NULL)),
        ADD CONSTRAINT fk_interviews_checklist_by FOREIGN KEY (checklist_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT');

    // Interviews that already have a result happened before the checklist existed; they stay as they were.
    echo "Interviews now have the BR-25 checklist.\n";
};
