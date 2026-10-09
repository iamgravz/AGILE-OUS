<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

use Agile\Auth;
use Agile\Membership;

function check(bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
    echo "PASS: $message\n";
}

$pdo = db();
check($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql', 'Connected to real MySQL');

$q = $pdo->prepare('SELECT is_approved, JSON_UNQUOTE(JSON_EXTRACT(criteria_json, \'$.grade_history_lookback\')) AS lookback
    FROM eligibility_policies WHERE policy_version = ? LIMIT 1');
$q->execute([\Agile\DraftBylawsPolicy::VERSION]);
$draft = $q->fetch();
check((bool)$draft && (int)$draft['is_approved'] === 0,
    'Source-derived draft policy is seeded but remains explicitly unapproved');
check($draft['lookback'] === 'during_entire_stay_in_institution',
    'Provisional Article VI grade history scope is not reduced to one semester');

foreach (['users','membership_applications','audit_logs','login_attempts','schema_migrations'] as $table) {
    $name = $pdo->quote($table);
    check((int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = $name")->fetchColumn() === 1,
        "Migrated table: $table");
}

$suffix = bin2hex(random_bytes(5));
$student = 'T-' . $suffix;
$email = 'staff-' . $suffix . '@example.invalid';
$reference = null;
$staffId = null;
try {
    $payload = [
        'full_name' => 'Synthetic AGILE Applicant',
        'email' => 'student-' . $suffix . '@example.invalid',
        'student_number' => $student,
        'desired_role' => 'Committee Member',
        'motivation' => 'Synthetic data used only to validate the MySQL write workflow.',
        'privacy_consent' => 'yes',
        'answers' => [
            'preferred_committee' => 'Membership and Student Welfare',
            'relevant_skills' => 'Synthetic documentation and coordination experience',
        ],
    ];
    $reference = Membership::submit($payload);
    check((bool)preg_match('/^AG-[A-F0-9]{14}$/', $reference), 'Created unpredictable application reference');

    $q = $pdo->prepare('SELECT id, full_name, status, student_number FROM membership_applications WHERE reference_code = ?');
    $q->execute([$reference]);
    $row = $q->fetch();
    check((bool)$row && $row['status'] === 'submitted' && $row['student_number'] === $student,
        'Application saved and retrievable from MySQL');
    $appId = (int)$row['id'];
    $q = $pdo->prepare('SELECT question_key, answer_text FROM application_answers WHERE application_id = ?');
    $q->execute([$appId]);
    check(count($q->fetchAll()) === 2, 'Position-specific application answers stored in MySQL');
    $q = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE entity_type = 'membership_application' AND entity_id = ? AND action = 'application.submitted'");
    $q->execute([$appId]);
    check((int)$q->fetchColumn() === 1, 'Application audit entry persisted');

    $password = 'SyntheticOnlyPassword!2026';
    $q = $pdo->prepare("INSERT INTO users(email,display_name,password_hash,role) VALUES (?,?,?,'msw_head')");
    $q->execute([$email,'Synthetic MSW Staff',password_hash($password,PASSWORD_DEFAULT)]);
    $staffId = (int)$pdo->lastInsertId();

    check(Auth::login($email,'incorrect-password') === false, 'Invalid credentials rejected');
    check(Auth::login($email,$password) === true, 'Valid staff credentials accepted');
    check(Auth::user()['role'] === 'msw_head', 'Authenticated staff session resolved from MySQL');
    Auth::logout();
    check(Auth::user() === null, 'Logout revokes authenticated session');

    echo "MySQL integration checks passed. Only synthetic records were used.\n";
} finally {
    if ($reference !== null) {
        $q = $pdo->prepare('SELECT id FROM membership_applications WHERE reference_code = ?');
        $q->execute([$reference]); $id = $q->fetchColumn();
        if ($id) {
            $pdo->prepare("DELETE FROM audit_logs WHERE entity_type = 'membership_application' AND entity_id = ?")->execute([$id]);
            $pdo->prepare('DELETE FROM application_verification_history WHERE application_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM application_status_history WHERE application_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM application_answers WHERE application_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM membership_applications WHERE id = ?')->execute([$id]);
        }
    }
    if ($staffId !== null) {
        $pdo->prepare('DELETE FROM audit_logs WHERE actor_user_id = ?')->execute([$staffId]);
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$staffId]);
    }
}
