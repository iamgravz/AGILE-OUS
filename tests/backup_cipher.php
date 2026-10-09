<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use Agile\BackupCipher;

function assertBackup(bool $yes,string $name): void {
    if (!$yes) {fwrite(STDERR,"FAIL: $name\n");exit(1);}
    echo "PASS: $name\n";
}
$key=random_bytes(SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES);
$plain="-- synthetic backup data, no real students --\n"
      .str_repeat("INSERT INTO synthetic_test VALUES ('AGILE OUS');\n",2500);
$input=fopen('php://temp','w+b');fwrite($input,$plain);rewind($input);
$encrypted=fopen('php://temp','w+b');
assertBackup(BackupCipher::encrypt($input,$encrypted,$key)===strlen($plain),
    'Encrypted stream preserves total plaintext byte count');
rewind($encrypted);
$output=fopen('php://temp','w+b');
assertBackup(BackupCipher::decrypt($encrypted,$output,$key)===strlen($plain),
    'Authenticated backup decrypts successfully');
rewind($output);
assertBackup(stream_get_contents($output)===$plain,'Decrypted synthetic data is unchanged');

rewind($encrypted);
try {
    BackupCipher::decrypt($encrypted,null,random_bytes(32));
    assertBackup(false,'Wrong decryption key must fail');
} catch (RuntimeException $e) {echo "PASS: Incorrect key rejected\n";}

rewind($encrypted);
$data=stream_get_contents($encrypted);
$tampered=$data;
$tampered[70]=chr(ord($tampered[70])^1);
$stream=fopen('php://temp','w+b');fwrite($stream,$tampered);rewind($stream);
try {
    BackupCipher::decrypt($stream,null,$key);
    assertBackup(false,'Tampered encrypted backup must fail');
} catch (RuntimeException $e) {echo "PASS: Modified ciphertext rejected\n";}

$truncated=fopen('php://temp','w+b');
fwrite($truncated,substr($data,0,-7));rewind($truncated);
try {
    BackupCipher::decrypt($truncated,null,$key);
    assertBackup(false,'Truncated backup must fail');
} catch (RuntimeException $e) {echo "PASS: Truncated backup rejected\n";}

echo "Encrypted backup-format tests passed.\n";
