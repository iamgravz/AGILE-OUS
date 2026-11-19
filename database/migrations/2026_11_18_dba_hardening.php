<?php

declare(strict_types=1);

use App\Services\EncryptionService;

// Database audit fixes (docs/database-audit.md): H3, M2, M3, L1–L4, L8.
// Brings an existing database to the same design as a fresh install from schema.sql.
// Safe to run again: every change checks information_schema first. Rows that break a new rule are reported and
// that rule is skipped, never deleted silently, so a DBA can fix the data and run setup.php again.
return static function (PDO $db): void {
    $one = static function (string $sql, array $params = []) use ($db): mixed {
        $statement = $db->prepare($sql);
        $statement->execute($params);

        return $statement->fetchColumn();
    };
    $hasColumn = static fn (string $table, string $column): bool => (int) $one(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$table, $column]
    ) > 0;
    $hasIndex = static fn (string $table, string $index): bool => (int) $one(
        'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
        [$table, $index]
    ) > 0;
    $hasCheck = static fn (string $name): bool => (int) $one(
        'SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ?',
        [$name]
    ) > 0;
    $addCheck = static function (string $table, string $name, string $rule, string $violations) use ($db, $hasCheck): void {
        if ($hasCheck($name)) {
            return;
        }

        $bad = (int) $db->query("SELECT COUNT(*) FROM {$table} WHERE NOT ({$violations})")->fetchColumn();

        if ($bad > 0) {
            echo "WARNING: {$bad} rows in {$table} break {$name}. Fix them, then run setup.php again.\n";

            return;
        }

        $db->exec("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$rule})");
        echo "Added {$name}.\n";
    };
    $dropIndex = static function (string $table, string $index) use ($db, $hasIndex): void {
        if ($hasIndex($table, $index)) {
            $db->exec("ALTER TABLE {$table} DROP INDEX {$index}");
        }
    };

    /* ---------- H3: AGILE's real committees (update R6). The seeder adds what is still missing. ---------- */

    $renames = [
        'Academic' => 'Academics and Training',
        'Events' => 'Events and External Relations',
        'Communications' => 'Creatives, Social Media and Communication',
    ];

    $descriptions = [
        'Academics and Training' => 'Plans tutorials, review sessions, workshops and other academic support.',
        'Events and External Relations' => 'Organizes AGILE events and works with partner organizations.',
        'Creatives, Social Media and Communication' => 'Handles publicity materials, social media and announcements.',
    ];

    foreach ($renames as $old => $new) {
        if ((int) $one('SELECT COUNT(*) FROM committees WHERE name = ?', [$new]) === 0) {
            $db->prepare('UPDATE committees SET name = ?, description = ? WHERE name = ?')->execute([$new, $descriptions[$new], $old]);
        }
    }

    $db->exec("UPDATE positions p JOIN committees c ON c.committee_id = p.committee_id
               SET p.title = 'Deputy Committee Head'
               WHERE p.title = 'Deputy Head' AND c.name <> 'The Source Code'");

    // The Source Code had the sample committee positions. Unused ones go; used ones become publication positions.
    $sourceCode = $one("SELECT committee_id FROM committees WHERE name = 'The Source Code'");

    if ($sourceCode !== false) {
        $references = $db->query(
            "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = 'positions' AND REFERENCED_COLUMN_NAME = 'position_id'"
        )->fetchAll(PDO::FETCH_NUM);
        $inUse = static function (int $positionId) use ($references, $one): bool {
            foreach ($references as [$table, $column]) {
                if ((int) $one("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?", [$positionId]) > 0) {
                    return true;
                }
            }

            return false;
        };

        foreach (['Committee Head' => 'Managing Editor', 'Deputy Head' => 'News Editor', 'Committee Member' => 'Writer'] as $old => $new) {
            $positionId = $one('SELECT position_id FROM positions WHERE committee_id = ? AND title = ?', [$sourceCode, $old]);

            if ($positionId === false) {
                continue;
            }

            if (!$inUse((int) $positionId)) {
                $db->prepare('DELETE FROM positions WHERE position_id = ?')->execute([$positionId]);
            } elseif ((int) $one('SELECT COUNT(*) FROM positions WHERE committee_id = ? AND title = ?', [$sourceCode, $new]) === 0) {
                $db->prepare("UPDATE positions SET title = ?, role_type = 'source_code' WHERE position_id = ?")->execute([$new, $positionId]);
                echo "The Source Code: \"{$old}\" is in use, so it was renamed \"{$new}\".\n";
            } else {
                echo "WARNING: The Source Code position \"{$old}\" is in use. Move its holders to a publication position.\n";
            }
        }

        // The MSW Committee Head decides for The Source Code (R6), so it has no committee head.
        $db->prepare('UPDATE committees SET head_user_id = NULL WHERE committee_id = ?')->execute([$sourceCode]);
    }

    // One title per committee. Executive positions have no committee, so a generated key stands in for NULL.
    if (!$hasColumn('positions', 'committee_key')) {
        $db->exec('ALTER TABLE positions ADD COLUMN committee_key INT UNSIGNED AS (IFNULL(committee_id, 0)) STORED AFTER committee_id');
    }

    if (!$hasIndex('positions', 'uq_positions_committee_title')) {
        $duplicates = (int) $db->query('SELECT COUNT(*) FROM (SELECT 1 FROM positions GROUP BY committee_key, title HAVING COUNT(*) > 1) d')->fetchColumn();

        if ($duplicates > 0) {
            echo "WARNING: {$duplicates} position titles repeat within a committee. Merge them, then run setup.php again.\n";
        } else {
            $db->exec('ALTER TABLE positions ADD UNIQUE KEY uq_positions_committee_title (committee_key, title)');
        }
    }

    /* ---------- M3: a guest's identity on a welfare case is encrypted like the rest of the case. ---------- */

    if (!$hasColumn('welfare_cases', 'guest_email_hash')) {
        $dropIndex('welfare_cases', 'idx_welfare_cases_guest_email');
        $db->exec('ALTER TABLE welfare_cases
            MODIFY guest_name TEXT NULL,
            MODIFY guest_email TEXT NULL,
            ADD COLUMN guest_email_hash CHAR(64) NULL AFTER guest_email,
            MODIFY guest_student_no TEXT NULL,
            ADD KEY idx_welfare_cases_guest_email_hash (guest_email_hash)');
    }

    $plain = $db->query('SELECT case_id, guest_name, guest_email, guest_student_no FROM welfare_cases WHERE guest_email IS NOT NULL AND guest_email_hash IS NULL')->fetchAll(PDO::FETCH_ASSOC);

    if ($plain !== []) {
        $encryption = new EncryptionService();
        $update = $db->prepare('UPDATE welfare_cases SET guest_name = ?, guest_email = ?, guest_email_hash = ?, guest_student_no = ? WHERE case_id = ?');

        foreach ($plain as $case) {
            $update->execute([
                $case['guest_name'] === null ? null : $encryption->encrypt($case['guest_name']),
                $encryption->encrypt($case['guest_email']),
                $encryption->blindIndex($case['guest_email']),
                $case['guest_student_no'] === null ? null : $encryption->encrypt($case['guest_student_no']),
                $case['case_id'],
            ]);
        }

        echo 'Encrypted the guest details on ' . count($plain) . " welfare cases.\n";
    }

    /* ---------- M2: business rules the database enforces itself. ---------- */

    $addCheck('welfare_cases', 'chk_welfare_cases_owner', 'user_id IS NOT NULL OR guest_email IS NOT NULL', 'user_id IS NOT NULL OR guest_email IS NOT NULL');
    $addCheck('welfare_cases', 'chk_welfare_cases_guest_hash', '(guest_email IS NULL) = (guest_email_hash IS NULL)', '(guest_email IS NULL) = (guest_email_hash IS NULL)');
    $addCheck('vacancies', 'chk_vacancies_slots_filled', 'slots_filled <= slots', 'slots_filled <= slots');
    $addCheck('vacancies', 'chk_vacancies_dates', 'closes_at > opens_at', 'closes_at > opens_at');
    $addCheck('evaluations', 'chk_evaluations_score', 'score BETWEEN 0 AND 100', 'score BETWEEN 0 AND 100');
    $addCheck(
        'academic_terms',
        'chk_academic_terms_year',
        "academic_year REGEXP '^[0-9]{4}-[0-9]{4}$' AND CAST(RIGHT(academic_year, 4) AS UNSIGNED) = CAST(LEFT(academic_year, 4) AS UNSIGNED) + 1",
        "academic_year REGEXP '^[0-9]{4}-[0-9]{4}$' AND CAST(RIGHT(academic_year, 4) AS UNSIGNED) = CAST(LEFT(academic_year, 4) AS UNSIGNED) + 1"
    );

    if (!$hasIndex('academic_terms', 'uq_academic_terms_year_semester')) {
        $duplicates = (int) $db->query('SELECT COUNT(*) FROM (SELECT 1 FROM academic_terms GROUP BY academic_year, semester HAVING COUNT(*) > 1) d')->fetchColumn();

        if ($duplicates > 0) {
            echo "WARNING: {$duplicates} academic terms are entered twice. Merge them, then run setup.php again.\n";
        } else {
            $db->exec('ALTER TABLE academic_terms ADD UNIQUE KEY uq_academic_terms_year_semester (academic_year, semester)');
        }
    }

    // Only one current term: the newest one that is marked current stays current.
    if (!$hasColumn('academic_terms', 'current_flag')) {
        $db->exec('UPDATE academic_terms SET is_current = 0 WHERE is_current = 1 AND term_id <> (SELECT term_id FROM (SELECT MAX(term_id) AS term_id FROM academic_terms WHERE is_current = 1) t)');
        $db->exec('ALTER TABLE academic_terms
            ADD COLUMN current_flag TINYINT UNSIGNED AS (IF(is_current = 1, 1, NULL)) STORED AFTER is_current,
            ADD UNIQUE KEY uq_academic_terms_current_flag (current_flag)');
    }

    // BR-02: an applicant is one person, known by email and by PUP student number.
    $db->exec("UPDATE applicants SET student_no = NULL WHERE student_no = ''");

    if (!$hasIndex('applicants', 'uq_applicants_student_no')) {
        $duplicates = (int) $db->query('SELECT COUNT(*) FROM (SELECT 1 FROM applicants WHERE student_no IS NOT NULL GROUP BY student_no HAVING COUNT(*) > 1) d')->fetchColumn();

        if ($duplicates > 0) {
            echo "WARNING: {$duplicates} student numbers belong to more than one applicant. Merge them, then run setup.php again.\n";
        } else {
            $db->exec('ALTER TABLE applicants ADD UNIQUE KEY uq_applicants_student_no (student_no)');
            $dropIndex('applicants', 'idx_applicants_student_no');
        }
    }

    /* ---------- L2: composite indexes for the queries that run most (added before L1/L3 drop their old ones). ---------- */

    $composites = [
        ['notifications', 'idx_notifications_user_bell', '(user_id, channel, read_at)', 'idx_notifications_user_id'],
        ['notifications', 'idx_notifications_queue', '(channel, status, scheduled_at)', null],
        ['welfare_cases', 'idx_welfare_cases_status_priority', '(status, priority)', 'idx_welfare_cases_status'],
        ['vacancies', 'idx_vacancies_status_closes_at', '(status, closes_at)', 'idx_vacancies_status'],
    ];

    foreach ($composites as [$table, $index, $columns, $replaces]) {
        if (!$hasIndex($table, $index)) {
            $db->exec("ALTER TABLE {$table} ADD KEY {$index} {$columns}");
        }

        if ($replaces !== null) {
            $dropIndex($table, $replaces);
        }
    }

    /* ---------- L1 and L3: indexes that another index already covers, or that no query uses. ---------- */

    $dropIndex('evaluations', 'idx_evaluations_application_id');
    $dropIndex('eligibility_screenings', 'idx_eligibility_screenings_term_id');

    // created_at stays indexed only where lists are sorted or filtered by date.
    $keepCreatedAt = ['audit_logs', 'notifications', 'membership_applications', 'welfare_cases', 'content_posts'];
    $createdAtIndexes = $db->query(
        "SELECT TABLE_NAME, INDEX_NAME FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'created_at' AND SEQ_IN_INDEX = 1 AND INDEX_NAME LIKE 'idx\\_%\\_created\\_at'"
    )->fetchAll(PDO::FETCH_NUM);

    foreach ($createdAtIndexes as [$table, $index]) {
        if (!in_array($table, $keepCreatedAt, true)) {
            $dropIndex($table, $index);
        }
    }

    /* ---------- L4: replaced by eligibility_documents in step 20. ---------- */

    if ($hasColumn('eligibility_screenings', 'document_path')) {
        $stored = (int) $db->query('SELECT COUNT(*) FROM eligibility_screenings WHERE document_path IS NOT NULL')->fetchColumn();

        if ($stored > 0) {
            echo "WARNING: {$stored} screenings still have a document_path. Move them to eligibility_documents, then run setup.php again.\n";
        } else {
            $db->exec('ALTER TABLE eligibility_screenings DROP COLUMN document_path');
        }
    }

    /* ---------- L8: what the guest role is for. ---------- */

    $db->exec("UPDATE roles SET role_name = 'Guest / Applicant (no account)',
        description = 'The public in the role matrix: no account and no permissions. Also holds disabled accounts retired by migration 003, kept for the audit trail.'
        WHERE slug = 'guest'");
};
