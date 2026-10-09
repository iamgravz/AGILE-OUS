<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('RUN_E2E_TESTS')!=='yes'||getenv('CI')!=='true'){
    fwrite(STDERR,"Scanner test runs only with disposable synthetic CI data.\n");
    exit(1);
}
putenv('APP_ENV=testing');
putenv('FILE_SCAN_DRIVER=synthetic');
require dirname(__DIR__).'/app/bootstrap.php';

use Agile\AttachmentScanner;
use Agile\Attachments;

function scanOk(bool $truth,string $message):void{
    if(!$truth){fwrite(STDERR,"FAIL: $message\n");exit(1);}
    echo "PASS: $message\n";
}
function scanDenied(callable $fn,string $message):void{
    try{$fn();}catch(\DomainException|\RuntimeException $e){echo "PASS: $message\n";return;}
    fwrite(STDERR,"FAIL: $message\n");exit(1);
}
$pdo=db();
$dir=dirname(__DIR__).'/storage/private';
if(!is_dir($dir))mkdir($dir,0700,true);
$base=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg==');
scanOk((new finfo(FILEINFO_MIME_TYPE))->buffer($base)==='image/png',
    'Synthetic file has a recognized image MIME signature');
$staffEmail='scan-test-'.bin2hex(random_bytes(5)).'@example.invalid';
$pdo->prepare("INSERT INTO users(email,display_name,password_hash,role)
   VALUES(?,'Synthetic MSW Scanner Reviewer',?,'msw_head')")
   ->execute([$staffEmail,password_hash('SyntheticScannerPassword!2026',PASSWORD_DEFAULT)]);
$head=['id'=>(int)$pdo->lastInsertId(),'role'=>'msw_head'];
$make=function(string $suffix)use($pdo,$dir,$base,$head):array{
    $name=bin2hex(random_bytes(20));
    $bytes=$base.$suffix;
    $path=$dir.'/'.$name.'.png';
    file_put_contents($path,$bytes);
    $q=$pdo->prepare("INSERT INTO private_attachments
      (storage_key,owner_type,owner_id,original_name,mime_type,byte_size,uploaded_by)
      VALUES (?,'academic_verification',9999999,'synthetic.png','image/png',?,?)");
    $q->execute([$name,strlen($bytes),(int)$head['id']]);
    return ['id'=>(int)$pdo->lastInsertId(),'path'=>$path];
};
$clean=$make('');
scanDenied(fn()=>Attachments::authorizeDownload($clean['id'],$head),
    'Quarantine blocks access before malware scanning');
scanOk(AttachmentScanner::scanOne($clean['id'])==='clean',
    'Approved synthetic test scanner clears matching benign file');
$allowed=Attachments::authorizeDownload($clean['id'],$head);
scanOk($allowed['scan_status']==='clean'&&strlen($allowed['content_sha256'])===64,
    'Only clean hash-verified private file is authorized for download');
file_put_contents($clean['path'],'changed after scan',FILE_APPEND);
scanDenied(fn()=>Attachments::authorizeDownload($clean['id'],$head),
    'File changed after scanning is blocked by digest integrity verification');

$infected=$make('AGILE_SYNTHETIC_INFECTED_MARKER');
scanOk(AttachmentScanner::scanOne($infected['id'])==='infected',
    'Synthetic malware signature gets infected result');
scanDenied(fn()=>Attachments::authorizeDownload($infected['id'],$head),
    'Infected document may never be downloaded');
$q=$pdo->prepare("SELECT COUNT(*) FROM security_events
    WHERE resource_type='private_attachment' AND resource_id=? AND severity='critical'");
$q->execute([$infected['id']]);
scanOk((int)$q->fetchColumn()===1,'Malware finding is recorded in security-event audit');

$err=$make('');
putenv('FILE_SCAN_DRIVER=disabled');
scanOk(AttachmentScanner::scanOne($err['id'])==='scan_error',
    'Missing scanner produces scan_error, never auto-clean');
scanDenied(fn()=>Attachments::authorizeDownload($err['id'],$head),
    'Scanner failure leaves document quarantined from users');
$pending=$make('');
scanDenied(fn()=>Attachments::authorizeDownload($pending['id'],$head),
    'Legacy/new pending file cannot be served without scanner clearance');
foreach([$clean,$infected,$err,$pending] as $item)@unlink($item['path']);
echo "Quarantine state, malware flag and file integrity CI tests passed.\n";
