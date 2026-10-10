<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/authorization.php';
startSecureSession();
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
$user = requirePermission('membership.view');
$canManage = in_array((string)$user['role'], ['membership_head','admin'], true);
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    if (!$canManage) { http_response_code(403); exit('Permission denied'); }
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'schedule') {
        $id=filter_var($_POST['application_id'] ?? null,FILTER_VALIDATE_INT);
        $datetime=(string)($_POST['scheduled_at'] ?? '');
        $format=(string)($_POST['format'] ?? '');
        $location=trim((string)($_POST['location_note'] ?? ''));
        $dt=DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i',$datetime);
        if (!$id || !$dt || $dt->format('Y-m-d\\TH:i')!==$datetime || $dt<=new DateTimeImmutable() ||
            !in_array($format,['online','in_person'],true) || strlen($location)<3 || strlen($location)>255) {
            http_response_code(422); exit('Invalid interview details');
        }
        $pdo=db();
        try {
            $pdo->beginTransaction();
            $stmt=$pdo->prepare('SELECT status FROM membership_applications WHERE id=? FOR UPDATE');
            $stmt->execute([$id]);
            if ($stmt->fetchColumn()!=='for_interview') {
                $pdo->rollBack(); http_response_code(409); exit('Application is not ready for interview');
            }
            $stmt=$pdo->prepare('INSERT INTO membership_interviews(application_id,scheduled_at,format,location_note,scheduled_by)
               VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE scheduled_at=VALUES(scheduled_at),format=VALUES(format),
               location_note=VALUES(location_note),scheduled_by=VALUES(scheduled_by)');
            $stmt->execute([$id,$dt->format('Y-m-d H:i:s'),$format,$location,(int)$user['id']]);
            $pdo->commit();
        } catch(Throwable $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    } elseif ($action === 'evaluate') {
        $id=filter_var($_POST['application_id'] ?? null,FILTER_VALIDATE_INT);
        $scores=[];
        foreach(['communication','motivation','skills'] as $field) {
            $score=filter_var($_POST[$field] ?? null,FILTER_VALIDATE_INT);
            if($score===false || $score<1 || $score>5) { http_response_code(422); exit('Scores must be 1 to 5'); }
            $scores[]=$score;
        }
        $notes=trim((string)($_POST['notes'] ?? ''));
        if(!$id || strlen($notes)>500) {http_response_code(422);exit('Invalid evaluation');}
        $pdo=db();
        $stmt=$pdo->prepare("INSERT INTO membership_evaluations
            (application_id,evaluator_id,communication_score,motivation_score,skills_score,notes)
            SELECT id,?,?,?,?,? FROM membership_applications WHERE id=? AND status='for_interview'
            ON DUPLICATE KEY UPDATE communication_score=VALUES(communication_score),
              motivation_score=VALUES(motivation_score),skills_score=VALUES(skills_score),notes=VALUES(notes)");
        $stmt->execute([(int)$user['id'],...$scores,$notes,$id]);
        if($stmt->rowCount()===0) {http_response_code(409);exit('Application not eligible');}
    } else { http_response_code(422); exit('Unknown action'); }
    header('Location: /recruitment.php?updated=1',true,303);exit;
}
$rows=db()->query("SELECT a.id,a.application_reference,a.applicant_name,a.requested_position,
 i.scheduled_at,i.format, (SELECT COUNT(*) FROM membership_evaluations e WHERE e.application_id=a.id) evaluation_count
 FROM membership_applications a LEFT JOIN membership_interviews i ON i.application_id=a.id
 WHERE a.status='for_interview' ORDER BY a.id DESC LIMIT 100")->fetchAll();
$positions=db()->query("SELECT p.title,p.committee,p.capacity,p.enabled,
 (SELECT COUNT(*) FROM membership_applications a WHERE a.requested_position=p.title AND a.status='approved') AS filled
 FROM recruitment_positions p ORDER BY p.committee,p.title")->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Recruitment | AGILE OUS</title><style>
body{font:15px system-ui;background:#faf8f5;color:#25212a;margin:2rem}table{width:100%;border-collapse:collapse;background:white}td,th{padding:12px;border:1px solid #ddd;text-align:left}
input,select,button{padding:7px;margin:3px}section{margin-bottom:2rem;overflow-x:auto}button{background:#670c24;color:white;border:0;border-radius:5px}
</style></head><body><h1>AGILE OUS — Recruitment Management</h1>
<p><a href="/membership.php">Applications</a> · <a href="/">Dashboard</a></p>
<?php if(isset($_GET['updated'])): ?><p role="status">Changes recorded.</p><?php endif; ?>
<section><h2>Position Capacity (Informational)</h2>
<table><tr><th>Committee</th><th>Position</th><th>Capacity</th><th>Approved</th><th>Remaining</th></tr>
<?php foreach($positions as $p): ?><tr><td><?= escapeHtml($p['committee']) ?></td><td><?= escapeHtml($p['title']) ?></td>
<td><?= (int)$p['capacity'] ?></td><td><?= (int)$p['filled'] ?></td><td><?= max(0,(int)$p['capacity']-(int)$p['filled']) ?></td></tr><?php endforeach; ?></table>
<p>Capacity is display-only in this phase; approval enforcement requires a future transactional capacity check.</p></section>
<section><h2>Interviews and Evaluations</h2><table><tr><th>Applicant</th><th>Schedule</th><th>Evaluation</th></tr>
<?php foreach($rows as $r): ?><tr><td><?= escapeHtml($r['applicant_name']) ?> <small><?= escapeHtml((string)$r['application_reference']) ?></small></td>
<td><?= escapeHtml((string)($r['scheduled_at']??'Not scheduled')) ?>
<?php if($canManage): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
<input type="hidden" name="action" value="schedule"><input type="hidden" name="application_id" value="<?= (int)$r['id'] ?>">
<input type="datetime-local" name="scheduled_at" required><select name="format"><option value="online">Online</option><option value="in_person">In person</option></select>
<input name="location_note" maxlength="255" placeholder="Meeting venue or platform" required><button>Save schedule</button></form><?php endif; ?></td>
<td><?= (int)$r['evaluation_count'] ?> evaluation(s)
<?php if($canManage): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
<input type="hidden" name="action" value="evaluate"><input type="hidden" name="application_id" value="<?= (int)$r['id'] ?>">
<?php foreach(['communication','motivation','skills'] as $field): ?><label><?= escapeHtml(ucfirst($field)) ?>
<input type="number" min="1" max="5" name="<?= escapeHtml($field) ?>" required></label><?php endforeach; ?>
<input name="notes" maxlength="500" placeholder="Optional evaluation note"><button>Save evaluation</button></form><?php endif; ?></td></tr><?php endforeach; ?></table></section></body></html>
