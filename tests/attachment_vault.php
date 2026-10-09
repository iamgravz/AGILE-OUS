<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';

use Agile\AttachmentVault;

function vaultOk(bool $ok,string $desc):void{
    if(!$ok){fwrite(STDERR,"FAIL: $desc\n");exit(1);}
    echo "PASS: $desc\n";
}
function vaultThrows(callable $fn,string $desc):void{
    try{$fn();}catch(\Throwable $e){echo "PASS: $desc\n";return;}
    fwrite(STDERR,"FAIL: $desc\n");exit(1);
}
$base=sys_get_temp_dir().'/agile-vault-'.bin2hex(random_bytes(6));
$source=$base.'/private';$target=$base.'/agile_restore_files_ci_'.bin2hex(random_bytes(3));
mkdir($base,0700,true);mkdir($source,0700);mkdir($target,0700);
$key=random_bytes(SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES);
$one=bin2hex(random_bytes(20)).'.pdf';
$two=bin2hex(random_bytes(20)).'.png';
$first="%PDF-1.4\n".str_repeat('Synthetic confidential academic data; ',800);
$second="\x89PNG\r\n\x1a\n".str_repeat("Synthetic only",1250);
file_put_contents($source.'/'.$one,$first);file_put_contents($source.'/'.$two,$second);
$encrypted=$base.'/files.afb';
$sum=AttachmentVault::create($source,$encrypted,$key);
vaultOk($sum['files']===2&&$sum['bytes']===strlen($first)+strlen($second),
   'Authenticated private-file archive includes all two synthetic documents');
$raw=file_get_contents($encrypted);
vaultOk(!str_contains($raw,'Synthetic confidential academic data'),
   'Encrypted archive does not expose plaintext academic content');
vaultOk(AttachmentVault::inspect($encrypted,$key)===$sum,
   'Entire encrypted private archive verifies with file-level hashes');
vaultThrows(fn()=>AttachmentVault::inspect($encrypted,random_bytes(32)),
   'Incorrect key rejected');
$copy=$base.'/tampered.afb';$broken=$raw;$broken[80]=chr(ord($broken[80])^1);
file_put_contents($copy,$broken);
vaultThrows(fn()=>AttachmentVault::inspect($copy,$key),
   'Tampered archive rejected before restore');
file_put_contents($copy,substr($raw,0,-9));
vaultThrows(fn()=>AttachmentVault::inspect($copy,$key),
   'Truncated private archive rejected');
$restored=AttachmentVault::inspect($encrypted,$key,$target);
vaultOk($restored===$sum,'Archive restored only into empty isolated target');
vaultOk(file_get_contents($target.'/'.$one)===$first&&file_get_contents($target.'/'.$two)===$second,
   'Private-file restore preserves exact content');
vaultThrows(fn()=>AttachmentVault::inspect($encrypted,$key,$target),
   'Restore refuses non-empty target and cannot overwrite documents');
file_put_contents($source.'/unexpected-backup.txt','not a recognized private attachment');
vaultThrows(fn()=>AttachmentVault::create($source,$base.'/refused.afb',$key),
   'Backup refuses unexpected private storage files instead of skipping them');
foreach([$source.'/'.$one,$source.'/'.$two,$source.'/unexpected-backup.txt',
        $target.'/'.$one,$target.'/'.$two,$copy,$encrypted] as $file)@unlink($file);
rmdir($source);rmdir($target);rmdir($base);
echo "Private-file authenticated backup and recovery tests passed.\n";
