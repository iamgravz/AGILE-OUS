<?php
declare(strict_types=1);
use Agile\Auth;
use Agile\Academic;
use Agile\AiGradeExtraction;
use Agile\Attachments;

if($path==='/staff/academic'&&$method==='GET'){
  $actor=Auth::requireRole(['msw_head']);
  $html='<p>Only appointed officers, committee personnel and The Source Code personnel are screened each semester. General Members are excluded. All flags require authorized human review.</p>'
    .'<h2>Create academic term</h2><form method="post" action="/staff/academic/term">'.formToken()
    .'<label>Academic term<label><input name="label" required placeholder="AY 2026-2027 First Sem"></label>'
    .'<label>Start<input type="date" name="start" required></label><label>End<input type="date" name="end" required></label>'
    .'<label>Verification deadline<input type="date" name="deadline" required></label><button>Create</button></form>';
  $terms=db()->query('SELECT * FROM academic_terms ORDER BY id DESC LIMIT 50')->fetchAll();
  foreach($terms as $t){
    $html.='<h2>'.escape($t['label']).' — '.escape($t['state']).'</h2>';
    if($t['state']==='active'){
      $html.='<form method="post" action="/staff/academic/start">'.formToken()
        .'<input type="hidden" name="term_id" value="'.(int)$t['id'].'"><button>Create/refresh verification roster</button></form>';
    }
    $q=db()->prepare('SELECT v.id,v.automated_flag,v.verified_result,m.full_name FROM academic_verifications v JOIN members m ON m.id=v.member_id WHERE v.term_id=? ORDER BY v.id DESC');
    $q->execute([(int)$t['id']]);
    foreach($q->fetchAll() as $a){
      $html.='<p><a href="/staff/academic/check?id='.(int)$a['id'].'">'.escape($a['full_name']).'</a> — '.escape($a['automated_flag']).' / '.escape($a['verified_result']).'</p>';
    }
  }
  page('Academic Verification',$html);
}
if($path==='/staff/academic/term'&&$method==='POST'){
  $actor=Auth::requireRole(['msw_head']);verifyCsrf();
  $label=trim((string)($_POST['label']??''));$start=(string)($_POST['start']??'');
  $end=(string)($_POST['end']??'');$deadline=(string)($_POST['deadline']??'');
  if(mb_strlen($label)<4||mb_strlen($label)>100||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$start)||
     !preg_match('/^\d{4}-\d{2}-\d{2}$/',$end)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$deadline)||
     $start>=$end||$deadline<$start||$deadline>$end){
      http_response_code(422);page('Invalid Term','<p>Check term dates and deadline.</p>');
  }
  $state=($start<=date('Y-m-d')&&$end>=date('Y-m-d'))?'active':'planned';
  db()->prepare('INSERT INTO academic_terms(label,starts_on,ends_on,deadline_on,state) VALUES(?,?,?,?,?)')
    ->execute([$label,$start,$end,$deadline,$state]);
  audit((int)$actor['id'],'academic.term_created','academic_term',(int)db()->lastInsertId());
  redirect('/staff/academic');
}
if($path==='/staff/academic/start'&&$method==='POST'){
  $actor=Auth::requireRole(['msw_head']);verifyCsrf();
  try{Academic::initiate((int)($_POST['term_id']??0),$actor);redirect('/staff/academic');}
  catch(DomainException $e){http_response_code(422);page('Term Error','<p class="error">'.escape($e->getMessage()).'</p>');}
}
if($path==='/staff/academic/check'&&$method==='GET'){
  $actor=Auth::requireRole(['msw_head']);$id=(int)($_GET['id']??0);
  $q=db()->prepare('SELECT v.*,m.full_name,t.label FROM academic_verifications v JOIN members m ON m.id=v.member_id JOIN academic_terms t ON t.id=v.term_id WHERE v.id=?');
  $q->execute([$id]);$v=$q->fetch();
  if(!$v){http_response_code(404);page('Not Found','<p>Verification not found.</p>');}
  $html='<p><a href="/staff/academic">← Verification cycles</a></p>'
    .'<p>'.escape($v['full_name']).' · '.escape($v['label']).'</p>'
    .'<p>Automated flag: '.escape($v['automated_flag']).' · Human outcome: '.escape($v['verified_result']).'</p>'
    .'<p>Grade screening requires a formally approved eligibility policy. Flags are never final decisions.</p>';
  if($v['verified_result']==='pending'){
    $html.='<h2>Screen verified grade entries</h2><form method="post" action="/staff/academic/screen">'.formToken()
       .'<input type="hidden" name="id" value="'.$id.'">'
       .'<label>Grades JSON (course and grade fields)<textarea name="grades" rows="6" required></textarea></label>'
       .'<button>Run approved policy rules</button></form>'
       .'<h2>Confidential document upload</h2><form enctype="multipart/form-data" method="post" action="/staff/academic/upload">'.formToken()
       .'<input type="hidden" name="id" value="'.$id.'">'
       .'<label>PDF or image, max 5 MB<input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png" required></label>'
       .'<button>Store Private Document</button></form>'
       .'<h2>Human verification</h2><form method="post" action="/staff/academic/verify">'.formToken()
       .'<input type="hidden" name="id" value="'.$id.'">'
       .'<label>Result<select name="result"><option value="eligible">Eligible</option><option value="ineligible">Ineligible</option>'
       .'<option value="needs_more_information">Needs More Information</option></select></label>'
       .'<label>Evidence and decision note<textarea name="note" required minlength="20"></textarea></label>'
       .'<button>Save Human Decision</button></form>';
  }
  if($v['verified_result']==='ineligible'){
    $html.='<h2>Authorized membership role change</h2><form method="post" action="/staff/academic/general">'.formToken()
       .'<input type="hidden" name="id" value="'.$id.'">'
       .'<label>Document authorized due-process outcome<textarea name="reason" required minlength="25"></textarea></label>'
       .'<button>Transition to General Membership</button></form>';
  }
  $q=db()->prepare("SELECT id,original_name FROM private_attachments WHERE owner_type='academic_verification' AND owner_id=? ORDER BY id DESC");$q->execute([$id]);
  foreach($q->fetchAll() as $file){
    $html.='<p>Private attachment: '.escape($file['original_name'])
      .' <a href="/staff/attachment?id='.(int)$file['id'].'">Download securely</a></p>';
    if($v['verified_result']==='pending'){
      $html.='<form method="post" action="/staff/academic/ai-extract">'.formToken()
        .'<input type="hidden" name="id" value="'.$id.'">'
        .'<input type="hidden" name="attachment_id" value="'.(int)$file['id'].'">'
        .'<button>Request AI Suggestions (privacy approval required)</button></form>';
    }
  }
  page('Academic Verification Detail',$html);
}
if($path==='/staff/academic/screen'&&$method==='POST'){
  $actor=Auth::requireRole(['msw_head']);verifyCsrf();$id=(int)($_POST['id']??0);
  try{$grades=json_decode((string)($_POST['grades']??''),true,512,JSON_THROW_ON_ERROR);
    if(!is_array($grades))throw new DomainException('Expected JSON grade array.');
    $r=Academic::screen($id,$actor,$grades);
    page('Screening Result','<p>'.escape($r['flag']).' · '.(int)$r['flagged_count'].' course(s) flagged.</p><p>Human verification is still required.</p><a href="/staff/academic/check?id='.$id.'">Return</a>');
  }catch(\JsonException|DomainException $e){http_response_code(422);page('Screening Error','<p class="error">'.escape($e->getMessage()).'</p>');}
}
if($path==='/staff/academic/verify'&&$method==='POST'){
  $actor=Auth::requireRole(['msw_head']);verifyCsrf();$id=(int)($_POST['id']??0);
  try{Academic::verify($id,$actor,(string)($_POST['result']??''),(string)($_POST['note']??''));redirect('/staff/academic/check?id='.$id);}
  catch(DomainException $e){http_response_code(422);page('Human Review Error','<p class="error">'.escape($e->getMessage()).'</p>');}
}
if($path==='/staff/academic/general'&&$method==='POST'){
  $actor=Auth::requireRole(['msw_head']);verifyCsrf();$id=(int)($_POST['id']??0);
  try{Academic::transitionToGeneral($id,$actor,(string)($_POST['reason']??''));redirect('/staff/academic/check?id='.$id);}
  catch(DomainException $e){http_response_code(422);page('Role Transition Error','<p class="error">'.escape($e->getMessage()).'</p>');}
}
if($path==='/staff/academic/upload'&&$method==='POST'){
  $actor=Auth::requireRole(['msw_head']);verifyCsrf();$id=(int)($_POST['id']??0);
  $q=db()->prepare('SELECT id FROM academic_verifications WHERE id=?');$q->execute([$id]);
  if(!$q->fetchColumn()){http_response_code(404);page('Not Found','<p>Verification not found.</p>');}
  try{Attachments::store($_FILES['attachment']??[],'academic_verification',$id,$actor);redirect('/staff/academic/check?id='.$id);}
  catch(DomainException $e){http_response_code(422);page('Upload Error','<p class="error">'.escape($e->getMessage()).'</p>');}
}
if($path==='/staff/academic/ai-extract'&&$method==='POST'){
  $actor=Auth::requireRole(['msw_head']);verifyCsrf();$id=(int)($_POST['id']??0);
  try{$rows=AiGradeExtraction::suggest((int)($_POST['attachment_id']??0),$actor);
    page('AI Suggestions — Verify Manually','<p>Review and correct these suggestions against the original document. No academic decision has been made.</p>'
       .'<pre>'.escape(json_encode($rows,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)).'</pre>'
       .'<a href="/staff/academic/check?id='.$id.'">Return to Human Review</a>');
  }catch(DomainException|RuntimeException $e){http_response_code(422);page('AI Unavailable','<p class="error">'.escape($e->getMessage()).'</p>');}
}
