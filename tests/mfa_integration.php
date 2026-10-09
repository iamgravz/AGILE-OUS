<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli'||getenv('RUN_E2E_TESTS')!=='yes'||getenv('CI')!=='true'){
    fwrite(STDERR,"MFA integration is restricted to isolated CI MySQL.\n");exit(1);
}
require dirname(__DIR__).'/app/bootstrap.php';
use Agile\Mfa;
function assertion(bool $ok,string $name):void{
    if(!$ok){fwrite(STDERR,"FAIL: $name\n");exit(1);}
    echo "PASS: $name\n";
}
function forbidden(callable $fn,string $name):void{
    try{$fn();}catch(DomainException $e){echo "PASS: $name\n";return;}
    fwrite(STDERR,"FAIL: $name unexpectedly allowed\n");exit(1);
}
$pdo=db();$tag=bin2hex(random_bytes(7));
$pwd='SyntheticMFAAccount!2026';
$actors=[];
foreach(['msw_head','member'] as $role){
    $email='mfa-'.$role.'-'.$tag.'@example.invalid';
    $q=$pdo->prepare('INSERT INTO users(email,display_name,password_hash,role) VALUES (?,?,?,?)');
    $q->execute([$email,'Synthetic MFA '.$role,password_hash($pwd,PASSWORD_DEFAULT),$role]);
    $actors[$role]=['id'=>(int)$pdo->lastInsertId(),'role'=>$role,'email'=>$email];
}
$staff=$actors['msw_head'];$ordinary=$actors['member'];
assertion(Mfa::required($staff),'Privileged staff account requires MFA');
assertion(!Mfa::required($ordinary),'Ordinary member is not required to enroll in privileged MFA');
forbidden(fn()=>Mfa::begin($ordinary,$pwd),'Ordinary member cannot start privileged enrollment');
forbidden(fn()=>Mfa::begin($staff,'incorrect password'),'Password re-authentication required for MFA enrollment');
$secret=Mfa::begin($staff,$pwd);
assertion(strlen($secret)===32,'Authenticator enrollment secret is sufficiently random');
$q=$pdo->prepare('SELECT secret_ciphertext,confirmed_at FROM mfa_credentials WHERE user_id=?');
$q->execute([(int)$staff['id']]);$record=$q->fetch();
assertion($record['confirmed_at']===null && !str_contains($record['secret_ciphertext'],$secret),
    'MFA pending secret stored encrypted, not plaintext');
assertion(!Mfa::enrolled((int)$staff['id']),'Unconfirmed MFA secret is not active');
forbidden(fn()=>Mfa::confirm($staff,'000000x'),'Malformed code refused');
$correct=Mfa::totp($secret,intdiv(time(),30));
$recovery=Mfa::confirm($staff,$correct);
assertion(count($recovery)===8 && count(array_unique($recovery))===8,
    'MFA enrollment creates eight unique recovery codes');
assertion(Mfa::enrolled((int)$staff['id']),'MFA confirmed and enabled in database');
assertion(Mfa::sessionVerified($staff),'Session authorized immediately after successful setup');
startSession();
$_SESSION['mfa_ok_uid']=0; // Simulate a new login without second factor.
assertion(!Mfa::sessionVerified($staff),'Password-only session is not MFA-authorized');
assertion(!Mfa::challenge($staff,$correct),'Used enrollment TOTP is blocked by monotonic counter');
assertion(Mfa::challenge($staff,$recovery[0]),'One-time recovery code allows a secure session');
$_SESSION['mfa_ok_uid']=0;
assertion(!Mfa::challenge($staff,$recovery[0]),'Recovery code cannot be reused');
$q=$pdo->prepare('SELECT used_at FROM mfa_recovery_codes WHERE user_id=? AND code_hash=?');
$q->execute([(int)$staff['id'],hash('sha256',$recovery[0])]);
assertion($q->fetchColumn()!==null,'Recovery code usage is persisted');
$unusedCount=$pdo->prepare('SELECT COUNT(*) FROM mfa_recovery_codes WHERE user_id=? AND used_at IS NULL');
$unusedCount->execute([(int)$staff['id']]);
assertion((int)$unusedCount->fetchColumn()===7,'Exactly one recovery code consumed');
$wrong='INCORRECT-OTP';
for($i=0;$i<4;$i++)assertion(!Mfa::challenge($staff,$wrong),'Invalid MFA attempt '.$i.' is denied');
forbidden(fn()=>Mfa::challenge($staff,$recovery[1]),
    'MFA account locks challenge after repeated incorrect attempts');
assertion(!Mfa::sessionVerified($staff),'Locked account does not get a privileged session');
echo "Privileged MFA lifecycle and anti-replay MySQL tests passed.\n";
