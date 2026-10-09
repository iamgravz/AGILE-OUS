<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use Agile\StagingPreflight;

function stageOk(bool $ok,string $name):void{
    if(!$ok){fwrite(STDERR,"FAIL: $name\n");exit(1);}
    echo "PASS: $name\n";
}
$good=[
 'APP_ENV'=>'staging','APP_URL'=>'https://staging.example.invalid',
 'SESSION_SECURE'=>'true',
 'MFA_KEY_B64'=>base64_encode(random_bytes(32)),
 'AGILE_BACKUP_KEY_B64'=>base64_encode(random_bytes(32)),
 'AGILE_FILE_BACKUP_KEY_B64'=>base64_encode(random_bytes(32)),
 'FILE_SCANNING_ENABLED'=>'true','FILE_SCAN_DRIVER'=>'clamav',
 'MAIL_TRANSPORT'=>'disabled','AI_EXTERNAL_PROCESSING_APPROVED'=>'false',
 'ACADEMIC_ROLE_TRANSITIONS_ENABLED'=>'false','AUTOMATION_ENABLED'=>'false',
 'AGILE_ALLOW_ISOLATED_RESTORE'=>'false'
];
stageOk(StagingPreflight::validate($good)===[],'Approved synthetic staging configuration passes');
$unsafe=$good;$unsafe['APP_URL']='http://example.invalid';
$unsafe['SESSION_SECURE']='false';$unsafe['FILE_SCAN_DRIVER']='synthetic';
$unsafe['ACADEMIC_ROLE_TRANSITIONS_ENABLED']='true';
$issues=StagingPreflight::validate($unsafe);
stageOk(count($issues)>=4,'Insecure TLS, scanner and role enforcement settings are blocked');
$unsafe=$good;$unsafe['AGILE_FILE_BACKUP_KEY_B64']='not a key';
stageOk(count(StagingPreflight::validate($unsafe))>=1,'Missing confidential document backup key blocks stage readiness');
echo "Staging security configuration validation passed.\n";
