<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli'||getenv('RUN_E2E_TESTS')!=='yes'||getenv('CI')!=='true') {
    fwrite(STDERR,"Identity/mail integration is restricted to disposable CI databases.\n");
    exit(1);
}
require dirname(__DIR__).'/app/bootstrap.php';

use Agile\ApplicationWorkflow;
use Agile\MailQueue;
use Agile\MemberIdentity;
use Agile\Membership;
use Agile\Messaging;
use Agile\Recruitment;

function verifyCondition(bool $yes,string $message): void {
    if (!$yes) { fwrite(STDERR,"FAIL: $message\n"); exit(1); }
    echo "PASS: $message\n";
}
function verifyDenied(callable $fn,string $message): void {
    try {$fn();} catch (DomainException $e) {echo "PASS: $message\n"; return;}
    fwrite(STDERR,"FAIL: $message (should have been denied)\n"); exit(1);
}
$pdo=db();
$suffix=bin2hex(random_bytes(6));
$password='SyntheticIdentityPassword!2026';
$staff=[];
foreach (['msw_head','msw_member','committee_head','admin'] as $role) {
    $email=($role==='committee_head'?'appointed':$role).$suffix.'@example.invalid';
    $q=$pdo->prepare('INSERT INTO users(email,display_name,password_hash,role) VALUES (?,?,?,?)');
    $q->execute([$email,'Synthetic '.$role,password_hash($password,PASSWORD_DEFAULT),$role]);
    $staff[$role]=['id'=>(int)$pdo->lastInsertId(),'role'=>$role,'email'=>$email];
}
$head=$staff['msw_head'];$officer=$staff['committee_head'];
$request=Recruitment::request($head,[
    'committee_name'=>'Synthetic Academic Committee',
    'role_category'=>'Committee Head',
    'position_title'=>'Synthetic Committee Head',
    'requested_slots'=>1,
    'reason'=>'Synthetic identity-link approval requires an authorized committee assignment.'
]);
Recruitment::decide($request,$head,true,'Approved staffing for synthetic identity integration checks.');
$q=$pdo->prepare('SELECT id FROM vacancies WHERE hr_request_id=?');
$q->execute([$request]);$vacancyId=(int)$q->fetchColumn();
$ref=Membership::submit([
    'full_name'=>'Synthetic Committee Officer',
    'email'=>$officer['email'],
    'student_number'=>'T-'.$suffix,
    'desired_role'=>'Committee Head',
    'vacancy_id'=>$vacancyId,
    'motivation'=>'Synthetic approved member connected to an existing committee leadership account.',
    'privacy_consent'=>'yes',
    'answers'=>[
        'preferred_committee'=>'Synthetic Academic Committee',
        'leadership_experience'=>'Synthetic committee leadership test record.',
        'proposed_programs'=>'Synthetic academic support and volunteer onboarding.',
    ],
]);
$q=$pdo->prepare('SELECT id FROM membership_applications WHERE reference_code=?');
$q->execute([$ref]);$applicationId=(int)$q->fetchColumn();
ApplicationWorkflow::changeStatus($applicationId,$head,'screening','Reviewed synthetic officer record');
ApplicationWorkflow::changeStatus($applicationId,$head,'for_interview','Synthetic leadership interview scheduled');
$interviewId=Recruitment::scheduleInterview(
    $applicationId,$head,(new DateTimeImmutable('+3 days'))->format('Y-m-d H:i'),
    'Synthetic virtual committee interview'
);
Recruitment::completeInterview($interviewId,$head);
Recruitment::recordEvaluation($applicationId,$head,90,'recommend',
    'Synthetic committee-head leadership assessment completed.');
ApplicationWorkflow::changeStatus($applicationId,$head,'for_verification','Checking synthetic officer requirements');
ApplicationWorkflow::updateVerification($applicationId,$head,true,true,
    'Verified synthetic committee interview and application documents.');
ApplicationWorkflow::changeStatus($applicationId,$head,'approved',
    'Authorized membership appointment after synthetic evidence.');
$q=$pdo->prepare('SELECT id,user_id FROM members WHERE application_id=?');
$q->execute([$applicationId]);$member=$q->fetch();$memberId=(int)$member['id'];
verifyCondition($member['user_id']===null,'Appointed membership starts without an unsafe automatic user link');

$candidates=MemberIdentity::candidates($officer);
verifyCondition(count($candidates)===1 && (int)$candidates[0]['id']===$memberId,
    'Only matching email and active organizational position are offered for linking');
verifyCondition(MemberIdentity::candidates($staff['msw_member'])===[],
    'Unrelated staff cannot see member identity candidates');
