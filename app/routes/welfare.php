<?php
declare(strict_types=1);
use Agile\Auth;
use Agile\Welfare;

if ($path==='/welfare'&&$method==='GET'){
    $html='<p>Confidential AGILE OUS student welfare concern intake. This is not an emergency service. For urgent threats, contact the appropriate emergency or institutional support service directly.</p>'
      .'<p><strong>Development notice:</strong> Submit synthetic information only until the PUP/organization privacy notice and deployment are approved.</p>'
      .'<form method="post" action="/welfare">'.formToken()
      .'<label>Your full name<input name="reporter_name" required maxlength="150"></label>'
      .'<label>Email<input type="email" name="reporter_email" required maxlength="190"></label>'
      .'<label>Concern category<select name="category">';
    foreach(Welfare::CATEGORIES as $category)$html.='<option>'.escape($category).'</option>';
    $html.='</select></label><label>Brief concern summary<input name="summary" required maxlength="255"></label>'
      .'<label>Details<textarea name="details" required minlength="20" maxlength="10000"></textarea></label>'
      .'<label><input type="checkbox" name="privacy_consent" value="yes" required style="width:auto;display:inline"> I understand this confidential information will be used only for authorized student welfare processing.</label>'
      .'<button type="submit">Submit Confidential Concern</button></form>'
      .'<p><a href="/welfare/track">Track an existing welfare concern</a></p>';
    page('Student Welfare', $html);
}
if ($path==='/welfare'&&$method==='POST'){
    verifyCsrf();
    try{$a=Welfare::submit($_POST);
        page('Welfare Concern Submitted','<p class="notice">Your concern was saved for confidential review.</p>'
        .'<p>Reference: <strong>'.escape($a['reference']).'</strong></p>'
        .'<p>Private tracking token: <code>'.escape($a['tracking_token']).'</code></p>'
        .'<p>Save BOTH values safely. The private token is shown only once and is required to check the status.</p>');
    }catch(DomainException $e){http_response_code(422);page('Welfare Submission Error','<p class="error">'.escape($e->getMessage()).'</p>');}
}
if ($path==='/welfare/track'&&$method==='GET'){
    page('Private Welfare Status','<p>For confidentiality, the public status page never shows your narrative, notes or attachments.</p>'
        .'<form method="post" action="/welfare/track">'.formToken()
        .'<label>Reference<input name="reference" required></label>'
        .'<label>Private tracking token<input name="token" required autocomplete="off"></label>'
        .'<button>Check My Status</button></form>');
}
if ($path==='/welfare/track'&&$method==='POST'){
    verifyCsrf();
    $s=Welfare::publicStatus(strtoupper(trim((string)($_POST['reference']??''))),trim((string)($_POST['token']??'')));
    if(!$s){http_response_code(404);page('Status Unavailable','<p>Invalid reference/token or record unavailable.</p>');}
    page('Concern Status','<p>Current status: <strong>'.escape($s['status']).'</strong></p>'
      .'<p>Submitted: '.escape($s['created_at']).'</p><p><a href="/welfare/track">Check another status</a></p>');
}
if ($path==='/staff/welfare'&&$method==='GET'){
    $actor=Auth::requireRole(['msw_head','msw_member']);
    if($actor['role']==='msw_head'){
        $rows=db()->query('SELECT id,reference_code,category,summary,priority,status,assigned_to FROM welfare_cases ORDER BY id DESC LIMIT 80')->fetchAll();
    }else {
        $q=db()->prepare('SELECT id,reference_code,category,summary,priority,status,assigned_to FROM welfare_cases WHERE assigned_to=? ORDER BY id DESC LIMIT 80');
        $q->execute([(int)$actor['id']]);$rows=$q->fetchAll();
    }
    $html='<p>Only authorized MSW reviewers may access confidential cases. President and technical admin are excluded from case details.</p>'
       .'<table><tr><th>Reference</th><th>Category</th><th>Priority</th><th>Status</th></tr>';
    foreach($rows as $c){$html.='<tr><td><a href="/staff/welfare/case?id='.(int)$c['id'].'">'.escape($c['reference_code']).'</a></td>'
        .'<td>'.escape($c['category']).'</td><td>'.escape($c['priority']).'</td><td>'.escape($c['status']).'</td></tr>';}
    page('Confidential Welfare Dashboard',$html.'</table>');
}
if ($path==='/staff/welfare/case'&&$method==='GET'){
    $actor=Auth::requireRole(['msw_head','msw_member']);
    $id=(int)($_GET['id']??0);
    try{$case=Welfare::findForActor($id,$actor);}
    catch(DomainException $e){http_response_code(403);page('Access Denied','<p>Case is not assigned to your account.</p>');}
    if(!$case){http_response_code(404);page('Not Found','<p>Case unavailable.</p>');}
    $html='<p><a href="/staff/welfare">← Cases</a></p><p><b>Reference:</b> '.escape($case['reference_code'])
        .' · '.escape($case['category']).' · '.escape($case['priority']).' · '.escape($case['status']).'</p>'
        .'<p><b>Reporter:</b> '.escape($case['reporter_name']).' ('.escape($case['reporter_email']).')</p>'
        .'<p><b>Summary:</b> '.escape($case['summary']).'</p>'
        .'<p><b>Confidential details:</b> '.nl2br(escape($case['details'])).'</p>';
    if($actor['role']==='msw_head'&&$case['status']!=='closed'){
        $reviewers=db()->query("SELECT id,display_name FROM users WHERE role='msw_member' AND is_active=1 ORDER BY display_name")->fetchAll();
        $html.='<h2>Assignment and priority</h2><form method="post" action="/staff/welfare/assign">'.formToken()
            .'<input type="hidden" name="id" value="'.$id.'"><label>Assigned reviewer<select name="reviewer_id"><option value="">Unassigned</option>';
        foreach($reviewers as $u)$html.='<option value="'.(int)$u['id'].'"'.((int)$case['assigned_to']===(int)$u['id']?' selected':'').'>'.escape($u['display_name']).'</option>';
        $html.='</select></label><label>Priority<select name="priority">';
        foreach(['low','normal','high','urgent'] as $p)$html.='<option value="'.$p.'"'.($case['priority']===$p?' selected':'').'>'.ucfirst($p).'</option>';
        $html.='</select></label><button>Save Assignment</button></form>';
    }
    if($case['status']!=='closed'){
        $allowed=[
            'submitted'=>['triaged'],'triaged'=>['in_progress','referred'],
            'in_progress'=>['referred','resolved'],'referred'=>['in_progress','resolved'],
            'resolved'=>['closed','in_progress']
        ][$case['status']]??[];
        if($actor['role']!=='msw_head')$allowed=array_values(array_diff($allowed,['resolved','closed']));
        if($allowed){
            $html.='<h2>Case status update</h2><form method="post" action="/staff/welfare/status">'.formToken()
                .'<input type="hidden" name="id" value="'.$id.'"><label>New status<select name="status">';
            foreach($allowed as $s)$html.='<option value="'.$s.'">'.escape($s).'</option>';
            $html.='</select></label><label>Private case note<textarea name="note" required minlength="10"></textarea></label>'
                .'<button>Update Case</button></form>';
        }
        $html.='<h2>Schedule follow-up</h2><form method="post" action="/staff/welfare/followup">'.formToken()
            .'<input type="hidden" name="id" value="'.$id.'"><label>Due date<input type="datetime-local" name="due_at" required></label>'
            .'<label>Instruction<textarea name="note" required minlength="10"></textarea></label><button>Save Follow-Up</button></form>'
            .'<h2>Referral</h2><form method="post" action="/staff/welfare/referral">'.formToken()
            .'<input type="hidden" name="id" value="'.$id.'"><label>Recipient office<input name="target" required maxlength="200"></label>'
            .'<label>Private referral note<textarea name="note" required minlength="10"></textarea></label><button>Record Referral</button></form>';
    }
    $q=db()->prepare('SELECT u.created_at,u.old_status,u.new_status,u.private_note,a.display_name FROM welfare_updates u JOIN users a ON a.id=u.actor_id WHERE u.case_id=? ORDER BY u.id DESC LIMIT 40');
    $q->execute([$id]);$html.='<h2>Confidential Case History</h2>';
    foreach($q->fetchAll() as $log)$html.='<p>'.escape($log['created_at']).' · '.escape($log['old_status']).' → '.escape($log['new_status']).' by '.escape($log['display_name']).'<br>'.nl2br(escape($log['private_note'])).'</p>';
    page('Welfare Case Review',$html);
}
if ($path==='/staff/welfare/assign'&&$method==='POST'){
    $actor=Auth::requireRole(['msw_head']);verifyCsrf();$id=(int)($_POST['id']??0);
    $val=$_POST['reviewer_id']??'';$reviewer=$val===''?null:filter_var($val,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if($val!==''&&!$reviewer){http_response_code(400);page('Invalid reviewer','<p>Invalid staff ID.</p>');}
    try{Welfare::assign($id,$actor,$reviewer===null?null:(int)$reviewer,(string)($_POST['priority']??''));redirect('/staff/welfare/case?id='.$id);}
    catch(DomainException $e){http_response_code(422);page('Assignment Error','<p class="error">'.escape($e->getMessage()).'</p>');}
}
if ($path==='/staff/welfare/status'&&$method==='POST'){
    $actor=Auth::requireRole(['msw_head','msw_member']);verifyCsrf();$id=(int)($_POST['id']??0);
    try{Welfare::update($id,$actor,(string)($_POST['status']??''),(string)($_POST['note']??''));redirect('/staff/welfare/case?id='.$id);}
    catch(DomainException $e){http_response_code(422);page('Status Error','<p class="error">'.escape($e->getMessage()).'</p>');}
}
if ($path==='/staff/welfare/followup'&&$method==='POST'){
    $actor=Auth::requireRole(['msw_head','msw_member']);verifyCsrf();$id=(int)($_POST['id']??0);
    try{Welfare::followup($id,$actor,(string)($_POST['due_at']??''),(string)($_POST['note']??''));redirect('/staff/welfare/case?id='.$id);}
    catch(DomainException $e){http_response_code(422);page('Follow-Up Error','<p class="error">'.escape($e->getMessage()).'</p>');}
}
if ($path==='/staff/welfare/referral'&&$method==='POST'){
    $actor=Auth::requireRole(['msw_head','msw_member']);verifyCsrf();$id=(int)($_POST['id']??0);
    try{Welfare::refer($id,$actor,(string)($_POST['target']??''),(string)($_POST['note']??''));redirect('/staff/welfare/case?id='.$id);}
    catch(DomainException $e){http_response_code(422);page('Referral Error','<p class="error">'.escape($e->getMessage()).'</p>');}
}
