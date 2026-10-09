<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use Agile\Mfa;

function mfaAssert(bool $ok,string $desc):void{
    if(!$ok){fwrite(STDERR,"FAIL: $desc\n");exit(1);}
    echo "PASS: $desc\n";
}
$secret='12345678901234567890';
$b32=Mfa::toBase32($secret);
mfaAssert(Mfa::fromBase32($b32)===$secret,'Base32 encoding/decoding round-trip');
$cases=[
    [59,'287082'],            // RFC 6238 SHA-1 TOTP 8-digit 94287082, truncated to 6
    [1111111109,'081804'],    // RFC 6238 SHA-1 07081804
    [1111111111,'050471'],    // RFC 6238 SHA-1 14050471
    [1234567890,'005924'],    // RFC 6238 SHA-1 89005924
    [2000000000,'279037'],    // RFC 6238 SHA-1 69279037
    [20000000000,'353130'],   // RFC 6238 SHA-1 65353130
];
foreach($cases as [$at,$expected]){
    mfaAssert(Mfa::totp($b32,intdiv($at,30))===$expected,'RFC 6238 SHA1 6-digit code at '.$at);
}
mfaAssert(Mfa::matchingStep($b32,'287082',59)===1,'Correct TOTP recognized');
mfaAssert(Mfa::matchingStep($b32,'badotp',59)===null,'Invalid TOTP format rejected');
mfaAssert(Mfa::matchingStep($b32,'123456',59)===null,'Incorrect TOTP rejected');
echo "MFA algorithm compliance checks passed.\n";
