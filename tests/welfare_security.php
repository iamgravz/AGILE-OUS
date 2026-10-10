<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/welfare_security.php';
putenv('WELFARE_ENCRYPTION_KEY=' . bin2hex(random_bytes(32)));
$original = "Confidential example—synthetic data only!";
$encrypted = encryptWelfare($original);
if ($encrypted === $original || decryptWelfare($encrypted) !== $original) {
    fwrite(STDERR, "Encryption round-trip failed\n"); exit(1);
}
$case = ['created_by'=>100,'assigned_to'=>101];
$checks = [
 [['id'=>100,'role'=>'welfare_member'],true],
 [['id'=>101,'role'=>'welfare_member'],true],
 [['id'=>102,'role'=>'welfare_member'],false],
 [['id'=>102,'role'=>'president'],false],
 [['id'=>102,'role'=>'membership_head'],false],
 [['id'=>102,'role'=>'welfare_head'],true]
];
foreach ($checks as [$user,$want]) {
 if (canReadWelfareCase($user,$case) !== $want) {
    fwrite(STDERR, "Welfare case access test failed\n");exit(1);
 }
}
if (count(welfareCategories()) !== 8) {
    fwrite(STDERR, "Welfare categories regression\n");exit(1);
}
echo "Welfare encryption and access tests passed\n";
