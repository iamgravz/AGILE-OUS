<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
require dirname(__DIR__).'/app/bootstrap.php';

if(\envValue('APP_ENV')!=='staging' || \envValue('AGILE_UAT_READ_ONLY')!=='true'){
    fwrite(STDERR,"Staging handoff requires authorized APP_ENV=staging and AGILE_UAT_READ_ONLY=true.\n");
    exit(2);
}
try{
    $issues=\Agile\StagingPreflight::inspectEnvironment();
    if($issues!==[]){
        $evidence=\Agile\ReleaseReadiness::summarize($issues,[],[]);
    } else {
        // Read-only transaction protects the database even if a future report
        // accidentally introduces writes. Never run this command against live records.
        $pdo=\db();
        $pdo->exec('SET TRANSACTION READ ONLY');
        $pdo->beginTransaction();
        try{
            $accept=\Agile\AcceptanceReport::collect($pdo,'staging_read_only');
            $integrity=\Agile\AcceptanceAudit::audit($pdo);
            $pdo->rollBack();
        }catch(\Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }
        $evidence=\Agile\ReleaseReadiness::summarize([],$accept,$integrity);
    }
    echo json_encode($evidence,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    exit($evidence['technical_checks_passed']?0:1);
}catch(\Throwable $e){
    // Do not expose connection strings, SQL, secrets, or personal information.
    fwrite(STDERR,"Staging handoff evaluation failed: ".get_class($e)."\n");
    exit(1);
}
