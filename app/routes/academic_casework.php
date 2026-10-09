<?php
declare(strict_types=1);

use Agile\AcademicCasework;
use Agile\Auth;
use Agile\DraftBylawsPolicy;

if ($path==='/staff/academic/casework' && $method==='GET') {
    $actor=Auth::requireRole(['msw_head']);
    $terms=AcademicCasework::dashboard($actor);
    $requests=AcademicCasework::inbox($actor,30);
    $html='<p>Provisional academic screening and member correction tracking. '
       .'The September 2026 AGILE Constitution & By-Laws are a <strong>draft</strong> '
       .'and cannot authorize automated penalties or role changes.</p>'
       .'<p><a href="/staff/academic">Academic term management</a> · '
       .'<a href="/staff/academic/appeals">All correction / appeal requests</a></p>'
       .'<h2>Semester Summary</h2><table><tr><th>Academic Term</th><th>Checks</th>'
       .'<th>Pending</th><th>Human-confirmed concerns</th><th>Open review requests</th></tr>';
    foreach($terms as $term){
        $html.='<tr><td>'.escape($term['label']).' ('.escape($term['state']).')</td>'
          .'<td>'.(int)$term['total_checks'].'</td>'
          .'<td>'.(int)$term['pending_checks'].'</td>'
          .'<td>'.(int)$term['confirmed_ineligible'].'</td>'
          .'<td>'.(int)$term['open_requests'].'</td></tr>';
    }
    $html.='</table><h2>Recent corrections and appeals</h2>';
    if (!$requests)$html.='<p>No submitted correction or appeal requests.</p>';
    foreach ($requests as $req) {
        $html.='<p><a href="/staff/academic/appeals?id='.(int)$req['id'].'">'
           .'Request #'.(int)$req['id'].'</a> — '.escape($req['term_label'])
           .' · '.escape($req['full_name']).' · '.escape($req['request_type'])
           .' · '.escape($req['status']).'</p>';
    }
    page('Academic Casework Dashboard',$html);
}
if ($path==='/staff/academic/preview' && $method==='GET') {
    $actor=Auth::requireRole(['msw_head']);
    $id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if (!$id) {http_response_code(400);page('Invalid Record','<p>Valid verification ID required.</p>');}
    $q=db()->prepare('SELECT v.id,t.label,m.full_name
        FROM academic_verifications v
        JOIN members m ON m.id=v.member_id
        JOIN academic_terms t ON t.id=v.term_id
        WHERE v.id=?');
    $q->execute([(int)$id]);$check=$q->fetch();
    if (!$check) {http_response_code(404);page('Not Found','<p>Verification not found.</p>');}
    $prior=AcademicCasework::latestPreview((int)$id,$actor);
    $html='<p><a href="/staff/academic/check?id='.(int)$id.'">Return to verification</a></p>'
       .'<p><strong>'.escape($check['full_name']).'</strong> · '.escape($check['label']).'</p>'
       .'<p><b>Draft Policy:</b> '.escape(DraftBylawsPolicy::VERSION)
       .' — preliminary screening only. Historical grade coverage and any concerns '
       .'must be independently validated by an authorized human reviewer.</p>';
    if ($prior) {
        $out=json_decode($prior['outcome_json'],true);
        $html.='<h2>Latest saved preliminary preview</h2>'
            .'<p>Saved '.escape($prior['reviewed_at']).' by '.escape($prior['reviewer']).'</p>'
            .'<p>Flag: <strong>'.escape((string)($out['screening_flag']??'unknown')).'</strong></p>';
        foreach(($out['issues']??[]) as $issue)$html.='<p>'.escape((string)$issue).'</p>';
        foreach(($out['missing_information']??[]) as $issue)$html.='<p>'.escape((string)$issue).'</p>';
    }
    $html.='<h2>Generate confidential draft preview</h2>'
       .'<form method="post" action="/staff/academic/preview">'.formToken()
       .'<input type="hidden" name="id" value="'.(int)$id.'">'
       .'<label>Current verified year level (1–10)<input type="number" min="1" max="10" name="year_level"></label>'
       .'<label>Current BSIT OUS enrollment<select name="enrollment">'
       .'<option value="unknown">Not yet verified</option><option value="yes">Verified enrolled</option>'
       .'<option value="no">Not enrolled</option></select></label>'
       .'<label>Prescribed full academic load<select name="academic_load">'
       .'<option value="unknown">Not yet verified</option><option value="yes">Verified full load</option>'
       .'<option value="no">Not full load</option></select></label>'
       .'<label><input type="checkbox" name="history_complete" value="yes" style="display:inline;width:auto">'
       .' Entire institutional grade history confirmed</label>'
       .'<label>Manually reviewed grade records (JSON course / grade array)'
       .'<textarea name="grades" rows="7" required placeholder=\'[{"course":"IT101","grade":"1.5"}]\'></textarea></label>'
       .'<button>Save Provisional Preview</button></form>'
       .'<p>Data is confidential. This form does not change approved eligibility or membership assignments.</p>';
    page('Draft Bylaws Screening Preview',$html);
}
if ($path==='/staff/academic/preview' && $method==='POST') {
    $actor=Auth::requireRole(['msw_head']);verifyCsrf();
    $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if (!$id) {http_response_code(400);page('Invalid Record','<p>Valid verification ID required.</p>');}
    try {
        $grades=json_decode((string)($_POST['grades']??''),true,512,JSON_THROW_ON_ERROR);
        if (!is_array($grades))throw new DomainException('Grade entries must be an array.');
        $bool=function(string $key): ?bool {
            $value=(string)($_POST[$key]??'unknown');
            return match($value){'yes'=>true,'no'=>false,default=>null};
        };
        $year=filter_var($_POST['year_level']??null,FILTER_VALIDATE_INT,
            ['options'=>['min_range'=>1,'max_range'=>10]]);
        $payload=[
            'grade_records'=>$grades,
            'year_level'=>$year===false?null:$year,
            'current_bsit_ous_enrollment'=>$bool('enrollment'),
            'full_academic_load'=>$bool('academic_load'),
            'full_history_supplied'=>($_POST['history_complete']??'')==='yes'
        ];
        $out=AcademicCasework::preview((int)$id,$actor,$payload);
        $html='<p><strong>Provisional result:</strong> '.escape($out['screening_flag']).'</p>'
             .'<p>Policy version: '.escape($out['policy_version']).' · Not ratified.</p>'
             .'<p>No membership or academic outcome changed.</p>';
        foreach($out['issues'] as $issue)$html.='<p>'.escape($issue).'</p>';
        foreach($out['missing_information'] as $need)$html.='<p>'.escape($need).'</p>';
        page('Preliminary Screening Saved',
            $html.'<p><a href="/staff/academic/preview?id='.(int)$id.'">Return to preview</a></p>');
    }catch(\JsonException|DomainException $e){
        http_response_code(422);
        page('Draft Preview Not Saved','<p class="error">'.escape($e->getMessage()).'</p>');
    }
}
if ($path==='/member/academic' && $method==='GET') {
    $actor=Auth::requireRole(['member','msw_head','msw_member','committee_head',
        'deputy_head','executive_officer','source_editor','president']);
    $checks=AcademicCasework::ownTerms($actor);
    $html='<p>Review only your own semestral verification status. '
        .'The draft-by-laws preview is <strong>provisional</strong> and not a final eligibility decision. '
        .'For confidential evidence, use the authorized university/organization process.</p>';
    if (!$checks)$html.='<p>No academic verification cycles are linked to your approved member account.</p>';
    foreach($checks as $row) {
        $html.='<section><h2>'.escape($row['label']).'</h2>'
            .'<p>Human verification: '.escape($row['verified_result'])
            .' · Provisional screening flag: '.escape((string)($row['provisional_flag']??'not_checked'))
            .'</p>';
        $requests=AcademicCasework::ownRequests((int)$row['id'],$actor);
        foreach($requests as $req) {
            $html.='<p><b>'.escape($req['request_type']).'</b> ('.escape($req['status']).')'
              .' — '.escape($req['created_at']).'</p>'
              .'<p>'.nl2br(escape($req['request_text'])).'</p>';
            if ($req['resolution_note']!==null) {
                $html.='<p>MSW response: '.nl2br(escape($req['resolution_note'])).'</p>';
            }
        }
        $html.='<form method="post" action="/member/academic/request">'.formToken()
            .'<input type="hidden" name="verification_id" value="'.(int)$row['id'].'">'
            .'<label>Request type<select name="request_type">'
            .'<option value="correction">Correct an academic record</option>'
            .'<option value="appeal">Request reconsideration</option>'
            .'<option value="additional_information">Provide additional information</option>'
            .'</select></label>'
            .'<label>Explanation (20–3,000 characters)<textarea name="reason" minlength="20" maxlength="3000" required></textarea></label>'
            .'<button>Submit Confidential Review Request</button></form></section><hr>';
    }
    page('My Academic Verification & Appeals',$html);
}
if ($path==='/member/academic/request' && $method==='POST') {
    $actor=Auth::requireRole(['member','msw_head','msw_member','committee_head',
        'deputy_head','executive_officer','source_editor','president']);
    verifyCsrf();
    try {
        AcademicCasework::submitRequest((int)($_POST['verification_id']??0),$actor,
            (string)($_POST['request_type']??''),(string)($_POST['reason']??''));
        redirect('/member/academic');
    } catch (DomainException $e) {
        http_response_code(422);
        page('Academic Review Request Not Accepted','<p class="error">'.escape($e->getMessage()).'</p>');
    }
}
if ($path==='/staff/academic/appeals' && $method==='GET') {
    $actor=Auth::requireRole(['msw_head']);
    $requests=AcademicCasework::inbox($actor,100);
    $chosen=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $html='<p><a href="/staff/academic/casework">Return to casework dashboard</a></p>'
       .'<p>Confidential review notes and outcomes. An appeal resolution does not automatically alter grades, '
       .'eligibility decisions or role assignments.</p>';
    foreach($requests as $req) {
        $html.='<section><p><b>Request #'.(int)$req['id'].'</b> — '
            .escape($req['full_name']).' · '.escape($req['term_label'])
            .' · '.escape($req['request_type']).' · '.escape($req['status']).'</p>'
            .'<p>'.nl2br(escape($req['request_text'])).'</p>'
            .'<p><a href="/staff/academic/check?id='.(int)$req['verification_id'].'">Verification details</a></p>';
        if (in_array($req['status'],['submitted','in_review'],true)) {
            $html.='<form method="post" action="/staff/academic/appeals/update">'.formToken()
               .'<input type="hidden" name="request_id" value="'.(int)$req['id'].'">'
               .'<label>Review status<select name="status">';
            $options=$req['status']==='submitted'?
                ['in_review'=>'In review','resolved'=>'Resolved','rejected'=>'Rejected']:
                ['resolved'=>'Resolved','rejected'=>'Rejected'];
            foreach($options as $value=>$label)$html.='<option value="'.$value.'">'.escape($label).'</option>';
            $html.='</select></label><label>Evidence and response (20–3,000 characters)'
               .'<textarea name="response" minlength="20" maxlength="3000" required></textarea></label>'
               .'<button>Record Review Update</button></form>';
        }
        if ($chosen && (int)$req['id']===(int)$chosen) {
            $html.='<h3>Immutable case history</h3>';
            foreach(AcademicCasework::events((int)$req['id'],$actor) as $event) {
                $html.='<p>'.escape($event['created_at']).' · '.escape($event['event_type'])
                   .' by '.escape($event['display_name']).'<br>'
                   .nl2br(escape($event['event_note'])).'</p>';
            }
        }
        $html.='</section><hr>';
    }
    page('Academic Corrections & Appeals Inbox',$html);
}
if ($path==='/staff/academic/appeals/update' && $method==='POST') {
    $actor=Auth::requireRole(['msw_head']);verifyCsrf();
    try {
        AcademicCasework::resolve((int)($_POST['request_id']??0),$actor,
            (string)($_POST['status']??''),(string)($_POST['response']??''));
        redirect('/staff/academic/appeals');
    } catch (DomainException $e) {
        http_response_code(422);
        page('Academic Appeal Review Not Accepted','<p class="error">'.escape($e->getMessage()).'</p>');
    }
}
