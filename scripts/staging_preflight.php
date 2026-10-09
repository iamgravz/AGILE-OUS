<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
require dirname(__DIR__).'/app/bootstrap.php';
$issues=\Agile\StagingPreflight::inspectEnvironment();
if($issues){
    fwrite(STDERR,"AGILE OUS staging is NOT ready. Correct these blockers:\n");
    foreach($issues as $issue)fwrite(STDERR,"- ".$issue."\n");
    exit(1);
}
echo "AGILE OUS staging configuration preflight passed (config-only; not a security certification).\n";
