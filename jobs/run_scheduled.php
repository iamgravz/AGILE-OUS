<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
require dirname(__DIR__).'/app/bootstrap.php';
if(\envValue('AUTOMATION_ENABLED','false')!=='true'){fwrite(STDOUT,"Scheduler disabled by default.\n");exit(0);}
$result=\Agile\Automation::runDue();
echo json_encode($result,JSON_THROW_ON_ERROR)."\n";
