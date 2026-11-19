<?php

declare(strict_types=1);

// Imports AGILE's real committee heads from a CSV (taken from the AGILE-OUS DIRECTORY sheet) so they can log in and
// decide their committee's applications (BR-20). Safe to run again: existing accounts and members are reused.
//
// Usage: php jobs/import_committee_heads.php [path/to/file.csv]
// Default file: storage/imports/committee_heads.csv (storage/ is not committed, so the personal data stays local).
// Columns: record_id, committee, last_name, first_name, student_no, pup_webmail, joined (YYYY-MM-DD)
//
// For each row: an account is created if the webmail has none (role from the position: msw_head for the MSW Committee
// Head, committee_head otherwise), a member record is created with their AGILE record ID as the member number
// (BR-27: existing members keep their IDs), and they are made Committee Head of their committee. Whoever held that
// position before (for example a demo account) becomes a General Member. New accounts get a set-password email, which
// stays in the queue until jobs/send_email_queue.php sends it; the links are also printed here for local testing.

require __DIR__ . '/_bootstrap.php';

use App\Core\Database;
use App\Models\MemberRepository;
use App\Models\UserRepository;
use App\Services\AuthService;
use App\Services\MembershipService;

const IMPORT_REASON = 'Committee Head from the AGILE-OUS Directory (import)';

$file = $argv[1] ?? dirname(__DIR__) . '/storage/imports/committee_heads.csv';

if (!is_file($file)) {
    fwrite(STDERR, "No file at {$file}.\n");
    exit(1);
}

$handle = fopen($file, 'r');
$header = array_map(static fn ($h) => strtolower(trim((string) $h)), fgetcsv($handle) ?: []);
$required = ['record_id', 'committee', 'last_name', 'first_name', 'student_no', 'pup_webmail', 'joined'];

if (array_diff($required, $header) !== []) {
    fwrite(STDERR, 'The CSV needs these columns: ' . implode(', ', $required) . "\n");
    exit(1);
}

$rows = [];

while (($line = fgetcsv($handle)) !== false) {
    if (count(array_filter($line, static fn ($v) => trim((string) $v) !== '')) === 0) {
        continue;
    }

    $rows[] = array_map('trim', array_combine($header, array_pad($line, count($header), '')));
}

fclose($handle);

// The MSW Committee Head goes first: they become the actor for the others, and a demo MSW head can only step down
// once a real one exists (MembershipService refuses to remove the last one).
$msw = (string) config('role_mapping.msw_committee');
usort($rows, static fn (array $a, array $b): int => (strcasecmp($b['committee'], $msw) === 0) <=> (strcasecmp($a['committee'], $msw) === 0));

$db = Database::connection();
$users = new UserRepository();
$members = new MemberRepository();
$auth = new AuthService();
$membership = new MembershipService();
$actorId = null;
$done = 0;
$failed = 0;

$headPosition = $db->prepare(
    "SELECT p.position_id FROM positions p JOIN committees c ON c.committee_id = p.committee_id
     WHERE c.name = ? AND p.role_type = 'committee_head' LIMIT 1"
);
$otherHolders = $db->prepare('SELECT member_id FROM members WHERE position_id = ? AND member_id <> ?');
$setupLink = $db->prepare(
    "SELECT JSON_UNQUOTE(JSON_EXTRACT(n.payload_json, '$.link')) FROM notifications n
     JOIN email_templates t ON t.template_id = n.template_id
     WHERE n.user_id = ? AND t.code = 'ACCOUNT_SETUP' ORDER BY n.notification_id DESC LIMIT 1"
);

foreach ($rows as $row) {
    $name = "{$row['first_name']} {$row['last_name']}";

    try {
        $headPosition->execute([$row['committee']]);
        $positionId = $headPosition->fetchColumn();

        if ($positionId === false) {
            throw new DomainException("No Committee Head position for \"{$row['committee']}\". Run php database/setup.php first.");
        }

        $isMsw = strcasecmp($row['committee'], $msw) === 0;
        $email = strtolower($row['pup_webmail']);
        $user = $users->findByEmail($email);
        $created = false;

        if ($user === null) {
            $userId = $auth->createAccount($email, $row['first_name'], $row['last_name'], $isMsw ? 'msw_head' : 'committee_head', $row['student_no']);
            $created = true;
        } else {
            $userId = (int) $user['user_id'];
        }

        $actorId ??= $isMsw ? $userId : null;

        if ($actorId === null) {
            throw new DomainException('The MSW Committee Head must be in the file, so the others have someone to be appointed by.');
        }

        Database::transaction(function () use ($db, $members, $membership, $userId, $row, $positionId, $otherHolders, $actorId): void {
            $member = $members->findBy(['user_id' => $userId])[0] ?? null;

            if ($member === null) {
                $memberId = $members->insert([
                    'user_id' => $userId,
                    'member_no' => $row['record_id'],
                    'membership_type' => 'officer',
                    'joined_at' => $row['joined'],
                    'registered_at' => date('Y-m-d H:i:s'),
                ]);
            } else {
                $memberId = (int) $member['member_id'];
            }

            if ((int) ($members->find($memberId)['position_id'] ?? 0) !== (int) $positionId) {
                $membership->changeRole($memberId, (int) $positionId, IMPORT_REASON, $actorId);
            }

            // Whoever held this head position before steps down to General Member.
            $otherHolders->execute([$positionId, $memberId]);

            foreach ($otherHolders->fetchAll(PDO::FETCH_COLUMN) as $previous) {
                $membership->changeRole((int) $previous, null, 'Replaced by the Committee Head from the AGILE-OUS Directory', $actorId);
            }
        });

        $line = sprintf('%-48s %-40s %s', $row['committee'], $name, $created ? 'account created' : 'account already existed');

        if ($created) {
            $setupLink->execute([$userId]);
            $link = $setupLink->fetchColumn();
            $line .= $link ? "\n    set-password link: " . url((string) $link) : '';
        }

        job_log($line);
        $done++;
    } catch (Throwable $exception) {
        job_log("FAILED {$row['committee']} ({$name}): {$exception->getMessage()}");
        $failed++;
    }
}

job_log("Done: {$done} committee heads, {$failed} failed. The set-password emails wait in the queue until jobs/send_email_queue.php runs.");
exit($failed > 0 ? 1 : 0);
