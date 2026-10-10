<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/authorization.php';
startSecureSession();
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
$user=requirePermission('membership.view');
$canCreate=in_array($user['role'],['membership_head','admin'],true);
if($_SERVER['REQUEST_METHOD']==='POST'){
 checkCsrf();
 if(!$canCreate){http_response_code(403);exit('Not authorized');}
 $id=filter_var($_POST['application_id']??null,FILTER_VALIDATE_INT);
 $kind=(string)($_POST['kind']??'');
 if(!$id || !in_array($kind,['interview_invite','approved','rejected'],true)){http_response_code(422);exit('Invalid request');}
 $stmt=db()->prepare('SELECT applicant_name,status FROM membership_applications WHERE id=?');
 $stmt->execute([$id]);$a=$stmt->fetch();
 if(!$a){http_response_code(404);exit('Application not found');}
 $expected=['interview_invite'=>'for_interview','approved'=>'approved','rejected'=>'rejected'];
 if($a['status']!==$expected[$kind]){http_response_code(409);exit('Status does not match template');}
 $templates=[
  'interview_invite'=>['AGILE OUS — Interview Invitation','Hello %s, your application is for interview. Our team will contact you with verified scheduling details.'],
  'approved'=>['AGILE OUS — Application Update','Hello %s, your application has been approved. Our team will share onboarding instructions.'],
  'rejected'=>['AGILE OUS — Application Update','Hello %s, thank you for applying. Your application was not selected for this position.']
 ];
 [$subject,$template]=$templates[$kind];$body=sprintf($template,$a['applicant_name']);
 $stmt=db()->prepare('INSERT INTO recruitment_notification_drafts(application_id,kind,subject,body,created_by) VALUES(?,?,?,?,?)');
 $stmt->execute([$id,$kind,$subject,$body,(int)$user['id']]);
 header('Location: /notification_drafts.php?created=1',true,303);exit;
}
$apps=db()->query("SELECT id,applicant_name,status FROM membership_applications WHERE status IN ('for_interview','approved','rejected') ORDER BY id DESC LIMIT 100")->fetchAll();
$drafts=db()->query('SELECT d.id,d.kind,d.subject,d.body,d.status,a.applicant_email FROM recruitment_notification_drafts d JOIN membership_applications a ON a.id=d.application_id ORDER BY d.id DESC LIMIT 100')->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Notification drafts</title>
<style>body{font:15px system-ui;margin:2rem;background:#faf8f5}article{background:white;padding:1rem;margin:1rem 0;border:1px solid #ddd;border-radius:8px}button{background:#670c24;color:white;padding:.6rem;border:0}pre{white-space:pre-wrap}</style></head><body>
<h1>AGILE OUS — Notification Drafts</h1><p>Drafts only. No emails are sent from this page.</p>
<?php if(isset($_GET['created'])):?><p role="status">Draft created.</p><?php endif;?>
<?php if($canCreate):?><form method="post"><input type="hidden" name="csrf_token" value="<?=escapeHtml(csrfToken())?>">
<label>Applicant <select name="application_id" required><?php foreach($apps as $a):?><option value="<?=(int)$a['id']?>"><?=escapeHtml($a['applicant_name'].' — '.$a['status'])?></option><?php endforeach;?></select></label>
<label>Template <select name="kind"><option value="interview_invite">Interview invitation</option><option value="approved">Approved</option><option value="rejected">Not selected</option></select></label>
<button>Create draft</button></form><?php endif;?>
<?php foreach($drafts as $d):?><article><strong><?=escapeHtml($d['subject'])?></strong>
<p>Recipient: <?=escapeHtml($d['applicant_email'])?> · <?=escapeHtml($d['status'])?></p>
<pre><?=escapeHtml($d['body'])?></pre></article><?php endforeach;?>
<p><a href="/recruitment.php">Back to recruitment</a></p></body></html>
