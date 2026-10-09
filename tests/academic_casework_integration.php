<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli'||getenv('RUN_E2E_TESTS')!=='yes'||getenv('CI')!=='true') {
    fwrite(STDERR,"Only disposable synthetic CI databases may run academic casework tests.\n");
    exit(1);
}
require dirname(__DIR__).'/app/bootstrap.php';

use Agile\Academic;
use Agile\AcademicCasework;
use Agile\DraftBylawsPolicy;

function acadOk(bool $ok,string $message): void {
    if (!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}
    echo "PASS: $message\n";
}
function acadDenied(callable $operation,string $message): void {
    try{$operation();}catch(DomainException $e){echo "PASS: $message\n";return;}
    fwrite(STDERR,"FAIL: $message (unauthorized action allowed)\n");exit(1);
}

$pdo=db();
$suffix=bin2hex(random_bytes(7));
$actors=[];
foreach(['msw_head','executive_officer','member','president'] as $index=>$role){
    $email="academic-$index-$suffix@example.invalid";
    $q=$pdo->prepare('INSERT INTO users(email,display_name,password_hash,role) VALUES (?,?,?,?)');
    $q->execute([$email,"Synthetic Academic $index",password_hash('SyntheticPolicyPassword!2026',PASSWORD_DEFAULT),$role]);
    $actors[$index]=['id'=>(int)$pdo->lastInsertId(),'role'=>$role,'email'=>$email];
}
$head=$actors[0];$officer=$actors[1];$general=$actors[2];$president=$actors[3];

