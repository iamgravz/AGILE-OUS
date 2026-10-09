<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli'||getenv('CI')!=='true'||getenv('RUN_E2E_TESTS')!=='yes') {
    fwrite(STDERR,"Phase 10 acceptance fixtures require disposable CI with synthetic records.\n");
    exit(1);
}
require dirname(__DIR__).'/app/bootstrap.php';

use Agile\Welfare;
use Agile\Content;

$destination=getenv('PHASE10_FIXTURE');
$password=getenv('HTTP_TEST_PASSWORD');
if (!is_string($destination)||$destination===''||!str_starts_with($destination,sys_get_temp_dir().'/')
    || !is_string($password)||strlen($password)<12) {
    throw new RuntimeException('Configure a protected temporary fixture path and synthetic password.');
}

$pdo=db();
$suffix=bin2hex(random_bytes(5));
$roles=[
 'president'=>'president','admin'=>'admin','assigned'=>'msw_member',
 'unassigned'=>'msw_member','committee'=>'committee_head',
 'editor'=>'source_editor','member'=>'member'
];
$actors=[];
foreach($roles as $label=>$role){
    $email='phase10-'.$label.'-'.$suffix.'@example.invalid';
    $q=$pdo->prepare('INSERT INTO users (email,display_name,password_hash,role)
        VALUES(?,?,?,?)');
    $q->execute([$email,'Phase10 Synthetic '.ucfirst($label),
        password_hash($password,PASSWORD_DEFAULT),$role]);
    $actors[$label]=['id'=>(int)$pdo->lastInsertId(),'email'=>$email,'role'=>$role];
}
$addApplication=function(string $label,string $status,?int $reviewer=null)use($pdo,$suffix):int{
    $code='AG-'.strtoupper(bin2hex(random_bytes(7)));
    $q=$pdo->prepare('INSERT INTO membership_applications
        (reference_code,full_name,email,student_number,desired_role,motivation,status,consent_at,assigned_to)
        VALUES(?,?,?,?,?,?,?,NOW(),?)');
    $q->execute([$code,'Phase10 Private Applicant '.$label,
        'phase10-app-'.$label.'-'.$suffix.'@example.invalid',
        'UAT-'.$label.'-'.$suffix,'General Member',
        'Synthetic test data only for scoped membership access.',$status,$reviewer]);
    return (int)$pdo->lastInsertId();
};
$appA=$addApplication('ASSIGNED','submitted',$actors['assigned']['id']);
$appB=$addApplication('OTHER','submitted',$actors['unassigned']['id']);
$memberApp=$addApplication('MEMBER','approved',null);
$verificationCode=hash('sha256',random_bytes(32));
$q=$pdo->prepare("INSERT INTO members
  (application_id,user_id,student_number,full_name,email,membership_type,verification_token_hash)
  VALUES(?,?,?,?,?,'general',?)");
$q->execute([$memberApp,$actors['member']['id'],'UAT-M-'.$suffix,
    'Phase10 Synthetic General Member',$actors['member']['email'],$verificationCode]);
$memberId=(int)$pdo->lastInsertId();

$wA=Welfare::submit([
    'reporter_name'=>'Phase10 Student Assigned',
    'reporter_email'=>'phase10-welfare-a-'.$suffix.'@example.invalid',
    'category'=>'Academic',
    'summary'=>'Phase10 Private Welfare CASE-A',
    'details'=>'PHASE10_CONFIDENTIAL_WELFARE_A: synthetic and strictly assigned to one reviewer.',
    'privacy_consent'=>'yes',
]);
$wB=Welfare::submit([
    'reporter_name'=>'Phase10 Student Unassigned',
    'reporter_email'=>'phase10-welfare-b-'.$suffix.'@example.invalid',
    'category'=>'Community Concerns',
    'summary'=>'Phase10 Private Welfare CASE-B',
    'details'=>'PHASE10_CONFIDENTIAL_WELFARE_B: synthetic and not available to assigned reviewer A.',
    'privacy_consent'=>'yes',
]);
$q=$pdo->prepare('SELECT id FROM welfare_cases WHERE reference_code=?');
$q->execute([$wA['reference']]);$caseA=(int)$q->fetchColumn();
$q->execute([$wB['reference']]);$caseB=(int)$q->fetchColumn();
Welfare::assign($caseA,['role'=>'msw_head','id'=>$actors['assigned']['id']],
    (int)$actors['assigned']['id'],'normal');
// Use a synthetic MSW Head ID for the audit actor, not a mismatched reviewer.
$headRow=$pdo->query("SELECT id FROM users WHERE role='msw_head' AND is_active=1 ORDER BY id LIMIT 1")->fetch();
if(!$headRow)throw new RuntimeException('Existing seeded synthetic MSW Head fixture missing.');
$pdo->prepare('UPDATE welfare_cases SET assigned_to=? WHERE id=?')->execute([
    (int)$actors['unassigned']['id'],$caseB
]);

$postId=Content::draft($actors['editor'],[
    'title'=>'Phase10 Editorial Draft Review',
    'content_type'=>'announcement',
    'body'=>'This is a synthetic editorial draft requiring publication authorization before public visibility.'
]);
Content::changeStatus($postId,$actors['editor'],'review');

$file=[
    'users'=>$actors,
    'applications'=>['a'=>$appA,'b'=>$appB],
    'welfare'=>[
      'a'=>$caseA,'b'=>$caseB,
      'reference'=>$wA['reference'],'token'=>$wA['tracking_token']
    ],
    'member'=>['id'=>$memberId,'verification_code'=>$verificationCode],
    'editor_post'=>$postId,
];
if(file_put_contents($destination,json_encode($file,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT))===false)
    throw new RuntimeException('Unable to save private synthetic acceptance fixture.');
chmod($destination,0600);
echo "Phase10 synthetic acceptance users and scoped records seeded.\n";
