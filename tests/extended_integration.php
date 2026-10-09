<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('RUN_E2E_TESTS')!=='yes'||getenv('CI')!=='true'){
  fwrite(STDERR,"Disposable CI MySQL only.\n");exit(1);
}
require dirname(__DIR__).'/app/bootstrap.php';
use Agile\Recruitment;use Agile\Membership;use Agile\ApplicationWorkflow;
use Agile\Welfare;use Agile\Content;use Agile\Academic;use Agile\Member;
use Agile\Messaging;use Agile\Automation;

function ok(bool $yes,string $name):void{
 if(!$yes){fwrite(STDERR,"FAIL: $name\n");exit(1);}
 echo "PASS: $name\n";
}
function mustDeny(callable $fn,string $name):void{
 try{$fn();}catch(DomainException $e){echo "PASS: $name\n";return;}
 fwrite(STDERR,"FAIL: $name\n");exit(1);
}
$pdo=db();$suffix=bin2hex(random_bytes(5));$actors=[];
foreach(['msw_head','msw_member','committee_head','source_editor','executive_officer','president'] as $role){
 $q=$pdo->prepare('INSERT INTO users(email,display_name,password_hash,role) VALUES (?,?,?,?)');
 $q->execute([$role.$suffix.'@example.invalid','Synthetic '.$role,
     password_hash('SyntheticPassword!2026',PASSWORD_DEFAULT),$role]);
 $actors[$role]=['id'=>(int)$pdo->lastInsertId(),'role'=>$role];
}
$head=$actors['msw_head'];$member=$actors['msw_member'];
$requestId=Recruitment::request($actors['committee_head'],[
 'committee_name'=>'Membership and Student Welfare','role_category'=>'Committee Member',
 'position_title'=>'Student Welfare Volunteer','requested_slots'=>1,
 'reason'=>'Synthetic recruitment need for testing.' ]);
Recruitment::decide($requestId,$head,true,'Approved synthetic recruitment test.');
$q=$pdo->prepare('SELECT * FROM vacancies WHERE hr_request_id=?');$q->execute([$requestId]);$v=$q->fetch();
ok((bool)$v&&$v['capacity']==1,'HR request becomes published position');
$ref=Membership::submit([
 'full_name'=>'Synthetic Volunteer','email'=>'volunteer'.$suffix.'@example.invalid',
 'student_number'=>'TEST-'.$suffix,'desired_role'=>'Committee Member','vacancy_id'=>(int)$v['id'],
 'motivation'=>'This test simulates a student recruitment application.',
 'privacy_consent'=>'yes',
 'answers'=>['preferred_committee'=>'Membership and Student Welfare','relevant_skills'=>'Synthetic documentation skills']
]);
$q=$pdo->prepare('SELECT id FROM membership_applications WHERE reference_code=?');$q->execute([$ref]);$app=(int)$q->fetchColumn();
ApplicationWorkflow::assignReviewer($app,$head,(int)$member['id']);
ApplicationWorkflow::changeStatus($app,$member,'screening','Starting synthetic screening');
ApplicationWorkflow::changeStatus($app,$member,'for_interview','Synthetic interview to be completed');
$interview=Recruitment::scheduleInterview($app,$member,(new DateTimeImmutable('+4 days'))->format('Y-m-d H:i'),'Synthetic secure video meeting');
Recruitment::completeInterview($interview,$member);
Recruitment::recordEvaluation($app,$member,85,'recommend','Completed synthetic evaluation notes.');
ApplicationWorkflow::changeStatus($app,$member,'for_verification','Beginning synthetic documentation checks');
ApplicationWorkflow::updateVerification($app,$head,true,true,'Interview and verification supported by test records.');
ApplicationWorkflow::changeStatus($app,$head,'approved','Decision made following the synthetic interview and verification.');
$q=$pdo->prepare('SELECT m.id,m.membership_type,m.verification_token_hash FROM members m WHERE application_id=?');$q->execute([$app]);$m=$q->fetch();
ok((bool)$m&&$m['membership_type']==='appointed','Approval creates actual appointed member');
$q=$pdo->prepare('SELECT filled FROM vacancies WHERE id=?');$q->execute([(int)$v['id']]);
ok((int)$q->fetchColumn()===1,'Vacancy capacity reconciled');

$case=Welfare::submit(['reporter_name'=>'Synthetic Reporter','reporter_email'=>'case'.$suffix.'@example.invalid',
 'category'=>'Academic','summary'=>'Synthetic welfare scenario',
 'details'=>'This is a synthetic concern for staff access control testing.',
 'privacy_consent'=>'yes']);
