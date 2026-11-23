<?php

declare(strict_types=1);

use App\Core\Config;
use App\Services\EncryptionService;

// Outside production only: seeded activity in every module, so each page has something to show:
// welfare cases with notes, follow-ups, referrals and resolutions; HR requests and vacancies; interviews and an
// appointment; candidacies; officer terms; a second committee; ID requests; email drafts. Everyone is a seeded
// account (@agile.test) and every text is made up. Each part checks for its own rows
// first, so running it again adds nothing. People missing from a smaller seeded set are skipped.
return static function (PDO $db): void {
    $now = time();
    $at = static fn (string $offset): string => date('Y-m-d H:i:s', strtotime($offset, $now));
    $user = static function (string $email) use ($db): int {
        $statement = $db->prepare('SELECT user_id FROM users WHERE email = ?');
        $statement->execute([$email]);

        return (int) $statement->fetchColumn();
    };
    $memberOf = static function (int $userId) use ($db): int {
        $statement = $db->prepare('SELECT member_id FROM members WHERE user_id = ?');
        $statement->execute([$userId]);

        return (int) $statement->fetchColumn();
    };
    $position = static function (string $committee, string $title) use ($db): int {
        $statement = $db->prepare(
            "SELECT p.position_id FROM positions p LEFT JOIN committees c ON c.committee_id = p.committee_id
             WHERE p.title = ? AND ((? = '' AND p.committee_id IS NULL) OR c.name = ?) LIMIT 1"
        );
        $statement->execute([$title, $committee, $committee]);

        return (int) $statement->fetchColumn();
    };
    $committee = static function (string $name) use ($db): int {
        $statement = $db->prepare('SELECT committee_id FROM committees WHERE name = ?');
        $statement->execute([$name]);

        return (int) $statement->fetchColumn();
    };
    $count = static function (string $sql, array $params = []) use ($db): int {
        $statement = $db->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    };
    // A copy of a seeded photo in storage/uploads, as a stand-in document. The repo file stays where it is.
    $copy = static function (string $photo, string $folder): ?string {
        $source = __DIR__ . '/seed-media/' . $photo;
        $stored = $folder . '/' . date('Y/m') . '/' . bin2hex(random_bytes(16)) . '.jpg';
        $target = Config::basePath('storage/uploads/' . $stored);

        if (!is_file($source) || (!is_dir(dirname($target)) && !mkdir(dirname($target), 0750, true) && !is_dir(dirname($target)))) {
            return null;
        }

        return copy($source, $target) ? $stored : null;
    };
    $encryption = new EncryptionService();
    $mswHead = $user('mswhead@agile.test');
    $mswMember = $user('mswmember@agile.test');

    if ($mswHead === 0) {
        return;
    }

    // ---------- Welfare cases ----------
    if ($count('SELECT COUNT(*) FROM welfare_cases WHERE case_code = ?', [seed_code('case:1')]) === 0) {
        $categories = $db->query('SELECT category_id FROM welfare_categories ORDER BY category_id')->fetchAll(PDO::FETCH_COLUMN);
        $cases = [
            // [owner email or guest name, category index, status, priority, urgent, days ago, subject, description, year, section, contact]
            ['member@agile.test', 0, 'submitted', 'medium', false, 1, 'Cannot enroll in Data Structures', 'The subject is full and I need it to graduate on time. Is there a way to get a slot?', '3rd Year', 'BSITOUMN 3-1', '09171230101'],
            ['rosa.garcia@agile.test', 1, 'submitted', 'high', true, 0, 'Tuition balance before finals', 'I have a balance and cannot take finals until it is paid. I am looking for any scholarship or installment option.', '2nd Year', 'BSITOUMN 2-2', '09171230102'],
            ['guest:Lara Dimaculangan', 2, 'in_progress', 'high', false, 6, 'Stress during midterms', 'I work full time and feel overwhelmed balancing work and classes. I would like to talk to someone.', '4th Year', 'BSITOUMN 4-1', '09171230103'],
            ['trisha.olivares@agile.test', 0, 'in_progress', 'medium', false, 9, 'Grade not yet posted', 'My grade in Systems Analysis is still not posted and the deadline for the dean\'s list is near.', '3rd Year', 'BSITOUMN 3-2', '09171230104'],
            ['guest:Paolo Ventura', 4, 'referred', 'high', false, 12, 'Classmate harassment in group chat', 'A classmate keeps posting mean comments about me in our group chat.', '1st Year', 'BSITOUMN 1-1', '09171230105'],
            ['vince.panganiban@agile.test', 3, 'resolved', 'medium', false, 20, 'Scholarship requirements', 'I do not know which documents the scholarship office needs for renewal.', '2nd Year', 'BSITOUMN 2-1', '09171230106'],
            ['aaron.quiambao@agile.test', 1, 'closed', 'low', false, 35, 'Laptop for online classes', 'My laptop broke and I need one for online classes this semester.', '1st Year', 'BSITOUMN 1-2', '09171230107'],
            ['guest:Mica Robledo', 0, 'closed', 'medium', false, 40, 'Missing COR', 'I cannot download my COR from the student portal.', '2nd Year', 'BSITOUMN 2-3', '09171230108'],
        ];
        $insert = $db->prepare(
            'INSERT INTO welfare_cases (case_code, user_id, guest_name, guest_email, guest_email_hash, category_id, priority, student_marked_urgent, subject, description,
                 submitter_contact_no, submitter_year_level, submitter_section, assigned_to, status, acknowledged_at, closing_remarks, closed_by, submitted_at, closed_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $history = $db->prepare('INSERT INTO welfare_case_history (case_id, event_type, old_value, new_value, actor_id) VALUES (?, ?, ?, ?, ?)');
        $note = $db->prepare('INSERT INTO case_notes (case_id, author_id, note, visible_to_student) VALUES (?, ?, ?, ?)');

        foreach ($cases as $index => [$owner, $category, $status, $priority, $urgent, $daysAgo, $subject, $description, $year, $section, $contact]) {
            $isGuest = str_starts_with($owner, 'guest:');
            $ownerId = $isGuest ? 0 : $user($owner);

            if (!$isGuest && $ownerId === 0) {
                continue;
            }

            $guestName = $isGuest ? substr($owner, 6) : null;
            $guestEmail = $isGuest ? strtolower(str_replace(' ', '.', $guestName)) . '@agile.test' : null;
            $submitted = $at("-{$daysAgo} days");
            $closed = in_array($status, ['closed'], true) ? $at('-' . max(0, $daysAgo - 5) . ' days') : null;
            $insert->execute([
                seed_code('case:' . ($index + 1)), $isGuest ? null : $ownerId,
                $isGuest ? $encryption->encrypt($guestName) : null, $isGuest ? $encryption->encrypt($guestEmail) : null, $isGuest ? $encryption->blindIndex($guestEmail) : null,
                (int) ($categories[$category % max(1, count($categories))] ?? 1), $priority, $urgent ? 1 : 0,
                $encryption->encrypt($subject), $encryption->encrypt($description),
                $encryption->encrypt($contact), $encryption->encrypt($year), $encryption->encrypt($section),
                $status === 'submitted' ? null : $mswHead, $status,
                $status === 'submitted' ? null : $at('-' . max(0, $daysAgo - 1) . ' days'),
                $closed === null ? null : $encryption->encrypt('The student confirmed the concern is settled.'),
                $closed === null ? null : $mswHead, $submitted, $closed,
            ]);
            $caseId = (int) $db->lastInsertId();
            $history->execute([$caseId, 'status_change', null, 'submitted', $isGuest ? null : $ownerId]);

            if ($status !== 'submitted') {
                $history->execute([$caseId, 'status_change', 'submitted', 'in_progress', $mswHead]);
                $note->execute([$caseId, $mswHead, $encryption->encrypt('Called the student; they agreed to a follow-up this week.'), 0]);
                $note->execute([$caseId, $mswHead, $encryption->encrypt('We are looking into this and will update you within three days.'), 1]);
            }

            if ($status === 'in_progress') {
                $db->prepare('INSERT INTO case_followups (case_id, due_at, handler_id) VALUES (?, ?, ?)')->execute([$caseId, $at('+2 days'), $mswHead]);
                $db->prepare('INSERT INTO case_followups (case_id, due_at, handler_id, done_at, remarks) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$caseId, $at('-3 days'), $mswHead, $at('-3 days'), $encryption->encrypt('First check-in done.')]);
            }

            if ($status === 'referred') {
                $history->execute([$caseId, 'status_change', 'in_progress', 'referred', $mswHead]);
                $db->prepare('INSERT INTO referrals (case_id, referred_to, remarks, status, referred_by, referred_at) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$caseId, $encryption->encrypt('PUP OUS Guidance and Counseling Office'), $encryption->encrypt('Referred with the student\'s consent for counseling.'), 'accepted', $mswHead, $at('-8 days')]);
            }

            if (in_array($status, ['resolved', 'closed'], true)) {
                $history->execute([$caseId, 'status_change', 'in_progress', 'resolved', $mswHead]);
                $db->prepare('INSERT INTO case_resolutions (case_id, actions_taken, outcome, summary, resolved_by, resolved_at) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$caseId, $encryption->encrypt('Explained the steps and connected the student with the right office.'), 'resolved', $encryption->encrypt('The student got what they needed.'), $mswHead, $at('-' . max(0, $daysAgo - 4) . ' days')]);
            }

            if ($status === 'closed') {
                $history->execute([$caseId, 'status_change', 'resolved', 'closed', $mswHead]);
            }
        }
    }

    // ---------- HR requests and vacancies ----------
    if ($count("SELECT COUNT(*) FROM hr_requests WHERE justification LIKE '%Committee needs more hands for this semester%'") === 0) {
        $requests = [
            // [head email, committee, position title, slots, status, remarks, vacancy status or null]
            ['ramon.agustin@agile.test', 'Logistics', 'Committee Member', 2, 'approved', 'Approved for the General Assembly season.', 'open'],
            ['bea.navarro@agile.test', 'Creatives, Social Media and Communication', 'Committee Member', 1, 'approved', 'Approved.', 'closed'],
            ['ella.soriano@agile.test', 'Finance and Fundraising', 'Committee Member', 1, 'submitted', null, null],
            ['irene.castillo@agile.test', 'Research and Extension', 'Deputy Committee Head', 1, 'returned', 'Please add the qualifications you need.', null],
        ];

        foreach ($requests as [$email, $committeeName, $title, $slots, $status, $remarks, $vacancyStatus]) {
            $head = $user($email);
            $committeeId = $committee($committeeName);
            $positionId = $position($committeeName, $title);

            if ($head === 0 || $committeeId === 0 || $positionId === 0) {
                continue;
            }

            $db->prepare('INSERT INTO hr_requests (committee_id, position_id, requested_by, slots, justification, qualifications, status, remarks, approved_by, approved_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$committeeId, $positionId, $head, $slots, "The {$committeeName} Committee needs more hands for this semester's activities.", 'Organized, can attend Saturday meetings, comfortable with Google Workspace.', $status, $remarks,
                    $status === 'approved' ? $mswHead : null, $status === 'approved' ? $at('-10 days') : null]);
            $requestId = (int) $db->lastInsertId();

            if ($vacancyStatus !== null) {
                $open = $vacancyStatus === 'open';
                $db->prepare('INSERT INTO vacancies (request_id, position_id, title, description, qualifications, slots, slots_filled, opens_at, closes_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$requestId, $positionId, "{$title}, {$committeeName}", "Help the {$committeeName} Committee run AGILE's activities this semester.", 'Organized and reliable; available on Saturdays.',
                        $slots, $open ? 0 : $slots, $at($open ? '-3 days' : '-40 days'), $at($open ? '+14 days' : '-20 days'), $open ? 'open' : 'filled']);
            }
        }
    }

    // ---------- Interviews and evaluations ----------
    if ($mswMember !== 0 && $count('SELECT COUNT(*) FROM interviews') < 6) {
        $waiting = $db->query("SELECT application_id FROM membership_applications WHERE status IN ('submitted', 'under_review') AND application_id NOT IN (SELECT application_id FROM interviews) ORDER BY application_id LIMIT 6")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($waiting as $index => $applicationId) {
            $past = $index >= 4;
            $db->prepare('INSERT INTO interviews (application_id, interviewer_id, scheduled_at, mode, location, result, notes, identity_verified, role_confirmed, privacy_reminder_given, conflict_of_interest, checklist_by, checklist_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([(int) $applicationId, $mswMember, $at(($past ? '-' : '+') . (2 + $index) . ' days 14:00'), $index % 2 === 0 ? 'online' : 'onsite',
                    $index % 2 === 0 ? 'https://example.com/agile-interview' : 'NALLRC, Room 204', $past ? 'passed' : 'pending', $past ? 'Clear answers; ready to help with events.' : null,
                    $past ? 1 : 0, $past ? 1 : 0, $past ? 1 : 0, $past ? 0 : null, $past ? $mswMember : null, $past ? $at('-' . (2 + $index) . ' days 14:00') : null]);
        }
    }

    // ---------- Assessments after a passed interview (AGILE's Applicant Evaluation sheet) ----------
    if ($mswMember !== 0) {
        $assessed = $db->query(
            "SELECT DISTINCT i.application_id FROM interviews i JOIN membership_applications a ON a.application_id = i.application_id
             WHERE i.result = 'passed' AND a.status NOT IN ('draft', 'approved', 'rejected', 'withdrawn')
               AND i.application_id NOT IN (SELECT application_id FROM applicant_assessments) ORDER BY i.application_id"
        )->fetchAll(PDO::FETCH_COLUMN);
        $profiles = [
            [[4, 4, 3, 4, 5], [4, 4, 3, 4, 4, 5], [4], 'recommended', 'Organized and eager to help at events.', 'Speak up more in group discussions.'],
            [[5, 4, 4, 5, 4], [5, 4, 4, 5, 4, 4], [5], 'highly_recommended', 'Clear communicator with event experience.', 'Learn the committee\'s documentation steps.'],
            [[3, 3, 3, 3, 4], [3, 3, 3, 4, 3, 4], [3], 'recommended_with_considerations', 'Willing and available on weekends.', 'Needs guidance on deadlines.'],
        ];

        foreach ($assessed as $index => $applicationId) {
            [$application, $interview, $role, $recommendation, $strengths, $development] = $profiles[$index % count($profiles)];
            $ratings = [];

            foreach (App\Services\AssessmentService::SECTIONS as $section => [, $criteria]) {
                $values = ['application' => $application, 'interview' => $interview, 'role' => $role][$section];

                foreach ($criteria as $i => $criterion) {
                    $ratings[$section][] = ['criterion' => $criterion, 'rating' => $values[$i], 'remark' => $i === 0 ? 'Answered with clear examples.' : ''];
                }
            }

            $db->prepare('INSERT INTO applicant_assessments (application_id, evaluator_id, ratings_json, application_score, interview_score, role_score, total_score, recommendation, strengths, development, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([(int) $applicationId, $mswMember, json_encode($ratings, JSON_THROW_ON_ERROR), array_sum($application), array_sum($interview), array_sum($role),
                    array_sum($application) + array_sum($interview) + array_sum($role), $recommendation, $strengths, $development, 'Ready for the committee. Best placed where there is event work.']);
        }
    }

    // ---------- An appointment for the approved vacancy application ----------
    if ($count('SELECT COUNT(*) FROM appointments') === 0) {
        $approved = $db->query(
            "SELECT a.application_id, v.position_id, m.member_id FROM membership_applications a
             JOIN vacancies v ON v.vacancy_id = a.vacancy_id
             LEFT JOIN members m ON m.user_id = a.user_id
             WHERE a.status = 'approved' LIMIT 1"
        )->fetch();

        if ($approved !== false && $mswMember !== 0) {
            $db->prepare('INSERT INTO appointments (application_id, member_id, position_id, status, prepared_by, remarks, effective_date) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([(int) $approved['application_id'], $approved['member_id'] ?: null, (int) $approved['position_id'], 'prepared', $mswMember, 'Prepared for the MSW Committee Head to approve.', date('Y-m-d', strtotime('+7 days', $now))]);
        }
    }

    // ---------- Officer terms (Article VI Section 4) ----------
    $term = $db->query('SELECT academic_year FROM academic_terms WHERE is_current = 1')->fetchColumn();
    $start = (int) date('n', $now) >= 8 ? (int) date('Y', $now) : (int) date('Y', $now) - 1;
    $academicYear = is_string($term) && $term !== '' ? $term : $start . '-' . ($start + 1);
    $previousYear = ((int) substr($academicYear, 0, 4) - 1) . '-' . substr($academicYear, 0, 4);

    if ($count('SELECT COUNT(*) FROM officer_terms') === 0) {
        $holders = $db->query(
            "SELECT m.member_id, m.position_id, COALESCE(u.year_level, a.year_level, '3rd Year') AS year_level, p.title
             FROM members m JOIN positions p ON p.position_id = m.position_id
             LEFT JOIN committees c ON c.committee_id = p.committee_id
             LEFT JOIN users u ON u.user_id = m.user_id LEFT JOIN applicants a ON a.applicant_id = m.applicant_id
             WHERE m.role_status = 'active' AND (p.committee_id IS NULL OR c.name = 'Year-Level Council')"
        )->fetchAll();
        $insert = $db->prepare('INSERT INTO officer_terms (member_id, position_id, academic_year, year_level, recorded_by) VALUES (?, ?, ?, ?, ?)');

        foreach ($holders as $holder) {
            $insert->execute([(int) $holder['member_id'], (int) $holder['position_id'], $academicYear, (string) $holder['year_level'], $mswHead]);

            // The President is serving a second term: the term limit page then has something to count.
            if ($holder['title'] === 'President') {
                $insert->execute([(int) $holder['member_id'], (int) $holder['position_id'], $previousYear, '3rd Year', $mswHead]);
            }
        }
    }

    // ---------- Candidacies ----------
    if ($count('SELECT COUNT(*) FROM election_candidacies') === 0) {
        $candidates = [
            // [email, committee ('' = Executive Board), title, year, section, status, remarks]
            ['rosa.garcia@agile.test', 'Year-Level Council', '2nd Year Representative', '2nd Year', 'BSITOUMN 2-2', 'qualified', 'Documents complete.'],
            ['trisha.olivares@agile.test', '', 'Vice President for Internal Affairs', '3rd Year', 'BSITOUMN 3-2', 'filed', null],
            ['vince.panganiban@agile.test', '', 'Treasurer', '2nd Year', 'BSITOUMN 2-1', 'disqualified', 'The copy of grades shows a W in one subject (Article VI, Section 2).'],
            ['aaron.quiambao@agile.test', 'Year-Level Council', '1st Year Representative', '1st Year', 'BSITOUMN 1-2', 'filed', null],
            ['bella.rosales@agile.test', '', 'General Secretary', '3rd Year', 'BSITOUMN 3-1', 'withdrawn', 'Withdrawn by the candidate.'],
            ['member@agile.test', '', 'Auditor', '3rd Year', 'BSITOUMN 3-3', 'qualified', 'Documents complete.'],
        ];

        foreach ($candidates as $index => [$email, $committeeName, $title, $year, $section, $status, $remarks]) {
            $candidate = $user($email);
            $positionId = $position($committeeName, $title);

            if ($candidate === 0 || $positionId === 0 || $memberOf($candidate) === 0) {
                continue;
            }

            $cor = $copy('publication-newsletter.jpg', 'candidacies');
            $grades = $copy('news-review-sessions.jpg', 'candidacies');

            if ($cor === null || $grades === null) {
                continue;
            }

            $contact = sprintf('091712302%02d', $index + 1);
            $db->prepare('UPDATE users SET year_level = ?, section = ?, contact_no = ? WHERE user_id = ?')->execute([$year, $section, $contact, $candidate]);
            $db->prepare('INSERT INTO election_candidacies (user_id, position_id, academic_year, year_level, section, contact_no, statement, cor_path, grades_path, status, remarks, decided_by, decided_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$candidate, $positionId, $academicYear, $year, $section, $contact, 'I want more review sessions and a faster way to ask for help.', $cor, $grades, $status, $remarks,
                    in_array($status, ['qualified', 'disqualified'], true) ? $mswHead : null, $status === 'filed' ? null : $at('-2 days')]);
        }
    }

    // ---------- A second committee (P4) ----------
    if ($count('SELECT COUNT(*) FROM member_committees') === 0) {
        $memberId = $count(
            "SELECT m.member_id FROM members m JOIN positions p ON p.position_id = m.position_id JOIN committees c ON c.committee_id = p.committee_id
             WHERE p.role_type = 'committee_member' AND c.name = 'Academics and Training' AND m.role_status = 'active' LIMIT 1"
        );
        $events = $committee('Events and External Relations');

        if ($memberId !== 0 && $events !== 0) {
            $db->prepare('INSERT INTO member_committees (member_id, committee_id, joined_at, added_by) VALUES (?, ?, ?, ?)')->execute([$memberId, $events, date('Y-m-d', strtotime('-14 days', $now)), $mswHead]);
        }
    }

    // ---------- Event registrations: the next event has registrants; the last past one has attendance ----------
    // INSERT IGNORE: one row per member and event, so a registration someone already made is left as it is.
    if ($count("SELECT COUNT(*) FROM content_posts WHERE type = 'event' AND status = 'published'") > 0) {
        $events = $db->query("SELECT post_id, event_start FROM content_posts WHERE type = 'event' AND status = 'published' AND event_start IS NOT NULL ORDER BY event_start")->fetchAll(PDO::FETCH_ASSOC);
        $upcoming = array_values(array_filter($events, static fn (array $e): bool => $e['event_start'] > date('Y-m-d H:i:s')))[0] ?? null;
        $past = array_values(array_filter($events, static fn (array $e): bool => $e['event_start'] <= date('Y-m-d H:i:s')));
        $past = $past === [] ? null : end($past);
        $people = ['member@agile.test', 'rosa.garcia@agile.test', 'trisha.olivares@agile.test', 'vince.panganiban@agile.test', 'aaron.quiambao@agile.test', 'bella.rosales@agile.test'];
        $insert = $db->prepare('INSERT IGNORE INTO event_registrations (post_id, member_id, walk_in, registered_at, attended_at, checked_in_by) VALUES (?, ?, ?, ?, ?, ?)');

        foreach ($people as $index => $email) {
            $memberId = $memberOf($user($email));

            if ($memberId === 0) {
                continue;
            }

            if ($upcoming !== null && $index < 4) {
                $insert->execute([(int) $upcoming['post_id'], $memberId, 0, $at('-1 day 10:00'), null, null]);
            }

            if ($past !== null && $mswMember !== 0) {
                $walkIn = $index === 5;
                $came = $index !== 4;
                $insert->execute([(int) $past['post_id'], $memberId, $walkIn ? 1 : 0, $walkIn ? $past['event_start'] : date('Y-m-d H:i:s', strtotime($past['event_start'] . ' -3 days')),
                    $came ? $past['event_start'] : null, $came ? $mswMember : null]);
            }
        }
    }

    // ---------- Service requests: one of each status, with a short thread ----------
    if ($mswMember !== 0 && $count("SELECT COUNT(*) FROM service_requests WHERE subject = 'My section is wrong'") === 0) {
        $requests = [
            ['member@agile.test', 'record_update', 'My section is wrong', 'open', 'My record says BSITOUMN 3-1, but I moved to 3-2 this semester.', null],
            ['rosa.garcia@agile.test', 'document', 'Endorsement letter for an internship', 'in_progress', 'May I ask for an endorsement letter from AGILE for my internship application?', 'Noted. We will prepare it and send it within the week.'],
            ['trisha.olivares@agile.test', 'technical', 'I cannot upload my COR', 'resolved', 'The upload says the file is too large.', 'Please save it as a PDF under 5 MB. It should go through now.'],
            ['vince.panganiban@agile.test', 'inquiry', 'When is the General Assembly?', 'closed', 'Is there a date for the General Assembly?', 'It is on the Events page. See you there!'],
        ];
        $insertRequest = $db->prepare('INSERT INTO service_requests (user_id, type, subject, status, assigned_to, resolved_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $insertMessage = $db->prepare('INSERT INTO service_request_messages (request_id, author_id, from_staff, body, created_at) VALUES (?, ?, ?, ?, ?)');

        foreach ($requests as $index => [$email, $type, $subject, $status, $question, $answer]) {
            $requester = $user($email);

            if ($requester === 0) {
                continue;
            }

            $sent = $at('-' . (6 - $index) . ' days 09:30');
            $insertRequest->execute([$requester, $type, $subject, $status, $answer === null ? null : $mswMember, in_array($status, ['resolved', 'closed'], true) ? $at('-' . (5 - $index) . ' days 15:00') : null, $sent]);
            $requestId = (int) $db->lastInsertId();
            $insertMessage->execute([$requestId, $requester, 0, $question, $sent]);

            if ($answer !== null) {
                $insertMessage->execute([$requestId, $mswMember, 1, $answer, $at('-' . (5 - $index) . ' days 14:00')]);
            }
        }
    }

    // ---------- ID and certificate requests ----------
    if ($count("SELECT COUNT(*) FROM credential_requests WHERE note = 'For the General Assembly.'") === 0) {
        $requests = [
            ['rosa.garcia@agile.test', 'digital_id', 'requested', 'For the General Assembly.', null, true],
            ['trisha.olivares@agile.test', 'certificate', 'requested', 'For my scholarship application.', null, false],
            ['vince.panganiban@agile.test', 'digital_id', 'declined', 'Please use my new photo.', 'The picture is not half-body. Please send a new one.', true],
            ['aaron.quiambao@agile.test', 'certificate', 'cancelled', 'Sent by mistake.', null, false],
        ];

        foreach ($requests as [$email, $type, $status, $note, $remarks, $withPhoto]) {
            $memberId = $memberOf($user($email));

            if ($memberId === 0) {
                continue;
            }

            $db->prepare('INSERT INTO credential_requests (member_id, type, note, photo_path, status, remarks, handled_by, handled_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$memberId, $type, $note, $withPhoto ? $copy('news-quiet-hour.jpg', 'id-photos') : null, $status, $remarks,
                    $status === 'declined' ? $mswHead : null, $status === 'requested' ? null : $at('-1 day')]);
        }
    }

    // ---------- Email drafts ----------
    if ($count("SELECT COUNT(*) FROM email_drafts WHERE subject = 'See you at the General Assembly'") === 0) {
        $drafts = [
            ['See you at the General Assembly', '<p>Hello AGILE members,</p><p>Join us at the General Assembly in the NALLRC Function Hall. Bring your digital ID.</p>', 'all_members', 'draft', null],
            ['Officers\' meeting this Saturday', '<p>Officers, please attend the planning meeting this Saturday at 9 AM, online.</p>', 'officers', 'draft', null],
            ['Your interview schedule is ready', '<p>Thank you for applying to AGILE. Your interview schedule is now in your email from the MSW team.</p>', 'applicants', 'sent', $at('-3 days')],
        ];

        foreach ($drafts as [$subject, $body, $target, $status, $sentAt]) {
            $db->prepare('INSERT INTO email_drafts (subject, body_html, target_group, status, created_by, sent_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([$subject, $body, $target, $status, $mswHead, $sentAt]);
        }
    }
};
