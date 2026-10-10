<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/authorization.php';
require dirname(__DIR__) . '/src/welfare_security.php';
startSecureSession();
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
$user=requirePermission('welfare.view');
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);
if(!$id){http_response_code(404);exit('Case not found');}
$stmt=db()->prepare('SELECT id,case_reference,category,priority,status,created_by,assigned_to,encrypted_narrative FROM welfare_cases WHERE id=?');
$stmt->execute([$id]);$case=$stmt->fetch();
if(!$case || !canReadWelfareCase($user,$case)){http_response_code(404);exit('Case not found');}
$stmt=db()->prepare("INSERT INTO audit_events(actor_id,action,resource_type,resource_id) VALUES (?,'welfare_case_read','welfare_case',?)");
$stmt->execute([(int)$user['id'],$id]);
$narrative=decryptWelfare((string)$case['encrypted_narrative']);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Confidential welfare record</title><style>body{font:16px system-ui;margin:2rem;background:#faf8f5}main{max-width:760px;background:white;padding:2rem;border:1px solid #ddd}pre{white-space:pre-wrap;overflow-wrap:anywhere}</style></head><body><main>
<h1><?=escapeHtml($case['case_reference'])?></h1>
<p><?=escapeHtml($case['category'])?> · <?=escapeHtml($case['status'])?></p>
<h2>Confidential description</h2><pre><?=escapeHtml($narrative)?></pre>
<p><a href="/welfare.php">Back to authorized cases</a></p></main></body></html>
