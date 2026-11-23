<?php

declare(strict_types=1);

// People with no account (BR-18), including one on the waiting list. Three have a submitted application at a different stage, and the third of those
// has its documents verified, so an approval can be tried without uploading anything. The fourth is already approved
// and has a member number but has not registered, so /register can be tried at once.
return static function (PDO $db): void {
    $folder = App\Core\Config::basePath('storage/uploads/applications/sample');

    if (!is_dir($folder)) {
        mkdir($folder, 0775, true);
    }

    $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

    // Stored file names must be 32 hex characters, or FileStorageService refuses to open them.
    $storedName = static fn (string $type): string => md5("sample-{$type}") . '.pdf';

    foreach (['school_id', 'cor'] as $type) {
        if (!is_file("{$folder}/{$storedName($type)}")) {
            file_put_contents("{$folder}/{$storedName($type)}", $pdf);
        }

        // The earlier name, from before the rows were corrected. Only these two sample files are ever removed here.
        if (is_file("{$folder}/{$type}.pdf")) {
            unlink("{$folder}/{$type}.pdf");
        }
    }

    // [email, first name, last name, student no, contact no, tracking code, status, document status, remarks]
    $people = [
        ['andrea.santos@agile.test', 'Andrea', 'Santos', '2024-00111-OU-0', '09170001111', 'FJKBRQJTPMC5', 'submitted', 'pending', null],
        ['jose.delacruz@agile.test', 'Jose', 'Dela Cruz', '2024-00112-OU-0', '09170001112', 'FTAVJNRA69R4', 'for_revision', 'needs_correction', 'The COR photo is too dark to read. Please upload a clearer copy.'],
        ['maria.lim@agile.test', 'Maria', 'Lim', '2024-00113-OU-0', '09170001113', '6M3DRHUUHT49', 'under_review', 'verified', null],
        ['lito.ramos@agile.test', 'Lito', 'Ramos', '2024-00114-OU-0', '09170001114', 'GJMK2Q7UT6A7', 'approved', 'verified', 'Welcome to AGILE.'],
        ['rosa.garcia@agile.test', 'Rosa', 'Garcia', '2024-00115-OU-0', '09170001115', 'DDVMBQCGKZSN', 'approved', 'verified', 'Welcome to AGILE.'],
        ['ana.villanueva@agile.test', 'Ana', 'Villanueva', '2024-00116-OU-0', '09170001116', 'CHZE8VH86R3C', 'waitlisted', 'verified', 'Qualified, but no slot is open yet.'],
    ];

    $answers = [
        'What is your current year level?' => '2nd Year',
        'Why do you want to join AGILE?' => 'I want to study with other BSIT-OU students and help with org activities.',
        'What skills, interests, or areas of Information Technology are you interested in developing?' => 'Web development and technical writing.',
        'How would you like to participate in AGILE?' => 'By helping in workshops and writing event recaps.',
        'Are you willing to participate in AGILE programs and activities based on your availability?' => 'Yes',
    ];
    // The general-membership questions of AGILE's registration form (an older copy of a question may be inactive).
    $findQuestion = $db->prepare("SELECT question_id FROM application_questions WHERE question_text = ? AND role_type IN ('academic', 'general') ORDER BY is_active DESC LIMIT 1");
    $now = date('Y-m-d H:i:s');

    foreach ($people as [$email, $firstName, $lastName, $studentNo, $contactNo, $code, $status, $documentStatus, $remarks]) {
        $applicantId = seed_row($db, 'applicants', 'applicant_id', ['email' => $email], [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'student_no' => $studentNo,
            'contact_no' => $contactNo,
            'year_level' => '2nd Year',
            'email_verified_at' => $now,
        ]);

        $exists = $db->prepare('SELECT COUNT(*) FROM membership_applications WHERE tracking_code = ?');
        $exists->execute([$code]);

        if ((int) $exists->fetchColumn() > 0) {
            continue;
        }

        $applicationId = seed_row($db, 'membership_applications', 'application_id', ['tracking_code' => $code], [
            'applicant_id' => $applicantId,
            'status' => $status,
            'wizard_step' => 6,
            'consent_at' => $now,
            'enrollment_confirmed_at' => $now,
            'submitted_at' => $now,
        ]);

        foreach ($answers as $question => $answer) {
            $findQuestion->execute([$question]);
            seed_row($db, 'application_answers', 'answer_id', ['application_id' => $applicationId, 'question_id' => $findQuestion->fetchColumn()], ['answer_text' => $answer]);
        }

        // General Membership needs the current COR only (AGILE's membership requirements).
        seed_row($db, 'application_documents', 'document_id', ['application_id' => $applicationId, 'doc_type' => 'cor'], [
            'original_name' => 'cor.pdf',
            'stored_path' => 'applications/sample/' . $storedName('cor'),
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($pdf),
            'verification_status' => $documentStatus,
            'verification_remarks' => $documentStatus === 'needs_correction' ? $remarks : null,
        ]);

        $history = $db->prepare('INSERT INTO application_status_history (application_id, old_status, new_status, remarks, changed_by, changed_at) VALUES (?, ?, ?, ?, NULL, ?)');
        $history->execute([$applicationId, 'draft', 'submitted', null, $now]);

        if ($status !== 'submitted') {
            $history->execute([$applicationId, 'submitted', $status, $remarks ?? 'Documents checked.', $now]);
        }

        if ($status === 'approved') {
            $memberId = seed_row($db, 'members', 'member_id', ['applicant_id' => $applicantId], [
                'member_no' => sprintf('AGILE-%s-%04d', date('Y'), $code === 'GJMK2Q7UT6A7' ? 100 : 101),
                'membership_type' => 'general',
                'joined_at' => date('Y-m-d'),
            ]);

            // Rosa has registered, so her account waits for the MSW Committee Head (BR-21).
            if ($code === 'DDVMBQCGKZSN') {
                $role = $db->query("SELECT role_id FROM roles WHERE slug = 'general_member'")->fetchColumn();
                $userId = seed_row($db, 'users', 'user_id', ['email' => $email], [
                    'role_id' => $role,
                    'password_hash' => password_hash('Password123!', PASSWORD_DEFAULT),
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'student_no' => $studentNo,
                    'contact_no' => $contactNo,
                    'status' => 'pending',
                    'email_verified_at' => $now,
                ]);
                $db->prepare('UPDATE applicants SET user_id = ? WHERE applicant_id = ?')->execute([$userId, $applicantId]);
                $db->prepare('UPDATE members SET user_id = ?, registered_at = ? WHERE member_id = ?')->execute([$userId, $now, $memberId]);
            }
        }
    }

    // A member's application to a committee position, ready for that committee's head to decide (BR-20).
    $position = $db->query("SELECT p.position_id, p.committee_id, c.head_user_id FROM positions p JOIN committees c ON c.committee_id = p.committee_id WHERE c.name = 'Academics and Training' AND p.title = 'Committee Member' LIMIT 1")->fetch();
    $memberUser = $db->query("SELECT user_id FROM users WHERE email = 'member@agile.test'")->fetchColumn();
    $exists = $db->query("SELECT COUNT(*) FROM membership_applications WHERE tracking_code = '9NARD7QDRQ59'")->fetchColumn();

    if ($position !== false && $position['head_user_id'] !== null && $memberUser !== false && (int) $exists === 0) {
        $requestId = seed_row($db, 'hr_requests', 'request_id', ['committee_id' => $position['committee_id'], 'position_id' => $position['position_id'], 'justification' => 'Tutors for the coming term.'], [
            'requested_by' => $position['head_user_id'],
            'slots' => 1,
            'qualifications' => 'Good standing in programming subjects.',
            'status' => 'approved',
        ]);
        $vacancyId = seed_row($db, 'vacancies', 'vacancy_id', ['request_id' => $requestId], [
            'position_id' => $position['position_id'],
            'title' => 'Academics and Training Committee Member',
            'description' => 'Help the Academics and Training Committee run review sessions and workshops this semester.',
            'qualifications' => 'Good standing in programming subjects.',
            'slots' => 1,
            'opens_at' => date('Y-m-d H:i:s', time() - 86400),
            'closes_at' => date('Y-m-d H:i:s', time() + 86400 * 30),
            'status' => 'open',
        ]);
        $applicationId = seed_row($db, 'membership_applications', 'application_id', ['tracking_code' => '9NARD7QDRQ59'], [
            'user_id' => $memberUser,
            'vacancy_id' => $vacancyId,
            'status' => 'under_review',
            'wizard_step' => 6,
            'consent_at' => $now,
            'enrollment_confirmed_at' => $now,
            'submitted_at' => $now,
        ]);

        seed_row($db, 'application_documents', 'document_id', ['application_id' => $applicationId, 'doc_type' => 'cor'], [
            'original_name' => 'cor.pdf',
            'stored_path' => 'applications/sample/' . $storedName('cor'),
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($pdf),
            'verification_status' => 'verified',
        ]);

        $history = $db->prepare('INSERT INTO application_status_history (application_id, old_status, new_status, remarks, changed_by, changed_at) VALUES (?, ?, ?, ?, NULL, ?)');
        $history->execute([$applicationId, 'draft', 'submitted', null, $now]);
        $history->execute([$applicationId, 'submitted', 'under_review', 'Documents checked.', $now]);
    }
};
