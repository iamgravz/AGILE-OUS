<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
require dirname(__DIR__).'/app/bootstrap.php';

if(\envValue('FILE_SCANNING_ENABLED','false')!=='true'){
    fwrite(STDERR,"Malware scanner worker is disabled until the operator enables it.\n");
    exit(2);
}
try{
    $status=\Agile\AttachmentScanner::scanBatch(20);
    echo json_encode($status,JSON_THROW_ON_ERROR)."\n";
}catch(\Throwable $e){
    fwrite(STDERR,"Private document scanner could not complete: ".get_class($e)."\n");
    exit(1);
}
