<?php

declare(strict_types=1);

// AGILE's real positions (from the AGILE-OUS Applicant Assessment Report, Positions Dashboard): the old single
// "Vice President" becomes "Vice President for Internal Affairs", keeping whoever holds it. The seeder then adds the
// Vice President for External Affairs, the Assistant Secretary and The Source Code's Editor-in-Chief. Safe to run again.
return static function (PDO $db): void {
    $exists = static function (string $title) use ($db): bool {
        $statement = $db->prepare('SELECT COUNT(*) FROM positions WHERE committee_id IS NULL AND title = ?');
        $statement->execute([$title]);

        return (int) $statement->fetchColumn() > 0;
    };

    if ($exists('Vice President') && !$exists('Vice President for Internal Affairs')) {
        $db->exec("UPDATE positions SET title = 'Vice President for Internal Affairs' WHERE committee_id IS NULL AND title = 'Vice President'");
        echo "Renamed Vice President to Vice President for Internal Affairs.\n";
    }
};
