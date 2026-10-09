<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CI')!=='true'||getenv('RUN_E2E_TESTS')!=='yes'){
    fwrite(STDERR,"Only disposable synthetic CI databases are allowed.\n");exit(2);
}
require dirname(__DIR__).'/app/bootstrap.php';

use Agile\AcceptanceAudit;

function requireGate(bool $yes,string $name):void {
    if(!$yes){fwrite(STDERR,"FAIL: $name\n");exit(1);}
    echo "PASS: $name\n";
}
function violationsFor(array $audit,string $gate):int {
    foreach($audit['checks'] as $check){
        if($check['name']===$gate)return (int)$check['violations'];
    }
    throw new RuntimeException('Unknown acceptance check: '.$gate);
}
$pdo=db();
requireGate(AcceptanceAudit::audit($pdo)['failed']===0,
    'Untampered synthetic database passes acceptance audit');

function tamperTest(PDO $pdo,callable $write,string $gate,string $label):void {
    $pdo->beginTransaction();
    try{
        $write($pdo);
        $report=AcceptanceAudit::audit($pdo);
        if(violationsFor($report,$gate)<1)
            throw new RuntimeException('Acceptance checker did not detect simulated violation: '.$label);
        echo "PASS: acceptance catches ".$label."\n";
    }finally{
        if($pdo->inTransaction())$pdo->rollBack();
    }
    requireGate(AcceptanceAudit::audit($pdo)['failed']===0,
        'Synthetic database restored after rollback-only '.$label.' test');
}

tamperTest($pdo,function(PDO $db):void{
    $db->exec("UPDATE eligibility_policies SET is_approved=1
       WHERE policy_version='DRAFT-2026-09-24-ARTICLE-VI-2'");
},'Original unratified bylaws policy is not approved',
 'accidentally ratified source-draft policy');

tamperTest($pdo,function(PDO $db):void{
    $q=$db->query('SELECT id,filled FROM vacancies ORDER BY id LIMIT 1 FOR UPDATE');
    $v=$q->fetch();
    if(!$v)throw new RuntimeException('Expected synthetic vacancy fixtures.');
    $next=(int)$v['filled']===0?1:0;
    $db->prepare('UPDATE vacancies SET filled=? WHERE id=?')->execute([$next,(int)$v['id']]);
},'Vacancy fill counts agree with active appointed-role records',
 'vacancy capacity accounting mismatch');

tamperTest($pdo,function(PDO $db):void{
    $q=$db->query("SELECT id FROM private_attachments ORDER BY id LIMIT 1 FOR UPDATE");
    $id=$q->fetchColumn();
    if(!$id)throw new RuntimeException('Expected synthetic private attachment fixture.');
    $db->prepare("UPDATE private_attachments SET scan_status='clean',content_sha256=NULL WHERE id=?")
      ->execute([(int)$id]);
},'Every clean private document has a verified SHA-256 digest',
 'clean document without digest');

echo "PASS: negative integrity gates detect corruption without persisting any mutations.\n";
