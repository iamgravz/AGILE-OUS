<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
require dirname(__DIR__).'/app/bootstrap.php';
if(\envValue('SECURITY_MONITOR_ENABLED','false')!=='true'){
    fwrite(STDOUT,"Security report disabled by default.\n");
    exit(0);
}
$q=\db()->query("SELECT event_key,severity,COUNT(*) AS occurrences,
    MAX(created_at) AS most_recent
    FROM security_events WHERE created_at>DATE_SUB(NOW(),INTERVAL 24 HOUR)
    GROUP BY event_key,severity ORDER BY occurrences DESC LIMIT 100");
$rows=$q->fetchAll();
foreach($rows as &$row)$row['occurrences']=(int)$row['occurrences'];
unset($row);
echo json_encode(['period'=>'previous_24_hours','events'=>$rows],
    JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
