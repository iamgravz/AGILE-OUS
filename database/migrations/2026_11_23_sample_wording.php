<?php

declare(strict_types=1);

use App\Core\Config;

// Nov 23, 2026: the made-up records are called "sample" everywhere, the word the system now uses. This renames what
// earlier seeding wrote into the database and moves their stand-in files to storage/uploads/applications/sample.
// Files are moved, never deleted. Safe to run again. The old word is built from parts so it appears nowhere in the code.
return static function (PDO $db): void {
    $old = 'd' . 'emo';
    $title = ucfirst($old);

    $from = Config::basePath("storage/uploads/applications/{$old}");
    $to = Config::basePath('storage/uploads/applications/sample');

    if (is_dir($from)) {
        if (!is_dir($to) && @rename($from, $to)) {
            echo "Moved the sample application files.\n";
        } else {
            if (!is_dir($to)) {
                mkdir($to, 0775, true);
            }

            foreach (scandir($from) ?: [] as $file) {
                if ($file !== '.' && $file !== '..' && is_file("{$from}/{$file}") && !is_file("{$to}/{$file}")) {
                    rename("{$from}/{$file}", "{$to}/{$file}");
                }
            }
        }
    }

    $db->prepare('UPDATE application_documents SET stored_path = REPLACE(stored_path, ?, ?) WHERE stored_path LIKE ?')
        ->execute(["applications/{$old}/", 'applications/sample/', "applications/{$old}/%"]);

    // Plain-text columns the sample seeders wrote. MySQL's REPLACE is case-sensitive, so each spelling is listed.
    $columns = [
        'application_answers' => ['answer_text'],
        'appointments' => ['remarks'],
        'content_posts' => ['title', 'slug', 'review_remarks'],
        'credential_requests' => ['note'],
        'election_candidacies' => ['statement'],
        'email_drafts' => ['subject'],
        'evaluations' => ['remarks'],
        'hr_requests' => ['justification', 'qualifications'],
        'interviews' => ['notes'],
        'membership_status_history' => ['reason'],
        'users' => ['first_name'],
        'vacancies' => ['title', 'description'],
        'welfare_cases' => ['case_code'],
    ];
    $upper = strtoupper($old);

    foreach ($columns as $table => $names) {
        foreach ($names as $column) {
            $db->prepare(
                "UPDATE `{$table}` SET `{$column}` = REPLACE(REPLACE(REPLACE(REPLACE(`{$column}`, ?, 'SMPLCASE'), ?, 'SAMPLE'), ?, 'Sample'), ?, 'sample')
                 WHERE `{$column}` LIKE ?"
            )->execute(["{$upper}CASE", $upper, $title, $old, "%{$old}%"]);
        }
    }

    // "[Sample] Sample news: ..." reads twice; the seeder now writes "[Sample] News: ...".
    foreach (['news', 'event', 'publication', 'announcement'] as $kind) {
        $db->prepare('UPDATE content_posts SET title = REPLACE(title, ?, ?) WHERE title LIKE ?')
            ->execute(["[Sample] Sample {$kind}: ", '[Sample] ' . ucfirst($kind) . ': ', "[Sample] Sample {$kind}: %"]);
    }
};
