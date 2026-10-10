<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/authorization.php';
startSecureSession();
header('Cache-Control: no-store, private');
header('X-Frame-Options: DENY');
$user=requirePermission('membership.view');
$manager=in_array((string)$user['role'],['admin','membership_head'],true);
if($_SERVER['REQUEST_METHOD']==='POST'){
 checkCsrf();
 if(!$manager){http_response_code(403);exit('Permission denied');}
 $title=trim((string)($_POST['title']??''));
 $committee=trim((string)($_POST['committee']??''));
 $year=trim((string)($_POST['academic_year']??''));
 $term=(string)($_POST['semester']??'');
 $capacity=filter_var($_POST['capacity']??null,FILTER_VALIDATE_INT);
 if($title===''||strlen($title)>120||$committee===''||strlen($committee)>120||
 !preg_match('/^20\d{2}-20\d{2}$/',$year)||!in_array($term,['1st','2nd','Summer'],true)||
 $capacity===false||$capacity<1||$capacity>1000){http_response_code(422);exit('Invalid position');}
 $stmt=db()->prepare('INSERT INTO recruitment_positions(title,committee,academic_year,semester,capacity) VALUES(?,?,?,?,?)');
 try{$stmt->execute([$title,$committee,$year,$term,$capacity]);}
 catch(PDOException $e){if($e->getCode()==='23000'){http_response_code(409);exit('Position already configured');}throw $e;}
 header('Location: /positions.php?created=1',true,303);exit;
}
$rows=db()->query("SELECT p.id,p.title,p.committee,p.academic_year,p.semester,p.capacity,p.enabled,
 (SELECT COUNT(*) FROM membership_applications a WHERE a.recruitment_position_id=p.id AND a.status='approved') filled
 FROM recruitment_positions p ORDER BY p.academic_year DESC,p.semester,p.committee,p.title")->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Vacancies | AGILE OUS</title><style>body{font:16px system-ui;margin:2rem;background:#faf8f5}table{border-collapse:collapse;width:100%;background:#fff}td,th{border:1px solid #ddd;padding:.65rem;text-align:left}input,select{padding:.55rem;margin:.3rem}button{background:#670c24;color:white;border:0;border-radius:6px;padding:.65rem}</style></head><body>
<h1>Recruitment vacancies</h1><p><a href="/recruitment.php">Recruitment dashboard</a></p>
<?php if(isset($_GET['created'])):?><p role="status">Position created.</p><?php endif;?>
<?php if($manager):?><h2>Add authorized vacancy</h2><form method="post">
<input type="hidden" name="csrf_token" value="<?=escapeHtml(csrfToken())?>">
<input name="title" maxlength="120" placeholder="Position title" required>
<input name="committee" maxlength="120" placeholder="Committee" required>
<input name="academic_year" pattern="20[0-9]{2}-20[0-9]{2}" placeholder="2026-2027" required>
<select name="semester"><option value="1st">First</option><option value="2nd">Second</option><option value="Summer">Summer</option></select>
<input type="number" name="capacity" min="1" max="1000" required placeholder="Capacity">
<button type="submit">Add position</button></form><?php endif;?>
<h2>Vacancy overview</h2><table><thead><tr><th>Term</th><th>Committee</th><th>Position</th><th>Capacity</th><th>Approved</th><th>Remaining</th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr><td><?=escapeHtml($r['academic_year'].' '.$r['semester'])?></td><td><?=escapeHtml($r['committee'])?></td><td><?=escapeHtml($r['title'])?></td><td><?=(int)$r['capacity']?></td><td><?=(int)$r['filled']?></td><td><?=max(0,(int)$r['capacity']-(int)$r['filled'])?></td></tr><?php endforeach;?>
</tbody></table><p>Changes to capacities and applicant-to-position assignments require additional controlled workflows.</p></body></html>