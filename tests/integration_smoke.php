<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

/**
 * Uses only synthetic fixtures and rolls back everything.
 * Run against a disposable CI database, NEVER a production database.
 */
if (getenv('APP_ENV') !== 'testing') {
    fwrite(STDERR, "Integration test requires APP_ENV=testing\n");
    exit(2);
}
$pdo = db();
function verify(bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
}
$pdo->beginTransaction();
try {
    $email = 'fixture-' . bin2hex(random_bytes(8)) . '@example.test';
    $year = '2098-2099';
    $stmt = $pdo->prepare("INSERT INTO recruitment_positions
      (title,committee,academic_year,semester,capacity,enabled) VALUES ('QA Fixture','QA Team',?,'1st',1,1)");
    $stmt->execute([$year]);
    $positionId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO membership_applications
      (applicant_name,applicant_email,academic_year,semester,recruitment_position_id,application_reference)
      VALUES ('Test Applicant',? ,?,'1st',?,?)");
    $stmt->execute([$email,$year,$positionId,strtoupper(bin2hex(random_bytes(12)))]);
    $applicationId = (int)$pdo->lastInsertId();

    $duplicateBlocked = false;
    try {
        $stmt->execute([$email,$year,$positionId,strtoupper(bin2hex(random_bytes(12)))]);
    } catch (PDOException $e) {
        $duplicateBlocked = $e->getCode() === '23000';
    }
    verify($duplicateBlocked, 'Duplicate applicant/term was not blocked');

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM membership_applications
        WHERE recruitment_position_id=? AND status='approved'");
    $stmt->execute([$positionId]);
    verify((int)$stmt->fetchColumn()===0, 'Unexpected initial occupancy');

    $stmt = $pdo->prepare("UPDATE membership_applications SET status='approved' WHERE id=?");
    $stmt->execute([$applicationId]);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM membership_applications
        WHERE recruitment_position_id=? AND status='approved'");
    $stmt->execute([$positionId]);
    verify((int)$stmt->fetchColumn()===1, 'Approved occupancy is incorrect');

    $stmt = $pdo->prepare('SELECT id FROM recruitment_positions WHERE id=? FOR UPDATE');
    $stmt->execute([$positionId]);
    verify((int)$stmt->fetchColumn()===$positionId,'Position locking unavailable');

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables
        WHERE table_schema=DATABASE() AND table_name='notification_outbox'");
    $stmt->execute();
    verify((int)$stmt->fetchColumn()===1,'Notification outbox missing');

    echo "Synthetic database integration smoke checks passed\n";
} finally {
    $pdo->rollBack();
}
