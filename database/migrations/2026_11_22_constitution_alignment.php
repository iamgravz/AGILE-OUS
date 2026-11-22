<?php

declare(strict_types=1);

// Alignment with the AGILE-OUS Constitution and By-Laws (docs/constitution-alignment.md, C1 to C4 and P1), Nov 22, 2026:
// Article II's vision, mission and objectives replace the sample wording (only where it was never edited); the official
// email; a Facebook address that is a real web address; "General Secretary"; and the committee names as Article V
// writes them. Safe to run again.
return static function (PDO $db): void {
    $vision = 'The Organization envisions itself as the leading academic and support hub for BSIT - Open University students, fostering collaboration, technical excellence, and an empowering virtual learning ecosystem where distance learners thrive.';
    $mission = implode("\n", [
        'Providing accessible academic support, technical tutorials, and peer mentorship tailored to the dynamics of independent and distance learning.',
        'Cultivating a robust professional network connecting students with alumni, IT industry leaders, and tech communities.',
        'Delivering hands-on workshops, technical skill-building bootcamps, and real-world project collaborations.',
        'Ensuring equitable access to digital resources and peer assistance to bridge distance learning challenges.',
    ]);
    $objectives = implode("\n", [
        'To build and sustain a dependable online academic support framework for BSIT-OUS students.',
        'To conduct IT-focused workshops, study sessions, and industry networking events.',
        'To promote ethical practices, open-source collaboration, and digital innovation.',
        'To represent and advocate for the general welfare and academic interests of the BSIT distance-learning community.',
    ]);
    $samples = [
        'org.mission' => 'To build a supportive community of BSIT-OU learners through academic support, skills development and student welfare services.',
        'org.vision' => 'A connected guild of IT learners and experts who grow together and serve others through technology.',
    ];

    $get = static function (string $key) use ($db): ?string {
        $statement = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $statement->execute([$key]);
        $value = $statement->fetchColumn();

        return $value === false ? null : (string) $value;
    };
    $set = static function (string $key, string $value) use ($db): void {
        $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')->execute([$key, $value]);
    };

    // Article II, only over the sample wording: a mission or vision someone already edited stays.
    foreach (['org.vision' => $vision, 'org.mission' => $mission] as $key => $text) {
        $current = $get($key);

        if ($current === null || $current === $samples[$key]) {
            $set($key, $text);
        }
    }

    if ($get('org.objectives') === null) {
        $set('org.objectives', $objectives);
    }

    // Article I and the letterhead: the official email, in every email's footer.
    if (in_array($get('org.contact_email'), [null, 'contact@agile.test', 'agile.ous@gmail.com', 'msw.agileous@gmail.com'], true)) {
        $set('org.contact_email', 'agile.pupous@gmail.com');
    }

    // "https://AGILE-OUS@facebook.com" is not a web address; the page "AGILE OUS" is.
    $facebook = (string) $get('org.facebook_url');

    if ($facebook === '' || str_contains($facebook, '@') || !str_starts_with($facebook, 'https://')) {
        $set('org.facebook_url', 'https://www.facebook.com/agile.ous');
    }

    // Article IV and V names. A name is only changed when the new one is not taken.
    $rename = static function (string $table, string $column, string $old, string $new, string $where = '1 = 1') use ($db): void {
        $taken = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = ? AND {$where}");
        $taken->execute([$new]);

        if ((int) $taken->fetchColumn() === 0) {
            $db->prepare("UPDATE {$table} SET {$column} = ? WHERE {$column} = ? AND {$where}")->execute([$new, $old]);
        }
    };
    $rename('positions', 'title', 'Secretary', 'General Secretary', 'committee_id IS NULL');
    $rename('committees', 'name', 'Research and Extensions', 'Research and Extension');
    $rename('committees', 'name', 'Finance and Fund Raising', 'Finance and Fundraising');

    echo "Vision, mission, objectives, official email and names now follow the Constitution and By-Laws.\n";
};
