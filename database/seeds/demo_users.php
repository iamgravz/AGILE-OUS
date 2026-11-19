<?php

declare(strict_types=1);

return static function (PDO $db): void {
    $findPosition = $db->prepare(
        'SELECT p.position_id, p.committee_id
         FROM positions p
         LEFT JOIN committees c ON c.committee_id = p.committee_id
         WHERE p.title = ? AND (c.name = ? OR (? IS NULL AND p.committee_id IS NULL))
         LIMIT 1'
    );
    $findRole = $db->prepare('SELECT role_id FROM roles WHERE slug = ?');
    $setHead = $db->prepare('UPDATE committees SET head_user_id = ? WHERE committee_id = ?');

    $msw = 'Membership and Student Welfare';

    // [email, role, first name, last name, student no, committee, position title]
    $users = [
        ['guest@agile.test', 'guest', 'Demo', 'Guest', null, null, null],
        // Registered and approved, but not a member yet: what /register gives before an application is approved.
        ['nomember@agile.test', 'general_member', 'Andrea', 'Santos', '2024-00101-OU-0', null, null],
        ['member@agile.test', 'general_member', 'Miguel', 'Reyes', '2023-00102-OU-0', null, null],
        ['head@agile.test', 'committee_head', 'Bianca', 'Cruz', '2022-00103-OU-0', 'Academics and Training', 'Committee Head'],
        ['execomm@agile.test', 'execomm', 'Paolo', 'Garcia', '2022-00104-OU-0', null, 'Vice President for Internal Affairs'],
        ['mswmember@agile.test', 'msw_member', 'Carla', 'Mendoza', '2023-00105-OU-0', $msw, 'Committee Member'],
        ['mswdeputy@agile.test', 'msw_member', 'Dennis', 'Torres', '2022-00106-OU-0', $msw, 'Deputy Committee Head'],
        ['mswhead@agile.test', 'msw_head', 'Elena', 'Ramos', '2021-00107-OU-0', $msw, 'Committee Head'],
        ['president@agile.test', 'president', 'Rafael', 'Navarro', '2021-00108-OU-0', null, 'President'],
    ];

    $now = date('Y-m-d H:i:s');
    $memberCount = 0;

    foreach ($users as $index => [$email, $role, $firstName, $lastName, $studentNo, $committee, $title]) {
        $findRole->execute([$role]);

        $userId = seed_row($db, 'users', 'user_id', ['email' => $email], [
            'role_id' => $findRole->fetchColumn(),
            'password_hash' => password_hash('Password123!', PASSWORD_DEFAULT),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'student_no' => $studentNo,
            'contact_no' => sprintf('0917000%04d', $index + 1),
            // Guests have no login (section 5), so this account exists only to represent the role.
            'status' => $role === 'guest' ? 'disabled' : 'active',
            'email_verified_at' => $now,
        ]);

        if ($role === 'guest' || $email === 'nomember@agile.test') {
            continue;
        }

        $memberCount++;
        $positionId = null;

        if ($title !== null) {
            $findPosition->execute([$title, $committee, $committee]);
            $position = $findPosition->fetch();
            $positionId = $position['position_id'];

            if ($title === 'Committee Head') {
                $setHead->execute([$userId, $position['committee_id']]);
            }
        }

        seed_row($db, 'members', 'member_id', ['user_id' => $userId], [
            'position_id' => $positionId,
            'member_no' => sprintf('AGILE-%s-%04d', date('Y'), $memberCount),
            'membership_type' => $title === null ? 'general' : 'officer',
            'role_status' => $title === null ? null : 'active',
            'joined_at' => date('Y-m-d'),
        ]);
    }
};
