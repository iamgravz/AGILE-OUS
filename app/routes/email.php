<?php
declare(strict_types=1);
use Agile\Auth;
use Agile\EmailCenter;
use Agile\Messaging;

if($path==='/staff/email'&&$method==='GET'){
  $actor=Auth::requireRole(['msw_head','msw_member']);
  $html='<p>AGILE OUS branded email drafts. All sending requires MSW Head approval. Gmail stays disabled until credentials and permission are configured.</p>'
   .'<form method="post" action="/staff/email/draft">'.formToken()
   .'<label>Recipient<input name="recipient" type="email" required></label>'
   .'<label>Template<select name="template_code">';
  foreach(EmailCenter::TEMPLATES as $code=>$label){
    $html.='<option value="'.escape($code).'">'.escape($label).'</option>';
  }
  $html.='</select></label><label>Subject<input name="subject" required maxlength="255"></label>'
   .'<label>Email text<textarea rows="8" name="message" required minlength="10"></textarea></label>'
   .'<button>Save Draft</button></form><h2>Email queue</h2>';
  if($actor['role']==='msw_head'){
    $drafts=db()->query('SELECT id,recipient,subject,template_code,status FROM email_drafts ORDER BY id DESC LIMIT 60')->fetchAll();
    $jobs=db()->query('SELECT id,recipient,subject,status,attempts,created_at FROM notification_outbox ORDER BY id DESC LIMIT 60')->fetchAll();
  }else{
    $q=db()->prepare('SELECT id,recipient,subject,template_code,status FROM email_drafts WHERE author_id=? ORDER BY id DESC LIMIT 60');
    $q->execute([(int)$actor['id']]);$drafts=$q->fetchAll();
    $jobs=[];
  }
  foreach($drafts as $d){
    $html.='<p><b>'.escape($d['subject']).'</b> → '.escape($d['recipient'])
      .' · '.escape($d['status']).'</p>';
    if($d['status']==='draft'&&$actor['role']==='msw_head'){
      $html.='<form method="post" action="/staff/email/queue">'.formToken()
        .'<input type="hidden" name="id" value="'.(int)$d['id'].'"><button>Approve and Queue Email</button></form>';
    }
  }
  if($jobs){
    $html.='<h2>Delivery attempts</h2>';
    foreach($jobs as $job){
      $html.='<p>'.escape($job['subject']).' → '.escape($job['recipient'])
        .' · '.escape($job['status']).' · '.(int)$job['attempts'].' attempt(s)</p>';
    }
  }
  page('Email Communication Center',$html);
}
if($path==='/staff/email/draft'&&$method==='POST'){
  $actor=Auth::requireRole(['msw_head','msw_member']);verifyCsrf();
  try{EmailCenter::draft($actor,$_POST);redirect('/staff/email');}
  catch(DomainException $e){http_response_code(422);page('Email Draft Error','<p class="error">'.escape($e->getMessage()).'</p>');}
}
if($path==='/staff/email/queue'&&$method==='POST'){
  $actor=Auth::requireRole(['msw_head']);verifyCsrf();
  try{EmailCenter::queueApproved((int)($_POST['id']??0),$actor);redirect('/staff/email');}
  catch(DomainException $e){http_response_code(422);page('Email Queue Error','<p class="error">'.escape($e->getMessage()).'</p>');}
}
if($path==='/staff/notifications'&&$method==='GET'){
  $actor=Auth::requireRole(['msw_head','msw_member','committee_head','deputy_head','executive_officer','source_editor','member']);
  $messages=Messaging::staffMessages((int)$actor['id']);
  $html='<h2>Notifications</h2>';
  foreach($messages as $m){
    $html.='<p><b>'.escape($m['title']).'</b> · '.escape($m['created_at'])
      .'<br>'.escape($m['body']).'</p>';
    if(!$m['read_at']){
      $html.='<form method="post" action="/staff/notifications/read">'.formToken()
        .'<input type="hidden" name="id" value="'.(int)$m['id'].'"><button>Mark Read</button></form>';
    }
  }
  page('My Notifications',$html);
}
if($path==='/staff/notifications/read'&&$method==='POST'){
  $actor=Auth::requireRole(['msw_head','msw_member','committee_head','deputy_head','executive_officer','source_editor','member']);
  verifyCsrf();Messaging::markRead((int)($_POST['id']??0),(int)$actor['id']);
  redirect('/staff/notifications');
}
