<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CI')!=='true'||getenv('RUN_E2E_TESTS')!=='yes') {
    fwrite(STDERR,"Synthetic UAT seeding is permitted in isolated CI only.\n");
    exit(2);
}
require dirname(__DIR__).'/app/bootstrap.php';

use Agile\Mfa;

$file=getenv('UAT_CREDENTIALS_FILE');
$password=getenv('HTTP_TEST_PASSWORD');
if(!$file || !$password || strlen($password)<12){
    fwrite(STDERR,"Missing isolated CI fixture parameters.\n");
    exit(2);
}
$pdo=db();
$lines=[];
foreach(['msw_head','msw_member','president','admin','member'] as $role){
    $email='uat-'.$role.'@example.invalid';
    $q=$pdo->prepare('INSERT INTO users(email,display_name,password_hash,role) VALUES(?,?,?,?)');
    $q->execute([$email,'UAT Synthetic '.$role,password_hash($password,PASSWORD_DEFAULT),$role]);
    $id=(int)$pdo->lastInsertId();
    $user=['id'=>$id,'role'=>$role,'email'=>$email];
    $recovery='-';
    if(Mfa::required($user)){
        $secret=Mfa::begin($user,$password);
        $codes=Mfa::confirm($user,Mfa::totp($secret,intdiv(time(),30)));
        // Ephemeral GitHub runner file, used once in the end-to-end browser check.
        $recovery=$codes[0];
    }
    $lines[]=$role.'|'.$email.'|'.$recovery;
}
$q=$pdo->query('SELECT id FROM membership_applications ORDER BY id LIMIT 1');
$appId=(int)$q->fetchColumn();
if($appId<1)throw new RuntimeException('Synthetic application fixtures not found.');
$lines[]='unassigned_application|'.$appId.'|-';
if(file_put_contents($file,implode("\n",$lines)."\n",LOCK_EX)===false)
    throw new RuntimeException('Could not write ephemeral UAT fixture file.');
chmod($file,0600);
echo "PASS: synthetic UAT accounts and private runner-only MFA recovery fixture created.\n";
