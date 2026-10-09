<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

use Agile\Membership;
use Agile\ApplicationWorkflow;

function assertWorkflow(bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
    echo "PASS: $message\n";
}
function expectedDenial(callable $callback, string $message): void {
    try { $callback(); } catch (DomainException $e) {
        echo "PASS: $message (" . $e->getMessage() . ")\n"; return;
    }
    fwrite(STDERR,"FAIL: $message (operation unexpectedly allowed)\n"); exit(1);
}

$pdo = db();
$suffix = bin2hex(random_bytes(5));
$userIds = [];
$appId = null;
try {
    foreach (['msw_head','msw_member','msw_member'] as $idx=>$role) {
        $email = "staff-$suffix-$idx@example.invalid";
        $q=$pdo->prepare('INSERT INTO users(email, display_name, password_hash, role) VALUES(?,?,?,?)');
        $q->execute([$email,"Synthetic Reviewer $idx",password_hash('SyntheticPasswordOnly2026!',PASSWORD_DEFAULT),$role]);
        $userIds[]=(int)$pdo->lastInsertId();
    }
    $head = ['id'=>$userIds[0], 'role'=>'msw_head'];
    $member = ['id'=>$userIds[1], 'role'=>'msw_member'];
    $outsider = ['id'=>$userIds[2], 'role'=>'msw_member'];
    $reference=Membership::submit([
        'full_name'=>'Synthetic Editorial Applicant',
        'email'=>"app-$suffix@example.invalid",
        'student_number'=>"TEST-$suffix",
        'desired_role'=>'The Source Code',
        'motivation'=>'I can contribute editorial and multimedia experience.',
        'privacy_consent'=>'yes',
        'answers'=>[
            'publication_position'=>'Writer',
            'publication_experience'=>'Synthetic writing and editorial experience for test.',
            'portfolio_url'=>'https://example.org/portfolio',
        ],
    ]);
    $q=$pdo->prepare('SELECT id FROM membership_applications WHERE reference_code = ?');
    $q->execute([$reference]);$appId=(int)$q->fetchColumn();
    assertWorkflow($appId>0,'Source Code application saved');

    expectedDenial(fn()=>ApplicationWorkflow::findForActor($appId,$member),'Unassigned member cannot view application');
    ApplicationWorkflow::assignReviewer($appId,$head,$member['id']);
    assertWorkflow((int)ApplicationWorkflow::findForActor($appId,$member)['id']===$appId,'Assigned MSW member can view application');
    expectedDenial(fn()=>ApplicationWorkflow::findForActor($appId,$outsider),'Other MSW member cannot view application');
    expectedDenial(fn()=>ApplicationWorkflow::changeStatus($appId,$outsider,'screening','Reviewing the application'),'Other MSW member cannot update application');

    ApplicationWorkflow::changeStatus($appId,$member,'screening','Initial screening started');
    ApplicationWorkflow::changeStatus($appId,$member,'for_interview','Interview invitation scheduled');
    ApplicationWorkflow::changeStatus($appId,$member,'for_verification','Proceeding to document review');
    expectedDenial(fn()=>ApplicationWorkflow::changeStatus($appId,$member,'approved','I approved this person'),'Committee member cannot finalize');
    expectedDenial(fn()=>ApplicationWorkflow::changeStatus($appId,$head,'approved','Approved membership application'),'Approval blocked without interview and documents');

    ApplicationWorkflow::updateVerification($appId,$head,true,true,'Verified synthetic interview and supporting documentation');
    $q=$pdo->prepare('SELECT COUNT(*) FROM application_verification_history WHERE application_id=? AND actor_user_id=? AND note LIKE ?');
    $q->execute([$appId,$head['id'],'Verified synthetic%']);
    assertWorkflow((int)$q->fetchColumn()===1,'Verification outcome, actor and note persisted');
    ApplicationWorkflow::changeStatus($appId,$head,'approved','Authorized decision after verified synthetic requirements');
    assertWorkflow(ApplicationWorkflow::find($appId)['status']==='approved','MSW Head finalized verified application');
    expectedDenial(fn()=>ApplicationWorkflow::changeStatus($appId,$head,'screening','Attempt to reopen finalized'),'Finalized application cannot be reopened');

    $q=$pdo->prepare('SELECT COUNT(*) FROM application_status_history WHERE application_id = ?');
    $q->execute([$appId]);
    assertWorkflow((int)$q->fetchColumn()===4,'Every status transition recorded in history');
    $q=$pdo->prepare('SELECT COUNT(*) FROM application_answers WHERE application_id = ?');
    $q->execute([$appId]);
    assertWorkflow((int)$q->fetchColumn()===3,'Publication position, experience and portfolio retained');
    echo "Workflow integration tests passed. Synthetic-only records.\n";
} finally {
    if ($appId !== null) {
        $pdo->prepare("DELETE FROM audit_logs WHERE entity_type='membership_application' AND entity_id = ?")->execute([$appId]);
        $pdo->prepare('DELETE FROM application_verification_history WHERE application_id = ?')->execute([$appId]);
        $pdo->prepare('DELETE FROM application_status_history WHERE application_id = ?')->execute([$appId]);
        $pdo->prepare('DELETE FROM application_answers WHERE application_id = ?')->execute([$appId]);
        $pdo->prepare('DELETE FROM membership_applications WHERE id = ?')->execute([$appId]);
    }
    foreach ($userIds as $userId) {
        $pdo->prepare('DELETE FROM audit_logs WHERE actor_user_id = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
    }
}
