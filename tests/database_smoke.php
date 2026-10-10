<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

function assertDb(bool $ok, string $message): void {
    if (!$ok) { fwrite(STDERR, "FAILED: $message\n"); exit(1); }
}
$pdo = db();
$tables = ['users','membership_applications','welfare_cases','audit_events',
    'login_attempts','recruitment_positions','membership_interviews',
    'membership_evaluations','membership_status_events',
    'recruitment_notification_drafts','notification_outbox'];
foreach ($tables as $table) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute([$table]);
    assertDb((int)$stmt->fetchColumn() === 1, "Missing table $table");
}
$columns = [
 'membership_applications' => ['recruitment_position_id','application_reference','consent_at'],
 'recruitment_positions' => ['academic_year','semester','capacity','enabled']
];
foreach($columns as $table=>$fields){
 foreach($fields as $field){
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
    $stmt->execute([$table,$field]);
    assertDb((int)$stmt->fetchColumn()===1,"Missing $table.$field");
 }
}
echo "Schema smoke tests passed\n";