verifyDenied(fn()=>MemberIdentity::request($memberId,$officer,'incorrect password'),
    'Identity link cannot be requested without current account password');
verifyDenied(fn()=>MemberIdentity::request($memberId,$staff['msw_member'],$password),
    'Unmatched email and organizational role cannot be linked');
$link=MemberIdentity::request($memberId,$officer,$password);
verifyCondition($link>0,'Validated staff account creates a pending identity link');
verifyDenied(fn()=>MemberIdentity::review($link,$officer,true,
    'The requester must never approve their own identity.'),
    'Requester cannot approve identity link');
verifyDenied(fn()=>MemberIdentity::review($link,$staff['admin'],true,
    'Admin is allowed only to review membership link for MSW Head.'),
    'Technical admin cannot approve other organizational staff identity');
verifyDenied(fn()=>MemberIdentity::request($memberId,$officer,$password),
    'Second pending identity link is blocked');
$queue=MemberIdentity::pendingForReviewer($head);
verifyCondition(count(array_filter($queue,fn($r)=>(int)$r['id']===$link))===1,
    'MSW Head sees an independently reviewable identity request');
MemberIdentity::review($link,$head,true,
    'Confirmed organizational position and matching credential-backed identity.');
$q=$pdo->prepare('SELECT user_id FROM members WHERE id=?');$q->execute([$memberId]);
verifyCondition((int)$q->fetchColumn()===(int)$officer['id'],
    'Independent approval securely links existing staff account to member');
verifyDenied(fn()=>MemberIdentity::review($link,$head,true,
    'Duplicated independent approval must always fail.'),
    'Approved identity cannot be linked twice');
verifyDenied(fn()=>MemberIdentity::request($memberId,$officer,$password),
    'Already-linked member cannot be re-linked');

$key='synthetic.mail.'.$suffix;
Messaging::enqueue($key,'synthetic@example.invalid','Synthetic Approval Notice','Synthetic mail only');
Messaging::enqueue($key,'synthetic@example.invalid','Synthetic Approval Notice','Synthetic mail only');
$q=$pdo->prepare('SELECT COUNT(*) FROM notification_outbox WHERE event_key=?');
$q->execute([$key]);verifyCondition((int)$q->fetchColumn()===1,
    'Mail outbox ignores duplicate event keys');
putenv('MAIL_TRANSPORT=disabled');
$worker=MailQueue::work(100); // Consume other synthetic queue entries from earlier CI tests.
verifyCondition($worker['disabled']===1 && $worker['sent']===0,
    'Unconfigured Gmail is not called or falsely marked submitted');
$q=$pdo->prepare('SELECT id,status FROM notification_outbox WHERE event_key=?');
$q->execute([$key]);$mail=$q->fetch();
verifyCondition($mail['status']==='disabled','Disabled mail remains visibly blocked');
verifyDenied(fn()=>MailQueue::review((int)$mail['id'],$staff['msw_member'],'mark_failed',
    'Only the authorized MSW Head may reconcile an outgoing mail item.'),
    'MSW Member cannot review email failures');
verifyDenied(fn()=>MailQueue::review((int)$mail['id'],$head,'requeue',
    'Manual duplicate risk review was completed but transport remains disabled.'),
    'Cannot requeue email when Gmail transport is disabled');
MailQueue::review((int)$mail['id'],$head,'mark_failed',
    'Gmail transport is disabled, so the message should be stopped safely.');
$q=$pdo->prepare('SELECT COUNT(*) FROM mail_delivery_reviews WHERE outbox_id=? AND decision=?');
$q->execute([(int)$mail['id'],'mark_failed']);
verifyCondition((int)$q->fetchColumn()===1,'Operator reconciliation decision is auditable');

$key2='synthetic.unknown.'.$suffix;
Messaging::enqueue($key2,'synthetic2@example.invalid','Synthetic Unknown Delivery','Synthetic body');
$pdo->prepare("UPDATE notification_outbox
     SET status='processing',processing_started_at=DATE_SUB(NOW(),INTERVAL 30 MINUTE)
     WHERE event_key=?")->execute([$key2]);
verifyCondition(MailQueue::recoverStale(10)>=1,'Interrupted Gmail job is detected');
$q=$pdo->prepare('SELECT status FROM notification_outbox WHERE event_key=?');
$q->execute([$key2]);
verifyCondition($q->fetchColumn()==='needs_review',
    'Interrupted job is quarantined for manual verification, never automatically resent');

echo "Independent identity and mail reconciliation tests passed with synthetic-only data.\n";
