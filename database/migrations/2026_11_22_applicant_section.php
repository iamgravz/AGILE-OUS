<?php

declare(strict_types=1);

// The first apply page asks for year level, section and contact number (Nov 22, 2026). Year level and contact number
// already have columns; this adds the section. Safe to run again.
return static function (PDO $db): void {
    $has = $db->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'section'"
    )->fetchColumn();

    if ((int) $has > 0) {
        return;
    }

    $db->exec('ALTER TABLE applicants ADD COLUMN section VARCHAR(30) NULL AFTER year_level');
    echo "Applicants can now give their section.\n";
};
