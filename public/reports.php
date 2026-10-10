<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/authorization.php';
startSecureSession();
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
$user=requirePermission('membership.view');
$year=trim((string)($_GET['year']??''));
$semester=(string)($_GET['semester']??'');
if($year!=='' && !preg_match('/^20[0-9]{2}-20[0-9]{2}$/',$year)){http_response_code(422);exit('Invalid academic year');}
if($semester!=='' && !in_array($semester,['1st','2nd','Summer'],true)){http_response_code(422);exit('Invalid semester');}
$where=[];$params=[];
if($year!==''){$where[]='academic_year=?';$params[]=$year;}
if($semester!==''){$where[]='semester=?';$params[]=$semester;}
$filter=$where?' WHERE '.implode(' AND ',$where):'';
$stmt=db()->prepare('SELECT status,COUNT(*) total FROM membership_applications'.$filter.' GROUP BY status');
$stmt->execute($params);$counts=array_fill_keys(['pending','for_interview','approved','rejected','withdrawn'],0);
foreach($stmt->fetchAll() as $r){$counts[$r['status']]=(int)$r['total'];}
$export=($_GET['export']??'')==='csv';
if($export){
  // Individual applicant exports are restricted to membership leadership only.
  if(!in_array((string)$user['role'],['admin','membership_head'],true)){http_response_code(403);exit('CSV export restricted');}
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="agile-membership-report.csv"');
  $out=fopen('php://output','wb');
  fputcsv($out,['Application Reference','Academic Year','Semester','Status','Assigned Position','Created At']);
  $stmt=db()->prepare('SELECT a.application_reference,a.academic_year,a.semester,a.status,COALESCE(p.title,\'\') position_title,a.created_at FROM membership_applications a LEFT JOIN recruitment_positions p ON p.id=a.recruitment_position_id'.
    ($where?' WHERE '.implode(' AND ',array_map(fn($field)=>'a.'.$field,$where)):'').' ORDER BY a.id DESC LIMIT 10000');
  $stmt->execute($params);
  while($r=$stmt->fetch()){
    $values=array_values($r);
    // Prevent CSV spreadsheet formula injection for untrusted text.
    foreach($values as &$v){$v=(string)$v;if(preg_match('/^[\\x00-\\x20]*[=+@\\-]/u',$v)){$v="'".$v;}}
    unset($v);
    fputcsv($out,$values);
  }
  fclose($out);exit;
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Membership reporting — AGILE OUS</title><style>body{font:16px system-ui;background:#faf8f5;margin:2rem}main{background:#fff;padding:1.5rem;max-width:850px}table{border-collapse:collapse;width:100%}td,th{border:1px solid #ddd;padding:.7rem;text-align:left}input,select,button{padding:.6rem}</style></head><body>
<main><h1>Membership Reporting</h1><p>Only membership records are included; no welfare case data.</p>
<form method="get"><label>Academic year <input name="year" value="<?=escapeHtml($year)?>" placeholder="2026-2027"></label>
<label>Semester <select name="semester"><option value="">All</option><?php foreach(['1st','2nd','Summer'] as $t):?><option value="<?=escapeHtml($t)?>" <?=$semester===$t?'selected':''?>><?=escapeHtml($t)?></option><?php endforeach;?></select></label>
<button type="submit">Filter</button>
<?php if(in_array((string)$user['role'],['admin','membership_head'],true)):?><button name="export" value="csv" type="submit">Export de-identified CSV</button><?php endif;?></form>
<table><thead><tr><th>Status</th><th>Applications</th></tr></thead><tbody><?php foreach($counts as $status=>$count):?><tr><td><?=escapeHtml(str_replace('_',' ',ucwords($status,'_')))?></td><td><?=$count?></td></tr><?php endforeach;?></tbody></table>
<p>CSV includes reference, term, status, position and timestamp only. Export is capped at 10,000 records.</p>
<p><a href="/recruitment.php">Back to recruitment</a></p></main></body></html>