$q=$pdo->prepare('SELECT id FROM welfare_cases WHERE reference_code=?');$q->execute([$case['reference']]);
$caseId=(int)$q->fetchColumn();
mustDeny(fn()=>Welfare::findForActor($caseId,$member),'Unassigned MSW cannot see confidential welfare case');
mustDeny(fn()=>Welfare::findForActor($caseId,$actors['president']),'President cannot see private welfare narrative');
Welfare::assign($caseId,$head,(int)$member['id'],'high');
Welfare::update($caseId,$member,'triaged','Assigned synthetic review performed');
Welfare::update($caseId,$member,'in_progress','Assigned synthetic follow-up started');
mustDeny(fn()=>Welfare::update($caseId,$member,'resolved','MSW member tries to finalize'),
  'Member cannot close welfare case');
Welfare::update($caseId,$head,'resolved','Authorized synthetic resolution after case review.');
ok(Welfare::publicStatus($case['reference'],$case['tracking_token'])['status']==='resolved',
 'Welfare tracking returns current status using private token');
ok(Welfare::publicStatus($case['reference'],str_repeat('0',48))===null,'Invalid welfare token denied');

$post=Content::draft($actors['source_editor'],[
 'title'=>'Synthetic Publication News','content_type'=>'publication',
 'body'=>'Synthetic article created only for a repeatable content approval test.' ]);
Content::changeStatus($post,$actors['source_editor'],'review');
mustDeny(fn()=>Content::changeStatus($post,$actors['source_editor'],'published'),
 'Publication editor cannot authorize public publishing');
Content::changeStatus($post,$actors['executive_officer'],'published');
ok(count(array_filter(Content::published(),fn($p)=>(int)$p['id']===$post))===1,
 'Approved publication appears on public feed');

$pdo->prepare("INSERT INTO academic_terms(label,starts_on,ends_on,deadline_on,state)
 VALUES (?,DATE_SUB(CURDATE(), INTERVAL 10 DAY),DATE_ADD(CURDATE(),INTERVAL 60 DAY),DATE_ADD(CURDATE(),INTERVAL 7 DAY),'active')")
 ->execute(['Synthetic '.$suffix]);$term=(int)$pdo->lastInsertId();
ok(Academic::initiate($term,$head)===1,'Academic cycle includes covered appointed member');
$q=$pdo->prepare('SELECT id FROM academic_verifications WHERE term_id=? AND member_id=?');
$q->execute([$term,(int)$m['id']]);$check=(int)$q->fetchColumn();
mustDeny(fn()=>Academic::screen($check,$head,[['course'=>'IT101','grade'=>'5.0']]),
 'Eligibility screening blocked without approved policy');
$pdo->prepare('INSERT INTO eligibility_policies(policy_version,criteria_json,is_approved,approved_by,approved_at) VALUES(?,?,1,?,NOW())')
 ->execute(['SYNTHETIC-'.$suffix,json_encode(['flag_codes'=>['5.0','F','W','D']]),$head['id']]);
$r=Academic::screen($check,$head,[['course'=>'IT101','grade'=>'5.0']]);
ok($r['flag']==='review_required','Policy engine flags grade for human review');
$q=$pdo->prepare('SELECT membership_type FROM members WHERE id=?');$q->execute([(int)$m['id']]);
ok($q->fetchColumn()==='appointed','Automated screening cannot demote member');
Academic::verify($check,$head,'ineligible','Synthetic human decision with documented verification evidence.');
// Queue invitation before linking a synthetic privileged staff identity.
Member::sendInvitation((int)$m['id'],$head);
$pdo->prepare('UPDATE members SET user_id=? WHERE id=?')
    ->execute([(int)$actors['committee_head']['id'],(int)$m['id']]);
Academic::transitionToGeneral($check,$head,'Synthetic role transition after documented approval and due process.');
$q=$pdo->prepare('SELECT role FROM users WHERE id=?');
$q->execute([(int)$actors['committee_head']['id']]);
ok($q->fetchColumn()==='member','Authorized role transition revokes linked committee-head privileges');
$q=$pdo->prepare('SELECT membership_type FROM members WHERE id=?');$q->execute([(int)$m['id']]);
ok($q->fetchColumn()==='general','General Member retained after approved transition');
$q=$pdo->prepare('SELECT filled FROM vacancies WHERE id=?');$q->execute([(int)$v['id']]);
ok((int)$q->fetchColumn()===0,'Vacancy reopened after authorized role transition');

$q=$pdo->query("SELECT COUNT(*) FROM notification_outbox WHERE event_key LIKE 'member.invite.%'");
ok((int)$q->fetchColumn()>=1,'Member invitation queued without sending real email');
ok(Member::publicVerification($m['verification_token_hash'])===null,'Public member verification private by default');
$stats=Automation::runDue();
ok(is_array($stats),'Scheduled automation runs against real MySQL');
echo "Extended functional, authorization and automation checks passed.\n";
