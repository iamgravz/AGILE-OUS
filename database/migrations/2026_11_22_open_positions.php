<?php

declare(strict_types=1);

// Open for application (Nov 22, 2026). The MSW Committee Head opens and closes positions, from Committee Head to The
// Source Code, and sets each one's target headcount (AGILE's Positions & Recruitment Dashboard). Applicants choose an
// open position on the first apply page, stored in membership_applications.position_id. A database that already has
// committee_id (the earlier "join a committee" version) has it turned into the Committee Member position. Safe to run again.
return static function (PDO $db): void {
    $hasColumn = static function (string $table, string $column) use ($db): bool {
        $statement = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $statement->execute([$table, $column]);

        return (int) $statement->fetchColumn() > 0;
    };
    $hasConstraint = static function (string $name) use ($db): bool {
        $statement = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'membership_applications' AND CONSTRAINT_NAME = ?");
        $statement->execute([$name]);

        return (int) $statement->fetchColumn() > 0;
    };

    if (!$hasColumn('positions', 'is_open')) {
        $db->exec('ALTER TABLE positions
            ADD COLUMN is_open TINYINT(1) NOT NULL DEFAULT 0 AFTER role_type,
            ADD COLUMN target_slots SMALLINT UNSIGNED NULL AFTER is_open');
        echo "Positions can now be opened for application, with a target headcount.\n";
    }

    // Starting values, set once (setup.php runs each migration once per database). On a new database the positions may
    // not exist yet; the seeder then creates them with the same values. Applying as a Committee Member was already open.
    $db->exec("UPDATE positions SET is_open = 1 WHERE role_type = 'committee_member'");
    $db->exec("UPDATE positions SET target_slots = CASE role_type WHEN 'committee_head' THEN 1 WHEN 'deputy' THEN 1 WHEN 'committee_member' THEN 4 END
               WHERE target_slots IS NULL AND role_type IN ('committee_head', 'deputy', 'committee_member')");
    $target = $db->prepare("UPDATE positions SET target_slots = ? WHERE role_type = 'source_code' AND title = ? AND target_slots IS NULL");

    // From AGILE's Positions & Recruitment Dashboard (Managing Editor counts the interim one).
    foreach (['Editor-in-Chief' => 1, 'Managing Editor' => 2, 'News Editor' => 2, 'Writer' => 3, 'Head Cartoonist' => 1, 'Cartoonist' => 3,
        'Layout Editor' => 3, 'Head Photojournalist' => 1, 'Photojournalist' => 3, 'Videographer/Editor' => 3] as $title => $slots) {
        $target->execute([$slots, $title]);
    }

    if (!$hasColumn('membership_applications', 'position_id')) {
        $db->exec('ALTER TABLE membership_applications
            ADD COLUMN position_id INT UNSIGNED NULL AFTER vacancy_id,
            ADD KEY idx_membership_applications_position_id (position_id),
            ADD CONSTRAINT fk_membership_applications_position FOREIGN KEY (position_id) REFERENCES positions (position_id) ON DELETE RESTRICT ON UPDATE RESTRICT');
        echo "Applications can now name the position applied for.\n";
    }

    if ($hasColumn('membership_applications', 'committee_id')) {
        $db->exec("UPDATE membership_applications a
            JOIN positions p ON p.committee_id = a.committee_id AND p.role_type = 'committee_member'
            SET a.position_id = p.position_id
            WHERE a.committee_id IS NOT NULL AND a.position_id IS NULL");

        if ($hasConstraint('chk_membership_applications_one_choice')) {
            $db->exec('ALTER TABLE membership_applications DROP CHECK chk_membership_applications_one_choice');
        }

        if ($hasConstraint('fk_membership_applications_committee')) {
            $db->exec('ALTER TABLE membership_applications DROP FOREIGN KEY fk_membership_applications_committee');
        }

        $db->exec('ALTER TABLE membership_applications DROP COLUMN committee_id');
        echo "Committee choices were moved to their Committee Member position.\n";
    }

    if (!$hasConstraint('chk_membership_applications_one_choice')) {
        $db->exec('ALTER TABLE membership_applications ADD CONSTRAINT chk_membership_applications_one_choice CHECK (vacancy_id IS NULL OR position_id IS NULL)');
    }
};
