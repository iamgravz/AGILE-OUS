<?php

declare(strict_types=1);

// Imports AGILE's existing members (from the AGILE-OUS DIRECTORY sheet) so nobody has to apply again. Each person gets a
// member record with their AGILE record ID as the member number (BR-27) and their current position. They then create
// their own account at /register with their PUP student number; the code goes to their PUP webmail (BR-18), and the
// account gets their position's role. Safe to run again: anyone already in the system is skipped.
//
// Usage: php jobs/import_members.php [path/to/file.csv]
// Default file: storage/imports/members.csv (storage/ is not committed, so the personal data stays local).
// Columns: record_id, committee, position, last_name, first_name, student_no, pup_webmail, contact_no, year_level, joined
//   committee is empty for Executive Committee positions; joined is YYYY-MM-DD.

require __DIR__ . '/_bootstrap.php';

use App\Core\Database;
use App\Models\ApplicantRepository;
use App\Models\MemberRepository;
use App\Services\AuditService;

$file = $argv[1] ?? dirname(__DIR__) . '/storage/imports/members.csv';

if (!is_file($file)) {
    fwrite(STDERR, "No file at {$file}.\n");
    exit(1);
}

$handle = fopen($file, 'r');
$header = array_map(static fn ($h) => strtolower(trim((string) $h)), fgetcsv($handle) ?: []);
$required = ['record_id', 'committee', 'position', 'last_name', 'first_name', 'student_no', 'pup_webmail', 'contact_no', 'year_level', 'joined'];

if (array_diff($required, $header) !== []) {
    fwrite(STDERR, 'The CSV needs these columns: ' . implode(', ', $required) . "\n");
    exit(1);
}

$db = Database::connection();
$applicants = new ApplicantRepository();
$members = new MemberRepository();
$findPosition = $db->prepare(
    'SELECT p.position_id, p.role_type FROM positions p LEFT JOIN committees c ON c.committee_id = p.committee_id
     WHERE p.title = ? AND ((? = \'\' AND p.committee_id IS NULL) OR c.name = ?) LIMIT 1'
);
$known = $db->prepare(
    'SELECT (SELECT COUNT(*) FROM users WHERE student_no = ?) + (SELECT COUNT(*) FROM applicants WHERE student_no = ?)
          + (SELECT COUNT(*) FROM members WHERE member_no = ?)'
);
$added = 0;
$skipped = 0;
$failed = 0;

while (($line = fgetcsv($handle)) !== false) {
    if (count(array_filter($line, static fn ($v) => trim((string) $v) !== '')) === 0) {
        continue;
    }

    $row = array_map('trim', array_combine($header, array_pad($line, count($header), '')));
    $name = "{$row['first_name']} {$row['last_name']}";
    $where = $row['committee'] === '' ? 'Executive Committee' : $row['committee'];

    try {
        if ($row['record_id'] === '' || $row['student_no'] === '' || filter_var($row['pup_webmail'], FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('needs an AGILE record ID, a student number and a PUP webmail');
        }

        $known->execute([$row['student_no'], $row['student_no'], $row['record_id']]);

        if ((int) $known->fetchColumn() > 0) {
            job_log(sprintf('%-40s %-30s already in the system, skipped', $where, $name));
            $skipped++;

            continue;
        }

        $findPosition->execute([$row['position'], $row['committee'], $row['committee']]);
        $position = $findPosition->fetch();

        if ($position === false) {
            throw new DomainException("no position \"{$row['position']}\" in {$where}. Run php database/setup.php first.");
        }

        if ($position['role_type'] === 'committee_head') {
            throw new DomainException('committee heads are imported by jobs/import_committee_heads.php');
        }

        Database::transaction(function () use ($applicants, $members, $row, $position): void {
            $applicantId = $applicants->insert([
                'email' => strtolower($row['pup_webmail']),
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'student_no' => $row['student_no'],
                'contact_no' => $row['contact_no'] === '' ? null : $row['contact_no'],
                'year_level' => $row['year_level'] === '' ? null : $row['year_level'],
            ]);
            $memberId = $members->insert([
                'applicant_id' => $applicantId,
                'position_id' => (int) $position['position_id'],
                'member_no' => $row['record_id'],
                'membership_type' => $position['role_type'] === 'general' ? 'general' : 'officer',
                'role_status' => 'active',
                'joined_at' => $row['joined'],
            ]);
            AuditService::log('member.imported', 'member', $memberId, ['member_no' => $row['record_id'], 'source' => 'AGILE-OUS Directory']);
        });

        job_log(sprintf('%-40s %-30s %s', $where . ', ' . $row['position'], $name, $row['record_id']));
        $added++;
    } catch (Throwable $exception) {
        job_log("FAILED {$where} ({$name}): {$exception->getMessage()}");
        $failed++;
    }
}

fclose($handle);
job_log("Done: {$added} added, {$skipped} already in the system, {$failed} failed. They register at /register with their PUP student number.");
exit($failed > 0 ? 1 : 0);
