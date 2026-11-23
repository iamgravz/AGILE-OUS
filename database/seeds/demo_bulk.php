<?php

declare(strict_types=1);

// 100 more made-up people across every level, from former Executive Committee officers down to applicants, so lists,
// filters, reports and pagination can be tried with a realistic amount of data. Every name, email, student number and
// phone number is invented. Runs only outside production and only when DEMO_BULK=true (tests keep it off, because they
// count the smaller demo set). Runs after demo_users, demo_applicants and demo_organization, and reuses their demo PDFs.
//
//   7  former Executive Committee officers (role expired, now general members)
//   3  deputy committee heads (the committees with no deputy yet)
//   7  The Source Code staff (the positions demo_organization leaves open)
//  21  committee members (3 per committee)
//  22  general members with an approved application (6 have registered an account)
//  40  applicants: 10 submitted, 9 under review, 5 for revision, 6 waitlisted, 5 rejected, 3 withdrawn, 2 drafts
//
// None of the new officers gets an account, as in demo_organization; the registered general members use Password123!.
return static function (PDO $db): void {
    $now = date('Y-m-d H:i:s');
    $today = date('Y-m-d');

    $firstNames = ['Aaron', 'Bella', 'Cedric', 'Daniela', 'Emman', 'Faith', 'Gabriel', 'Hannah', 'Ivan', 'Jasmine',
        'Kevin', 'Louise', 'Mark', 'Nicole', 'Oscar', 'Pia', 'Renz', 'Sofia', 'Trisha', 'Vince'];
    // None of these is used by the other demo seeders, so no email collides with theirs.
    $lastNames = ['Abad', 'Bernardo', 'Cabrera', 'Dizon', 'Enriquez', 'Flores', 'Galang', 'Hernandez', 'Ignacio', 'Javier',
        'Lacson', 'Magbanua', 'Natividad', 'Olivares', 'Panganiban', 'Quiambao', 'Rosales', 'Samonte', 'Tan', 'Umali',
        'Vergara', 'Yap', 'Zamora', 'Belmonte', 'Cordero'];
    $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];

    // 20 and 25 have a least common multiple of 100, so the first 100 pairs are all different.
    $person = static function (int $i) use ($firstNames, $lastNames, $yearLevels): array {
        $first = $firstNames[$i % 20];
        $last = $lastNames[$i % 25];

        return [
            'email' => strtolower("{$first}.{$last}") . '@agile.test',
            'first_name' => $first,
            'last_name' => $last,
            'student_no' => sprintf('2025-%05d-OU-0', 500 + $i),
            'contact_no' => sprintf('0918300%04d', $i + 1),
            'year_level' => $yearLevels[$i % 4],
        ];
    };

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
    $isVacant = static function (int $positionId) use ($db): bool {
        $statement = $db->prepare("SELECT COUNT(*) FROM members WHERE position_id = ? AND role_status = 'active'");
        $statement->execute([$positionId]);

        return (int) $statement->fetchColumn() === 0;
    };
    $nextMemberNo = static function () use ($db): string {
        $prefix = 'AGILE-' . date('Y') . '-';
        $statement = $db->prepare('SELECT COALESCE(MAX(CAST(SUBSTRING(member_no, ?) AS UNSIGNED)), 0) FROM members WHERE member_no LIKE ?');
        $statement->execute([strlen($prefix) + 1, $prefix . '%']);

        return sprintf('%s%04d', $prefix, (int) $statement->fetchColumn() + 1);
    };
    $memberExists = static function (int $applicantId) use ($db): bool {
        $statement = $db->prepare('SELECT COUNT(*) FROM members WHERE applicant_id = ?');
        $statement->execute([$applicantId]);

        return (int) $statement->fetchColumn() > 0;
    };
    $applicant = static fn (array $p): int => seed_row($db, 'applicants', 'applicant_id', ['email' => $p['email']], [
        'first_name' => $p['first_name'],
        'last_name' => $p['last_name'],
        'student_no' => $p['student_no'],
        'contact_no' => $p['contact_no'],
        'year_level' => $p['year_level'],
        'email_verified_at' => $now,
    ]);

    $president = (int) $db->query("SELECT user_id FROM users WHERE email = 'president@agile.test'")->fetchColumn();
    $mswHead = (int) $db->query("SELECT user_id FROM users WHERE email = 'mswhead@agile.test'")->fetchColumn();
    $i = 0;

    // Former Executive Committee: last term's officers. Their role has expired and they stay general members (BR-13).
    $executive = ['President', 'Vice President for Internal Affairs', 'Vice President for External Affairs', 'General Secretary',
        'Assistant Secretary', 'Treasurer', 'Auditor'];
    $expiredAt = date('Y-m-d H:i:s', strtotime('-2 months'));

    foreach ($executive as $title) {
        $p = $person($i++);
        $applicantId = $applicant($p);

        if ($memberExists($applicantId)) {
            continue;
        }

        $memberId = seed_row($db, 'members', 'member_id', ['applicant_id' => $applicantId], [
            'member_no' => $nextMemberNo(),
            'membership_type' => 'general',
            'role_status' => 'expired',
            'role_expired_at' => $expiredAt,
            'joined_at' => date('Y-m-d', strtotime('-14 months')),
        ]);

        if ($president > 0) {
            $history = $db->prepare('INSERT INTO membership_status_history (member_id, old_role, new_role, reason, changed_by, changed_at) VALUES (?, ?, ?, ?, ?, ?)');
            $history->execute([$memberId, $title, 'General Member', 'Demo: term of office ended.', $president, $expiredAt]);
        }
    }

    // Current officers with no account yet: deputies where the seat is open, The Source Code, and committee members.
    $officers = [
        ['Research and Extension', 'Deputy Committee Head'],
        ['Creatives, Social Media and Communication', 'Deputy Committee Head'],
        ['Finance and Fundraising', 'Deputy Committee Head'],
    ];

    foreach (['News Editor', 'Feature Editor', 'Head Cartoonist', 'Layout Editor', 'Head Photojournalist', 'Photojournalist', 'Videographer/Editor'] as $title) {
        $officers[] = ['The Source Code', $title];
    }

    foreach (['Academics and Training', 'Research and Extension', 'Creatives, Social Media and Communication',
        'Finance and Fundraising', 'Logistics', 'Membership and Student Welfare', 'Events and External Relations'] as $committee) {
        for ($n = 0; $n < 3; $n++) {
            $officers[] = [$committee, 'Committee Member'];
        }
    }

    foreach ($officers as [$committee, $title]) {
        $p = $person($i++);
        $positionId = $position($committee, $title);

        // A seat someone already holds goes to a committee member instead, so no single-holder position gets two.
        if ($title !== 'Committee Member' && $positionId > 0 && !$isVacant($positionId)) {
            $positionId = $committee === 'The Source Code' ? $position($committee, 'Writer') : $position($committee, 'Committee Member');
        }

        if ($positionId === 0) {
            continue;
        }

        $applicantId = $applicant($p);

        if ($memberExists($applicantId)) {
            continue;
        }

        seed_row($db, 'members', 'member_id', ['applicant_id' => $applicantId], [
            'position_id' => $positionId,
            'member_no' => $nextMemberNo(),
            'membership_type' => 'officer',
            'role_status' => 'active',
            'joined_at' => $today,
        ]);
    }

    // Applications: general members (approved) and applicants at every other stage.
    $pdfPath = static fn (string $type): string => 'applications/demo/' . md5("demo-{$type}") . '.pdf';
    $pdfSize = (int) @filesize(App\Core\Config::basePath('storage/uploads/' . $pdfPath('cor')));
    // Every third applicant who is not yet approved applies for a committee's Committee Member position, so committee
    // heads have their own applicants to decide. They give their grades too; the demo reuses the COR file for it.
    $memberPositionIds = array_map('intval', $db->query(
        "SELECT p.position_id FROM positions p WHERE p.role_type = 'committee_member' ORDER BY p.committee_id"
    )->fetchAll(PDO::FETCH_COLUMN));
    $answers = [
        'What is your current year level?' => null,
        'Why do you want to join AGILE?' => 'I want to learn with other BSIT-OU students and help run AGILE activities.',
        'What skills, interests, or areas of Information Technology are you interested in developing?' => 'Programming, graphic design and event support.',
        'How would you like to participate in AGILE?' => 'By joining workshops and helping at events.',
        'Are you willing to participate in AGILE programs and activities based on your availability?' => 'Yes',
    ];
    $findQuestion = $db->prepare("SELECT question_id FROM application_questions WHERE question_text = ? AND role_type IN ('academic', 'general') ORDER BY is_active DESC LIMIT 1");
    $insertHistory = $db->prepare('INSERT INTO application_status_history (application_id, old_status, new_status, remarks, changed_by, changed_at) VALUES (?, ?, ?, ?, ?, ?)');

    // [status, how many, remarks]. The path through the status map (ApplicationStatus) is written to the history.
    $stages = [
        ['approved', 22, 'Welcome to AGILE.'],
        ['submitted', 10, null],
        ['under_review', 9, null],
        ['for_revision', 5, 'The certificate of registration is cut off. Please upload the whole page.'],
        ['waitlisted', 6, 'Qualified, but no slot is open yet.'],
        ['rejected', 5, 'Not enrolled in BSIT-OU this term.'],
        ['withdrawn', 3, null],
        ['draft', 2, null],
    ];
    $paths = [
        'approved' => ['submitted', 'under_review', 'approved'],
        'submitted' => ['submitted'],
        'under_review' => ['submitted', 'under_review'],
        'for_revision' => ['submitted', 'under_review', 'for_revision'],
        'waitlisted' => ['submitted', 'under_review', 'waitlisted'],
        'rejected' => ['submitted', 'under_review', 'rejected'],
        'withdrawn' => ['submitted', 'withdrawn'],
        'draft' => [],
    ];
    $registered = 0;

    foreach ($stages as [$status, $count, $remarks]) {
        for ($n = 0; $n < $count; $n++) {
            $p = $person($i++);
            $code = sprintf('DEMBLK%06d', $i);
            $exists = $db->prepare('SELECT COUNT(*) FROM membership_applications WHERE tracking_code = ?');
            $exists->execute([$code]);

            if ((int) $exists->fetchColumn() > 0) {
                continue;
            }

            $applicantId = $applicant($p);
            // Spread submissions over the last 30 days, so lists have a real order.
            $submittedAt = date('Y-m-d H:i:s', strtotime("-{$i} hours", strtotime('-' . ($i % 30) . ' days')));
            $isDraft = $status === 'draft';
            $isDecided = in_array($status, ['approved', 'waitlisted', 'rejected'], true);
            $positionId = $status !== 'approved' && $memberPositionIds !== [] && $n % 3 === 0 ? $memberPositionIds[$i % count($memberPositionIds)] : null;

            $applicationId = seed_row($db, 'membership_applications', 'application_id', ['tracking_code' => $code], [
                'applicant_id' => $applicantId,
                'position_id' => $positionId,
                'status' => $status,
                'wizard_step' => $isDraft ? 3 : 6,
                'decision_remarks' => $isDecided ? $remarks : null,
                'decided_by' => $isDecided && $mswHead > 0 ? $mswHead : null,
                'decided_at' => $isDecided ? $now : null,
                'consent_at' => $submittedAt,
                'enrollment_confirmed_at' => $isDraft ? null : $submittedAt,
                'submitted_at' => $isDraft ? null : $submittedAt,
            ]);

            foreach ($answers as $question => $answer) {
                $findQuestion->execute([$question]);
                $questionId = $findQuestion->fetchColumn();

                if ($questionId !== false) {
                    seed_row($db, 'application_answers', 'answer_id', ['application_id' => $applicationId, 'question_id' => $questionId], ['answer_text' => $answer ?? $p['year_level']]);
                }
            }

            if ($isDraft) {
                continue;
            }

            $verified = !in_array($status, ['submitted', 'withdrawn', 'for_revision'], true);

            $types = $positionId !== null ? ['cor', 'grades'] : ['cor'];

            foreach ($types as $type) {
                $needsFix = $status === 'for_revision' && $type === 'cor';
                seed_row($db, 'application_documents', 'document_id', ['application_id' => $applicationId, 'doc_type' => $type], [
                    'original_name' => "{$type}.pdf",
                    'stored_path' => $pdfPath('cor'),
                    'mime_type' => 'application/pdf',
                    'size_bytes' => $pdfSize,
                    'verification_status' => $needsFix ? 'needs_correction' : ($verified || $status === 'for_revision' ? 'verified' : 'pending'),
                    'verification_remarks' => $needsFix ? $remarks : null,
                ]);
            }

            $previous = 'draft';

            foreach ($paths[$status] as $next) {
                $insertHistory->execute([$applicationId, $previous, $next, $next === $status ? $remarks : null, null, $submittedAt]);
                $previous = $next;
            }

            if ($status !== 'approved' || $memberExists($applicantId)) {
                continue;
            }

            $memberId = seed_row($db, 'members', 'member_id', ['applicant_id' => $applicantId], [
                'member_no' => $nextMemberNo(),
                'membership_type' => 'general',
                'joined_at' => $today,
            ]);

            // The first 6 approved members have registered and been approved (BR-21), so they can sign in.
            if ($registered < 6) {
                $registered++;
                $userId = seed_row($db, 'users', 'user_id', ['email' => $p['email']], [
                    'role_id' => $role('general_member'),
                    'password_hash' => password_hash('Password123!', PASSWORD_DEFAULT),
                    'first_name' => $p['first_name'],
                    'last_name' => $p['last_name'],
                    'student_no' => $p['student_no'],
                    'contact_no' => $p['contact_no'],
                    'status' => 'active',
                    'email_verified_at' => $now,
                ]);
                $db->prepare('UPDATE applicants SET user_id = ? WHERE applicant_id = ?')->execute([$userId, $applicantId]);
                $db->prepare('UPDATE members SET user_id = ?, registered_at = ? WHERE member_id = ?')->execute([$userId, $now, $memberId]);
            }
        }
    }
};
