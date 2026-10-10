<?php
declare(strict_types=1);
use Agile\Auth;
use Agile\Recruitment;
use Agile\ApplicationWorkflow;

// Public vacancies: no applicant information.
if ($path==='/vacancies'&&$method==='GET') {
    $html='<p>Published AGILE OUS recruitment positions, subject to available slots.</p><div class="table-scroll" role="region" aria-label="Available recruitment positions" tabindex="0"><table><tr><th>Committee</th><th>Position</th><th>Open</th></tr>';
    foreach(Recruitment::openVacancies() as $v) {
        $html.='<tr><td>'.escape($v['committee_name']).'</td><td>'.escape($v['position_title']).'</td><td>'.((int)$v['capacity']-(int)$v['filled']).'</td></tr>';
    }
    page('Available Positions',$html.'</table></div><p><a href="/apply">Apply for membership</a></p>');
}
if ($path==='/staff/hr'&&$method==='GET') {
    $actor=Auth::requireRole(['msw_head','committee_head']);
    $html='<h2>Human Resource Requests</h2><form method="post" action="/staff/hr">'.formToken()
      .'<label>Committee<input name="committee_name" required maxlength="140"></label>'
      .'<label>Role category<select name="role_category">';
    foreach(Recruitment::CATEGORIES as $category) $html.='<option>'.escape($category).'</option>';
    $html.='</select></label><label>Position title<input name="position_title" required maxlength="140"></label>'
      .'<label>Number of positions<input type="number" min="1" max="30" name="requested_slots" required></label>'
      .'<label>Justification<textarea name="reason" minlength="15" maxlength="2500" required></textarea></label>'
      .'<button>Submit HR request</button></form>';
    if($actor['role']==='msw_head'){
        $rows=db()->query("SELECT h.*,u.display_name FROM hr_requests h JOIN users u ON u.id=h.requested_by ORDER BY h.id DESC LIMIT 80")->fetchAll();
        $html.='<h2>Requested positions</h2>';
        foreach($rows as $r){
            $html.='<section><p><b>'.escape($r['position_title']).'</b> — '.escape($r['committee_name'])
                .' · '.(int)$r['requested_slots'].' slot(s) · '.escape($r['status']).'</p>'
                .'<p>Requested by '.escape($r['display_name']).': '.escape($r['reason']).'</p>';
            if($r['status']==='pending'){
                $html.='<form method="post" action="/staff/hr/decision">'.formToken()
                 .'<input type="hidden" name="request_id" value="'.(int)$r['id'].'">'
                 .'<label>Decision note<textarea name="note" minlength="10" maxlength="1000" required></textarea></label>'
                 .'<button name="decision" value="approve">Approve and publish vacancy</button>'
                 .'<button name="decision" value="reject">Reject request</button></form>';
            }
            $html.='</section><hr>';
        }
    }
    page('HR Request Management',$html);
}
if ($path==='/staff/hr'&&$method==='POST') {
    $actor=Auth::requireRole(['msw_head','committee_head']);verifyCsrf();
    try{Recruitment::request($actor,$_POST);redirect('/staff/hr');}
    catch(DomainException $e){http_response_code(422);page('HR Request Error','<p class="error">'.escape($e->getMessage()).'</p>');}
}
if ($path==='/staff/hr/decision'&&$method==='POST') {
    $actor=Auth::requireRole(['msw_head']);verifyCsrf();
    $id=filter_var($_POST['request_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if(!$id){http_response_code(400);page('Invalid HR request','<p>Invalid ID.</p>');}
    try {
        Recruitment::decide((int)$id,$actor,($_POST['decision']??'')==='approve',(string)($_POST['note']??''));
        redirect('/staff/hr');
    }catch(DomainException $e){http_response_code(422);page('Decision Error','<p class="error">'.escape($e->getMessage()).'</p>');}
}
if ($path==='/staff/recruitment'&&$method==='GET') {
    $actor=Auth::requireRole(['msw_head','msw_member']);
    $id=filter_var($_GET['application_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if(!$id){http_response_code(400);page('Invalid application','<p>Open an application from the staff dashboard first.</p>');}
    try{$application=ApplicationWorkflow::findForActor((int)$id,$actor);}
    catch(DomainException $e){http_response_code(403);page('Denied','<p>Application not assigned to you.</p>');}
    if(!$application){http_response_code(404);page('Not Found','<p>Application unavailable.</p>');}
    $html='<p><a href="/application?id='.(int)$id.'">Back to application</a></p>'
       .'<h2>Interview schedule</h2><form method="post" action="/staff/recruitment/interview">'.formToken()
       .'<input type="hidden" name="application_id" value="'.(int)$id.'">'
       .'<label>Interview date/time<input type="datetime-local" name="starts_at" required></label>'
       .'<label>Meeting details<input name="meeting_details" maxlength="500" required></label><button>Schedule</button></form>';
    $q=db()->prepare('SELECT * FROM interviews WHERE application_id=? ORDER BY starts_at DESC');
    $q->execute([(int)$id]);
    foreach($q->fetchAll() as $row){
        $html.='<p>Interview '.escape($row['starts_at']).' — '.escape($row['status'])
             .' · '.escape($row['meeting_details']).'</p>';
        if($row['status']==='scheduled'){
            $html.='<form method="post" action="/staff/recruitment/complete">'.formToken()
               .'<input type="hidden" name="interview_id" value="'.(int)$row['id'].'">'
               .'<input type="hidden" name="application_id" value="'.(int)$id.'"><button>Mark completed</button></form>';
        }
    }
    $html.='<h2>Evaluation</h2><form method="post" action="/staff/recruitment/evaluate">'.formToken()
        .'<input type="hidden" name="application_id" value="'.(int)$id.'">'
        .'<label>Score (0–100)<input type="number" min="0" max="100" name="score" required></label>'
        .'<label>Recommendation<select name="recommendation"><option value="recommend">Recommend</option><option value="hold">Hold</option><option value="not_recommend">Not recommend</option></select></label>'
        .'<label>Evaluation notes<textarea name="notes" required minlength="10"></textarea></label><button>Save evaluation</button></form>';
    $q=db()->prepare('SELECT e.score,e.recommendation,e.notes,e.created_at,u.display_name FROM evaluations e JOIN users u ON u.id=e.evaluator_id WHERE application_id=? ORDER BY e.id DESC');
    $q->execute([(int)$id]);
    foreach($q->fetchAll() as $e){
        $html.='<p>'.escape($e['created_at']).' — Score: '.(int)$e['score'].' · '.escape($e['recommendation'])
            .' by '.escape($e['display_name']).'<br>'.nl2br(escape($e['notes'])).'</p>';
    }
    page('Interview and Evaluation',$html);
}
if ($path==='/staff/recruitment/interview'&&$method==='POST') {
    $actor=Auth::requireRole(['msw_head','msw_member']);verifyCsrf();
    $id=(int)($_POST['application_id']??0);
    try{Recruitment::scheduleInterview($id,$actor,(string)($_POST['starts_at']??''),(string)($_POST['meeting_details']??''));redirect('/staff/recruitment?application_id='.$id);}
    catch(DomainException $e){http_response_code(422);page('Scheduling Error','<p class="error">'.escape($e->getMessage()).'</p>');}
}
if ($path==='/staff/recruitment/complete'&&$method==='POST') {
    $actor=Auth::requireRole(['msw_head','msw_member']);verifyCsrf();
    $id=(int)($_POST['application_id']??0);
    try{Recruitment::completeInterview((int)($_POST['interview_id']??0),$actor);redirect('/staff/recruitment?application_id='.$id);}
    catch(DomainException $e){http_response_code(422);page('Interview Error','<p class="error">'.escape($e->getMessage()).'</p>');}
}
if ($path==='/staff/recruitment/evaluate'&&$method==='POST') {
    $actor=Auth::requireRole(['msw_head','msw_member']);verifyCsrf();
    $id=(int)($_POST['application_id']??0);
    try{Recruitment::recordEvaluation($id,$actor,(int)($_POST['score']??-1),(string)($_POST['recommendation']??''),(string)($_POST['notes']??''));redirect('/staff/recruitment?application_id='.$id);}
    catch(DomainException $e){http_response_code(422);page('Evaluation Error','<p class="error">'.escape($e->getMessage()).'</p>');}
}
