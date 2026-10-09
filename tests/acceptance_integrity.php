<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli' || getenv('RUN_E2E_TESTS')!=='yes' || getenv('CI')!=='true'){
    fwrite(STDERR,"Only disposable synthetic CI databases are allowed.\n");
    exit(2);
}
require dirname(__DIR__).'/app/bootstrap.php';
use Agile\AcceptanceAudit;

$report=AcceptanceAudit::audit(db());
foreach($report['checks'] as $check){
    echo ($check['passed']?'PASS: ':'FAIL: ').$check['name']
        .' (violations: '.$check['violations'].")\n";
}
if($report['failed']!==0){
    fwrite(STDERR,"FAIL: cross-module integrity acceptance. Do not label the build staging ready.\n");
    exit(1);
}
echo "PASS: all ".count($report['checks'])." aggregate cross-module integrity gates.\n";
// Assert the report never includes any private student content or identifiers.
$json=json_encode($report,JSON_THROW_ON_ERROR);
foreach(['email','student_number','reporter_name','password_hash','tracking_token','declared_grades_json','private_note'] as $privateKey){
    if(str_contains($json,'"'.$privateKey.'"')){
        fwrite(STDERR,"FAIL: acceptance report included confidential field key.\n");exit(1);
    }
}
echo "PASS: acceptance report contains aggregate gate summaries only.\n";
