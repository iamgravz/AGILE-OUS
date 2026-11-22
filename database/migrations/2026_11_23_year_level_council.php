<?php

declare(strict_types=1);

// Year-Level Council (Constitution and By-Laws, Article V Section 3), Nov 23, 2026: one representative per year level,
// elected (Article VI), never applied for. Positions use role_type 'general', which Open for application, HR requests
// and the officer role mapping all leave alone; the holder keeps the General Member role. Safe to run again.
return static function (PDO $db): void {
    $find = $db->prepare('SELECT committee_id FROM committees WHERE name = ?');
    $find->execute(['Year-Level Council']);
    $committeeId = $find->fetchColumn();

    if ($committeeId === false) {
        $db->prepare('INSERT INTO committees (name, description) VALUES (?, ?)')->execute([
            'Year-Level Council',
            'Elected representatives from each year level: the communication bridge between the Executive Board and their batch.',
        ]);
        $committeeId = $db->lastInsertId();
        echo "Added the Year-Level Council.\n";
    }

    $exists = $db->prepare('SELECT COUNT(*) FROM positions WHERE committee_id = ? AND title = ?');
    $insert = $db->prepare("INSERT INTO positions (committee_id, title, role_type, is_open, target_slots) VALUES (?, ?, 'general', 0, 1)");

    foreach (['1st Year Representative', '2nd Year Representative', '3rd Year Representative', '4th Year Representative'] as $title) {
        $exists->execute([$committeeId, $title]);

        if ((int) $exists->fetchColumn() === 0) {
            $insert->execute([$committeeId, $title]);
        }
    }
};
