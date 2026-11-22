<?php

declare(strict_types=1);

// Year level, section and contact number on every submission (Nov 23, 2026). Accounts keep the student's year level
// and section next to the contact number; a welfare case keeps the submitter's, encrypted like the rest of a guest's
// identity (M3). Safe to run again.
return static function (PDO $db): void {
    $has = static function (string $table, string $column) use ($db): bool {
        $statement = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $statement->execute([$table, $column]);

        return (int) $statement->fetchColumn() > 0;
    };

    if (!$has('users', 'year_level')) {
        $db->exec('ALTER TABLE users ADD COLUMN year_level VARCHAR(20) NULL AFTER contact_no, ADD COLUMN section VARCHAR(30) NULL AFTER year_level');
        echo "Accounts now keep the year level and section.\n";
    }

    if (!$has('welfare_cases', 'submitter_contact_no')) {
        $db->exec('ALTER TABLE welfare_cases ADD COLUMN submitter_contact_no TEXT NULL AFTER guest_student_no, ADD COLUMN submitter_year_level TEXT NULL AFTER submitter_contact_no, ADD COLUMN submitter_section TEXT NULL AFTER submitter_year_level');
        echo "Welfare cases now keep the submitter's contact number, year level and section.\n";
    }
};
