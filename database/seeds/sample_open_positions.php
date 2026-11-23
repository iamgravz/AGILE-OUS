<?php

declare(strict_types=1);

// Outside production only: five positions open for application, so Open Positions and Committees show
// real-looking cards. It only opens positions that still have slots; the MSW Committee Head can close them in
// Administration, Open for application. Safe to run again.
return static function (PDO $db): void {
    $open = [
        // The sample organization fills most Committee Member slots, so the rest come from The Source Code's staff.
        ['Events and External Relations', 'Committee Member'],
        ['The Source Code', 'Writer'],
        ['The Source Code', 'Cartoonist'],
        ['The Source Code', 'Photojournalist'],
        ['The Source Code', 'Videographer/Editor'],
    ];
    $update = $db->prepare(
        'UPDATE positions p JOIN committees c ON c.committee_id = p.committee_id
         SET p.is_open = 1
         WHERE c.name = ? AND p.title = ? AND (p.target_slots IS NULL OR p.target_slots >
             (SELECT COUNT(*) FROM members m WHERE m.position_id = p.position_id AND m.role_status = \'active\'))'
    );

    foreach ($open as [$committee, $title]) {
        $update->execute([$committee, $title]);
    }
};
