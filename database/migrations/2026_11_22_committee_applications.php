<?php

declare(strict_types=1);

// Applying to join a committee as a Committee Member, without a vacancy (AGILE's membership requirements, Nov 22, 2026).
// committee_id is the committee chosen; an application has a vacancy or a committee or neither (general membership),
// never both. Safe to run again.
return static function (PDO $db): void {
    $has = $db->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'membership_applications' AND COLUMN_NAME = 'committee_id'"
    )->fetchColumn();

    if ((int) $has > 0) {
        return;
    }

    $db->exec('ALTER TABLE membership_applications
        ADD COLUMN committee_id INT UNSIGNED NULL AFTER vacancy_id,
        ADD KEY idx_membership_applications_committee_id (committee_id),
        ADD CONSTRAINT chk_membership_applications_one_choice CHECK (vacancy_id IS NULL OR committee_id IS NULL),
        ADD CONSTRAINT fk_membership_applications_committee FOREIGN KEY (committee_id) REFERENCES committees (committee_id) ON DELETE RESTRICT ON UPDATE RESTRICT');

    echo "Applications can now name the committee they want to join.\n";
};
