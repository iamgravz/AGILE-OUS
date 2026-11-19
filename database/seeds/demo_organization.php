<?php

declare(strict_types=1);

// Sample people for every real AGILE position, so the whole structure can be shown and tested without anyone's real
// details. Every name, email and student number here is made up; real members register or are added in the app.
// Runs only outside production, after demo_users (which already has the President, a VP, the Academics and Training
// head and the MSW Committee Head, Deputy and Member).
//
// Committee heads get an account (password Password123!) so they can decide their committee's applications (BR-20).
// Everyone else is a member with no account yet, as an approved applicant would be; they can try /register.
return static function (PDO $db): void {
    $now = date('Y-m-d H:i:s');
    $role = static function (string $slug) use ($db): int {
        $statement = $db->prepare('SELECT role_id FROM roles WHERE slug = ?');
        $statement->execute([$slug]);

        return (int) $statement->fetchColumn();
    };
    $position = static function (string $committee, string $title) use ($db): int {
        $statement = $db->prepare(
            'SELECT p.position_id FROM positions p LEFT JOIN committees c ON c.committee_id = p.committee_id
             WHERE p.title = ? AND ((? = \'\' AND p.committee_id IS NULL) OR c.name = ?) LIMIT 1'
        );
        $statement->execute([$title, $committee, $committee]);

        return (int) $statement->fetchColumn();
    };
    $nextMemberNo = static function () use ($db): string {
        $prefix = 'AGILE-' . date('Y') . '-';
        $statement = $db->prepare('SELECT COALESCE(MAX(CAST(SUBSTRING(member_no, ?) AS UNSIGNED)), 0) FROM members WHERE member_no LIKE ?');
        $statement->execute([strlen($prefix) + 1, $prefix . '%']);

        return sprintf('%s%04d', $prefix, (int) $statement->fetchColumn() + 1);
    };

    // [committee ('' = Executive Committee), position, first name, last name]. Student numbers and emails are generated.
    $people = [
        ['', 'Vice President for External Affairs', 'Marco', 'Villareal'],
        ['', 'Secretary', 'Joanna', 'Bautista'],
        ['', 'Assistant Secretary', 'Kim', 'Salazar'],
        ['', 'Treasurer', 'Patrick', 'Domingo'],
        ['', 'Auditor', 'Grace', 'Manalo'],
        ['Academics and Training', 'Deputy Committee Head', 'Liza', 'Tolentino'],
        ['Academics and Training', 'Committee Member', 'Carlo', 'Aquino'],
        ['Research and Extensions', 'Committee Head', 'Irene', 'Castillo'],
        ['Research and Extensions', 'Committee Member', 'Noel', 'Pascual'],
        ['Creatives, Social Media and Communication', 'Committee Head', 'Bea', 'Navarro'],
        ['Creatives, Social Media and Communication', 'Committee Member', 'Rico', 'Valdez'],
        ['Finance and Fund Raising', 'Committee Head', 'Ella', 'Soriano'],
        ['Finance and Fund Raising', 'Committee Member', 'Jun', 'Ocampo'],
        ['Logistics', 'Committee Head', 'Ramon', 'Agustin'],
        ['Logistics', 'Deputy Committee Head', 'Tess', 'Medina'],
        ['Logistics', 'Committee Member', 'Arvin', 'Robles'],
        ['Events and External Relations', 'Committee Head', 'Gino', 'Mercado'],
        ['Events and External Relations', 'Deputy Committee Head', 'Nina', 'Ramirez'],
        ['The Source Code', 'Editor-in-Chief', 'Clara', 'Fajardo'],
        ['The Source Code', 'Managing Editor', 'Dino', 'Lazaro'],
        ['The Source Code', 'Writer', 'Mia', 'Santiago'],
        ['The Source Code', 'Cartoonist', 'Paolo', 'Reyes'],
    ];

    foreach ($people as $index => [$committee, $title, $firstName, $lastName]) {
        $positionId = $position($committee, $title);

        if ($positionId === 0) {
            continue;
        }

        $email = strtolower(str_replace(' ', '', "{$firstName}.{$lastName}")) . '@agile.test';
        $studentNo = sprintf('2024-%05d-OU-0', 300 + $index);
        $isHead = $title === 'Committee Head';

        $applicantId = seed_row($db, 'applicants', 'applicant_id', ['email' => $email], [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'student_no' => $studentNo,
            'contact_no' => sprintf('0917200%04d', $index + 1),
            'year_level' => '3rd Year',
            'email_verified_at' => $now,
        ]);

        $member = $db->prepare('SELECT member_id FROM members WHERE applicant_id = ?');
        $member->execute([$applicantId]);

        if ($member->fetchColumn() !== false) {
            continue;
        }

        $userId = null;

        if ($isHead) {
            $userId = seed_row($db, 'users', 'user_id', ['email' => $email], [
                'role_id' => $role('committee_head'),
                'password_hash' => password_hash('Password123!', PASSWORD_DEFAULT),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'student_no' => $studentNo,
                'contact_no' => sprintf('0917200%04d', $index + 1),
                'status' => 'active',
                'email_verified_at' => $now,
            ]);
            $db->prepare('UPDATE applicants SET user_id = ? WHERE applicant_id = ?')->execute([$userId, $applicantId]);
        }

        seed_row($db, 'members', 'member_id', ['applicant_id' => $applicantId], [
            'user_id' => $userId,
            'position_id' => $positionId,
            'member_no' => $nextMemberNo(),
            'membership_type' => 'officer',
            'role_status' => 'active',
            'joined_at' => date('Y-m-d'),
            'registered_at' => $userId === null ? null : $now,
        ]);

        // A committee with no head yet gets this one, so its applications have a decider (BR-20).
        if ($isHead) {
            $db->prepare(
                'UPDATE committees c JOIN positions p ON p.committee_id = c.committee_id
                 SET c.head_user_id = ? WHERE p.position_id = ? AND c.head_user_id IS NULL'
            )->execute([$userId, $positionId]);
        }
    }
};
