<?php

declare(strict_types=1);

// One QR code per member (Nov 23, 2026). The MSW Committee Head puts it on the ID made from AGILE's template; scanning it
// shows the member's current ID and whether they are an active member, so a new ID never needs a new QR. Safe to run again.
return static function (PDO $db): void {
    $has = $db->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'members' AND COLUMN_NAME = 'verify_token'"
    )->fetchColumn();

    if ((int) $has === 0) {
        $db->exec('ALTER TABLE members ADD COLUMN verify_token CHAR(64) NULL AFTER member_no, ADD UNIQUE KEY uq_members_verify_token (verify_token)');
        echo "Members now have their own QR code.\n";
    }
};