$memberIds=[];
foreach ([
    ['actor'=>$officer,'type'=>'appointed','role'=>'Executive Officer'],
    ['actor'=>$general,'type'=>'general','role'=>'General Member'],
] as $index=>$fixture) {
    $appref='AG-'.strtoupper(bin2hex(random_bytes(7)));
    $q=$pdo->prepare("INSERT INTO membership_applications
         (reference_code,full_name,email,student_number,desired_role,motivation,status,consent_at)
         VALUES (?,?,?,?,?,'Synthetic application solely for confidential security testing.','approved',NOW())");
    $q->execute([$appref,'Synthetic Academic Student '.($index+1),$fixture['actor']['email'],
        'SIM-'.$suffix.'-'.$index,$fixture['role']]);
    $appId=(int)$pdo->lastInsertId();
    $q=$pdo->prepare("INSERT INTO members
         (application_id,user_id,student_number,full_name,email,membership_type,verification_token_hash)
         VALUES (?,?,?,?,?,?,?)");
    $q->execute([$appId,$fixture['actor']['id'],'SIM-'.$suffix.'-'.$index,
        'Synthetic Academic Student '.($index+1),$fixture['actor']['email'],$fixture['type'],
        hash('sha256',random_bytes(32))]);
    $memberIds[]=(int)$pdo->lastInsertId();
}
$officerMember=$memberIds[0];$generalMember=$memberIds[1];
$pdo->prepare("INSERT INTO role_assignments(member_id,role_category,position_title,recorded_by)
    VALUES (?,'Executive Officer','President',?)")->execute([$officerMember,(int)$head['id']]);
$pdo->prepare("INSERT INTO academic_terms(label,starts_on,ends_on,deadline_on,state)
    VALUES (?,DATE_SUB(CURDATE(),INTERVAL 3 DAY),DATE_ADD(CURDATE(),INTERVAL 90 DAY),
        DATE_ADD(CURDATE(),INTERVAL 7 DAY),'active')")->execute(['Synthetic Academic Casework '.$suffix]);
$term=(int)$pdo->lastInsertId();

Academic::initiate($term,$head);
$q=$pdo->prepare('SELECT id FROM academic_verifications WHERE term_id=? AND member_id=?');
$q->execute([$term,$officerMember]);
$checkId=(int)$q->fetchColumn();
acadOk($checkId>0,'Covered executive officer is added to semestral roster');
$q->execute([$term,$generalMember]);
acadOk(!$q->fetchColumn(),'General Member is excluded from semester grade checks');

$grades=[
    ['course'=>'IT 301','grade'=>'5.0'],
    ['course'=>'IT 302','grade'=>'1.50'],
];
$input=[
    'role_category'=>'General Member', // malicious caller-provided role must be ignored
    'executive_position'=>'Other office',
    'year_level'=>2,
    'current_bsit_ous_enrollment'=>true,
    'full_academic_load'=>true,
    'full_history_supplied'=>false,
    'grade_records'=>$grades
];
acadDenied(fn()=>AcademicCasework::preview($checkId,$president,$input),
    'President cannot access academic grade preview');
acadDenied(fn()=>AcademicCasework::preview($checkId,$officer,$input),
    'Linked officer cannot generate reviewer-only preview');
$out=AcademicCasework::preview($checkId,$head,$input);
acadOk($out['policy_status']==='draft_not_ratified' &&
    $out['policy_version']===DraftBylawsPolicy::VERSION,
    'Draft is versioned and visibly unratified');
acadOk($out['screening_flag']==='review_required',
    'Draft flags 5.0 and under-year President for manual review');
acadOk(str_contains(implode(' ', $out['issues']),'minimum of 3'),
    'Effective executive position was derived from actual membership assignment');
acadOk(str_contains(implode(' ', $out['missing_information']),'institutional stay'),
    'Incomplete institutional grade history prompts further review');
$latest=AcademicCasework::latestPreview($checkId,$head);
acadOk((bool)$latest && (int)$latest['reviewed_by']===(int)$head['id'],
    'Provisional preview and reviewer are persisted privately');

$q=$pdo->prepare('SELECT policy_id,verified_result,automated_flag FROM academic_verifications WHERE id=?');
$q->execute([$checkId]);$official=$q->fetch();
acadOk($official['policy_id']===null && $official['verified_result']==='pending'
    && $official['automated_flag']==='not_checked',
    'Draft preview cannot alter official eligibility or approved-policy screening');
$q=$pdo->prepare('SELECT membership_type FROM members WHERE id=?');
$q->execute([$officerMember]);
acadOk($q->fetchColumn()==='appointed','Draft grade flag never demotes an organizational officer');

$mine=AcademicCasework::ownTerms($officer);
acadOk(count(array_filter($mine,fn($row)=>(int)$row['id']===$checkId))===1,
    'Linked officer sees own verification term and provisional status');
acadOk(AcademicCasework::ownTerms($general)===[],
    'General member sees no grade verification records');
acadDenied(fn()=>AcademicCasework::ownRequests($checkId,$general),
    'Another member cannot read someone else academic requests');
acadDenied(fn()=>AcademicCasework::submitRequest($checkId,$general,'appeal',
    'Synthetic attempt to change another student private grades.'),
    'Another member cannot submit appeal for someone else');
acadDenied(fn()=>AcademicCasework::submitRequest($checkId,$president,'appeal',
    'Synthetic officer on a different account tries unauthorized appeal.'),
    'President without matching member link cannot appeal another record');

$requestId=AcademicCasework::submitRequest($checkId,$officer,'correction',
    'My synthetic institutional grade record needs manual verification against its official source.');
acadOk($requestId>0,'Linked member submits confidential correction request');

acadOk((int)\Agile\AcademicCasework::authorizeEvidence($requestId,$officer,true)['member_id']===$officerMember,
    'Academic appeal owner may attach documents to a pending own request');
acadOk((int)\Agile\AcademicCasework::authorizeEvidence($requestId,$head,false)['member_id']===$officerMember,
    'MSW Head may review authorized confidential appeal evidence');
acadDenied(fn()=>\Agile\AcademicCasework::authorizeEvidence($requestId,$general,false),
    'Another member cannot download someone else academic evidence');
acadDenied(fn()=>\Agile\AcademicCasework::authorizeEvidence($requestId,$president,true),
    'Unlinked President may not upload to someone else appeal');

acadDenied(fn()=>AcademicCasework::submitRequest($checkId,$officer,'appeal',
    'Submitting another pending request should be rejected until review finishes.'),
    'Duplicate pending correction / appeal requests are blocked');
$inbox=AcademicCasework::inbox($head);
acadOk(count(array_filter($inbox,fn($row)=>(int)$row['id']===$requestId))===1,
    'MSW Head inbox receives confidential academic correction');
acadDenied(fn()=>AcademicCasework::inbox($president),
    'President cannot inspect academic casework inbox');
acadDenied(fn()=>AcademicCasework::resolve($requestId,$president,'resolved',
    'Unauthorized disposition attempt on confidential student academic review.'),
    'President cannot resolve academic appeals');

AcademicCasework::resolve($requestId,$head,'in_review',
    'Synthetic correction under human review; official academic source will be consulted.');
AcademicCasework::resolve($requestId,$head,'resolved',
    'Synthetic case response recorded; outcome only concerns the review request, not appointment.');
acadDenied(fn()=>AcademicCasework::resolve($requestId,$head,'in_review',
    'Previously resolved requests must remain immutable.'),
    'Resolved academic case cannot be reopened or overwritten');
$events=AcademicCasework::events($requestId,$head);
acadDenied(fn()=>\Agile\AcademicCasework::authorizeEvidence($requestId,$officer,true),
    'Resolved academic request cannot receive more student evidence');
acadOk(count($events)===3 &&
    array_column($events,'event_type')===['submitted','in_review','resolved'],
    'Academic request maintains append-only submit/review/resolve event history');
$own=AcademicCasework::ownRequests($checkId,$officer);
acadOk(count($own)>=1 && $own[0]['status']==='resolved' && $own[0]['resolution_note']!==null,
    'Applicant sees own reviewer response');
$q=$pdo->prepare('SELECT verified_result FROM academic_verifications WHERE id=?');
$q->execute([$checkId]);
acadOk($q->fetchColumn()==='pending','Appeal resolution does not change official academic eligibility');
$q=$pdo->prepare('SELECT COUNT(*) FROM role_assignments WHERE member_id=? AND ends_at IS NULL');
$q->execute([$officerMember]);
acadOk((int)$q->fetchColumn()===1,'Officer retains role after preliminary screening and appeal resolution');
$secondId=AcademicCasework::submitRequest($checkId,$officer,'additional_information',
    'Additional synthetic academic evidence will be presented to the authorized MSW reviewer.');
acadOk($secondId>$requestId,'New request allowed once previous request was resolved');

$summary=AcademicCasework::dashboard($head);
$termSummary=array_values(array_filter($summary,fn($t)=>(int)$t['id']===$term));
acadOk(count($termSummary)===1 && (int)$termSummary[0]['open_requests']===1,
    'MSW dashboard summarizes academic term and pending appeals');
acadDenied(fn()=>AcademicCasework::dashboard($president),
    'President cannot access confidential academic dashboard');

echo "Phase 7 provisional screening, student appeal and RBAC integration tests passed.\n";
