<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
require dirname(__DIR__).'/app/bootstrap.php';

// Never run test/acceptance inspection against real student data by mistake.
// The query results are only aggregate counts, but this command is
// intentionally restricted to staging or an explicit disposable CI DB.
$disposable=\envValue('CI')==='true' && \envValue('RUN_E2E_TESTS')==='yes';
$staging=\envValue('APP_ENV')==='staging';
if(!$disposable && !$staging){
    fwrite(STDERR,"Acceptance audit restricted to authorized staging or isolated CI.\n");
    exit(2);
}
try{
    $report=\Agile\AcceptanceAudit::audit(\db());
    echo json_encode($report,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT)."\n";
    exit($report['failed']===0?0:1);
}catch(\Throwable $e){
    fwrite(STDERR,"Acceptance audit failed safely (".get_class($e).").\n");
    exit(1);
}
