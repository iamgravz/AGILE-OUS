<?php

declare(strict_types=1);

// A half-body professional picture with an ID request (Nov 23, 2026), for the MSW Committee Head to put on AGILE's ID
// template. Safe to run again.
return static function (PDO $db): void {
    $has = $db->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'credential_requests' AND COLUMN_NAME = 'photo_path'"
    )->fetchColumn();

    if ((int) $has === 0) {
        $db->exec('ALTER TABLE credential_requests ADD COLUMN photo_path VARCHAR(255) NULL AFTER note');
        echo "ID requests now carry the member's picture.\n";
    }
};
