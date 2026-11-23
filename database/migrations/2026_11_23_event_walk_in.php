<?php

declare(strict_types=1);

// Event attendance (Nov 23, 2026): marks members checked in without registering first. Safe to run again.
return static function (PDO $db): void {
    $has = (int) $db->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_registrations' AND COLUMN_NAME = 'walk_in'"
    )->fetchColumn();

    if ($has === 0) {
        $db->exec('ALTER TABLE event_registrations ADD COLUMN walk_in TINYINT(1) NOT NULL DEFAULT 0 AFTER status');
    }
};
