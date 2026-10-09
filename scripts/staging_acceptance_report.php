<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(403);exit('CLI only');}
require dirname(__DIR__).'/app/bootstrap.php';

$ci=getenv('CI')==='true' && getenv('RUN_E2E_TESTS')==='yes';
$staging=envValue('APP_ENV')==='staging' &&
    envValue('AGILE_UAT_READ_ONLY')==='true';
if(!$ci&&!$staging){
    fwrite(STDERR,"Refusing acceptance database access outside authorized read-only staging or isolated CI.\n");
    exit(2);
}
$mode=$ci?'synthetic_ci':'staging_read_only';
try {
    $report=\Agile\AcceptanceReport::collect(db(),$mode);
    echo json_encode($report,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
    if(!$report['all_schema_invariants_passed'])exit(1);
} catch(\Throwable $e) {
    // Never leak student data, connection strings or private SQL to CLI logs.
    fwrite(STDERR,"Read-only acceptance report failed: ".get_class($e)."\n");
    exit(1);
}
