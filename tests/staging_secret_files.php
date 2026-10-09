<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';

function assertStageSecret(bool $yes,string $label):void {
    if(!$yes){fwrite(STDERR,"FAIL: $label\n");exit(1);}
    echo "PASS: $label\n";
}
function assertReject(callable $fn,string $label):void {
    try {$fn();}catch(RuntimeException $e){echo "PASS: $label\n";return;}
    fwrite(STDERR,"FAIL: $label\n");exit(1);
}
$key = 'MFA_KEY_B64';
$prior = getenv($key);
$priorFile = getenv($key.'_FILE');
$secret = tempnam(sys_get_temp_dir(),'agile-stage-secret-');
if($secret===false)throw new RuntimeException('Cannot make synthetic secret fixture.');
chmod($secret,0600);
$value=base64_encode(random_bytes(32));
file_put_contents($secret,$value."\n");
try{
    putenv($key);putenv($key.'_FILE='.$secret);
    assertStageSecret(envValue($key)===$value,
        'MFA staging encryption secret resolves from protected file with final newline');
    $fallback = 'DEFAULT_ONLY';
    assertStageSecret(envValue('STAGING_UNKNOWN_FILE_KEY',$fallback)===$fallback,
        'Arbitrary environment names cannot read a _FILE path');

    putenv($key.'=plaintext-override');
    assertReject(fn()=>envValue($key),'Conflicting plain environment and file secrets are blocked');
    putenv($key);
    file_put_contents($secret,'');
    assertReject(fn()=>envValue($key),'Empty secret file fails closed');
    file_put_contents($secret,str_repeat('a',4097));
    assertReject(fn()=>envValue($key),'Oversize secret file fails closed');
    file_put_contents($secret,$value);
    putenv($key.'_FILE='.$secret.'-missing');
    assertReject(fn()=>envValue($key),'Unavailable secret file fails closed');
    putenv($key.'_FILE='.$secret);
    $link=$secret.'-link';
    if(symlink($secret,$link)){
        putenv($key.'_FILE='.$link);
        assertReject(fn()=>envValue($key),'Symlink secret file is not followed');
        unlink($link);
    }
    echo "PASS: file-backed staging secret regression suite.\n";
}finally{
    if($prior===false)putenv($key);else putenv($key.'='.$prior);
    if($priorFile===false)putenv($key.'_FILE');else putenv($key.'_FILE='.$priorFile);
    @unlink($secret);
}
