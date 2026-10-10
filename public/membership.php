<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/authorization.php';
startSecureSession();
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
$user = requirePermission('membership.view');
$message = '';
$canDecide = in_array($user['role'], ['admin','membership_head'], true);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    if (!$canDecide) { http_response_code(403); exit('Approval permission required'); }
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $next = (string)($_POST['status'] ?? '');
    if (!$id || !in_array($next, ['for_interview','approved','rejected'], true)) {
        http_response_code(422); exit('Invalid request');
    }
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT status FROM membership_applications WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $old = $stmt->fetchColumn();
        $allowed = ['pending'=>['for_interview','rejected'], 'for_interview'=>['approved','rejected']];
        if (!$old || !in_array($next, $allowed[$old] ?? [], true)) {
            $pdo->rollBack(); http_response_code(409); exit('Invalid status transition');
        }
        $stmt = $pdo->prepare('UPDATE membership_applications SET status=?, reviewed_by=? WHERE id=?');
        $stmt->execute([$next, $user['id'], $id]);
        $stmt = $pdo->prepare('INSERT INTO membership_status_events(application_id, actor_id, old_status, new_status) VALUES (?,?,?,?)');
        $stmt->execute([$id, $user['id'], $old, $next]);
        $pdo->commit();
        header('Location: /membership.php?updated=1', true, 303); exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}
$stmt = db()->query('SELECT id, application_reference, applicant_name, applicant_email, academic_year, semester, requested_position, status, created_at FROM membership_applications ORDER BY id DESC LIMIT 100');
$items = $stmt->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Membership review</title>
<style>body{font:15px system-ui;margin:2rem;background:#faf8f5}table{border-collapse:collapse;background:white;width:100%}td,th{border:1px solid #ddd;padding:.7rem;text-align:left}button{padding:.5rem}main{overflow-x:auto}</style></head>
<body><h1>AGILE OUS — Membership Review</h1><p>Authorized staff only. Maximum 100 recent applications.</p>
<?php if (isset($_GET['updated'])): ?><p role="status">Application status updated.</p><?php endif; ?>
<main><table><thead><tr><th>Reference</th><th>Applicant</th><th>Period</th><th>Position</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php foreach ($items as $item): ?><tr>
<td><?= escapeHtml((string)$item['application_reference']) ?></td>
<td><?= escapeHtml((string)$item['applicant_name']) ?><small> (<?= escapeHtml((string)$item['applicant_email']) ?>)</small></td>
<td><?= escapeHtml((string)$item['academic_year'].' '.$item['semester']) ?></td>
<td><?= escapeHtml((string)($item['requested_position'] ?? '—')) ?></td>
<td><?= escapeHtml((string)$item['status']) ?></td><td>
<?php if ($canDecide && in_array($item['status'], ['pending','for_interview'], true)): ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
<input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
<select name="status"><?php if ($item['status']==='pending'): ?><option value="for_interview">For interview</option><?php else: ?><option value="approved">Approve</option><?php endif; ?><option value="rejected">Reject</option></select>
<button type="submit">Update</button></form><?php else: ?>Read only<?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></main><p><a href="/">Return to dashboard</a></p></body></html>
