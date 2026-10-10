<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/src/bootstrap.php';
$errors=[];
if (PHP_VERSION_ID < 80200) { $errors[]='PHP 8.2+ required'; }
foreach(['pdo_mysql','openssl'] as $ext) {
    if(!extension_loaded($ext)){$errors[]='Missing required extension: '.$ext;}
}
if(strlen(getenv('APP_KEY') ?: '')<32){$errors[]='APP_KEY missing or too short';}
if(!preg_match('/^[0-9a-f]{64}$/i',getenv('WELFARE_ENCRYPTION_KEY') ?: '')){
    $errors[]='WELFARE_ENCRYPTION_KEY must be 64 hex characters';
}
if(getenv('APP_ENV')==='production' && getenv('SESSION_SECURE')!=='true'){
    $errors[]='Production requires SESSION_SECURE=true';
}
if(getenv('APPLICATIONS_OPEN')==='true'){
    $errors[]='Public applications enabled: require privacy and operations sign-off';
}
try{
    $pdo=db();
    $expected=['users','membership_applications','recruitment_positions',
      'recruitment_notification_drafts','notification_outbox','welfare_cases',
      'welfare_case_events','public_submission_attempts','audit_events'];
    foreach($expected as $table){
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
        $stmt->execute([$table]);
        if((int)$stmt->fetchColumn()!==1){$errors[]='Missing database table: '.$table;}
    }
}catch(Throwable $e){$errors[]='Database connection or schema check failed';}
if($errors){
    foreach($errors as $error){fwrite(STDERR,"[BLOCKER] $error\n");}
    exit(1);
}
echo "Preflight checks passed (not a production security certification).\n";
