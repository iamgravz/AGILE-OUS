<?php

declare(strict_types=1);

use App\Services\EncryptionService;

// The made-up records read like real ones for the presentation: no "[Sample]" or "Sample:" in the text, the six
// placeholder posts rewritten (seeds/content_post_list.php), and post links without "sample-". The records stay
// findable for removal before going live: they belong to @agile.test accounts (SampleDataRemover). Safe to run again.
return static function (PDO $db): void {
    // Posts: new slug, title and text from the shared list; dates are left as they are.
    $posts = (require dirname(__DIR__) . '/seeds/content_post_list.php')(static fn (int $days, string $time): string => '');
    $update = $db->prepare(
        "UPDATE content_posts SET slug = ?, title = ?, excerpt = ?, body = ?, venue_or_link = IF(type = 'event', ?, venue_or_link)
         WHERE slug = ? AND NOT EXISTS (SELECT 1 FROM (SELECT slug FROM content_posts) taken WHERE taken.slug = ?)"
    );

    foreach ($posts as $post) {
        $update->execute([$post['slug'], $post['title'], $post['excerpt'], $post['body'], $post['venue'], $post['old'], $post['slug']]);
    }

    $db->exec("UPDATE content_posts SET review_remarks = NULL WHERE review_remarks = 'Sample post, published by the seeder.'");

    // Plain-text columns written by the sample seeders: drop the markers.
    $columns = [
        'hr_requests' => ['justification', 'qualifications'],
        'vacancies' => ['title', 'description'],
        'interviews' => ['notes'],
        'appointments' => ['remarks'],
        'election_candidacies' => ['statement'],
        'service_requests' => ['subject'],
        'credential_requests' => ['note'],
        'email_drafts' => ['subject'],
        'membership_status_history' => ['reason'],
        'applicant_assessments' => ['remarks', 'ratings_json'],
    ];

    foreach ($columns as $table => $names) {
        foreach ($names as $column) {
            $db->exec("UPDATE `{$table}` SET `{$column}` = REPLACE(REPLACE(`{$column}`, '[Sample] ', ''), 'Sample: ', '')
                       WHERE `{$column}` LIKE '%[Sample] %' OR `{$column}` LIKE 'Sample: %'");
        }
    }

    $db->exec("UPDATE applicant_assessments SET remarks = 'Ready for the committee. Best placed where there is event work.' WHERE remarks = 'Assessment for testing.'");
    $db->exec("UPDATE vacancies SET description = 'Help the Academics and Training Committee run review sessions and workshops this semester.' WHERE description = 'A sample position for testing.'");
    $db->exec("UPDATE users SET first_name = 'Guest', last_name = 'Account' WHERE email = 'guest@agile.test' AND first_name = 'Sample'");
    $db->exec("UPDATE application_answers SET answer_text = 'I want to help run AGILE events and learn from other members.' WHERE answer_text = 'Sample answer for the browser check.'");

    // Welfare subjects and closing remarks are encrypted, so they are read, changed and written back one by one.
    $cases = $db->query("SELECT case_id, subject, closing_remarks FROM welfare_cases")->fetchAll(PDO::FETCH_ASSOC);

    if ($cases === []) {
        return;
    }

    $encryption = new EncryptionService();
    $save = $db->prepare('UPDATE welfare_cases SET subject = ?, closing_remarks = ? WHERE case_id = ?');

    foreach ($cases as $case) {
        $subject = $encryption->decrypt((string) $case['subject']);
        $remarks = $case['closing_remarks'] === null ? null : $encryption->decrypt((string) $case['closing_remarks']);
        $newSubject = str_starts_with($subject, '[Sample] ') ? substr($subject, 9) : $subject;
        $newRemarks = $remarks !== null && str_starts_with($remarks, 'Sample closing remarks: the student') ? 'The student' . substr($remarks, strlen('Sample closing remarks: the student')) : $remarks;

        if ($newSubject !== $subject || $newRemarks !== $remarks) {
            $save->execute([$encryption->encrypt($newSubject), $newRemarks === null ? null : $encryption->encrypt($newRemarks), (int) $case['case_id']]);
        }
    }
};
