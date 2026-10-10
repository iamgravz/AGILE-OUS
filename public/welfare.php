<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/authorization.php';
require dirname(__DIR__) . '/src/welfare_security.php';
startSecureSession();
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
$user=requirePermission('welfare.view');
$isHead=in_array((string)$user['role'],['welfare_head','admin'],true);
$role=(string)$user['role'];
$allowed=['submitted'=>['under_review'],'under_review'=>['referred','resolved'],
 'referred'=>['under_review','resolved'],'resolved'=>['closed'],'closed'=>[]];
if($_SERVER['REQUEST_METHOD']==='POST'){
    checkCsrf();
    $action=(string)($_POST['action']??'');
    $pdo=db();
    if($action==='create'){
        requirePermission('welfare.create');
        $category=(string)($_POST['category']??'');
        $priority=(string)($_POST['priority']??'normal');
        $narrative=trim((string)($_POST['narrative']??''));
        if(!in_array($category,welfareCategories(),true) ||
           !in_array($priority,['normal','high'],true) ||
           strlen($narrative)<10 || strlen($narrative)>4000){
            http_response_code(422);exit('Invalid case details');
        }
        $reference='WF-'.strtoupper(bin2hex(random_bytes(10)));
        $encrypted=encryptWelfare($narrative);
        try{
            $pdo->beginTransaction();
            $stmt=$pdo->prepare('INSERT INTO welfare_cases(case_reference,category,priority,encrypted_narrative,created_by,assigned_to) VALUES(?,?,?,?,?,?)');
            $stmt->execute([$reference,$category,$priority,$encrypted,(int)$user['id'],(int)$user['id']]);
            $id=(int)$pdo->lastInsertId();
            $stmt=$pdo->prepare("INSERT INTO welfare_case_events(case_id,actor_id,action,next_status) VALUES (?,?,'created','submitted')");
            $stmt->execute([$id,(int)$user['id']]);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction()){$pdo->rollBack();}throw $e;}
    }elseif($action==='status'){
        requirePermission('welfare.update');
        $id=filter_var($_POST['case_id']??null,FILTER_VALIDATE_INT);
        $next=(string)($_POST['next_status']??'');
        if(!$id || !in_array($next,['under_review','referred','resolved','closed'],true)){
            http_response_code(422);exit('Invalid transition');
        }
        try{
            $pdo->beginTransaction();
            $stmt=$pdo->prepare('SELECT id,status,created_by,assigned_to FROM welfare_cases WHERE id=? FOR UPDATE');
            $stmt->execute([$id]);$case=$stmt->fetch();
            if(!$case || !canReadWelfareCase($user,$case)){
                $pdo->rollBack();http_response_code(404);exit('Case not found');
            }
            if(!in_array($next,$allowed[$case['status']]??[],true)){
                $pdo->rollBack();http_response_code(409);exit('Invalid status transition');
            }
            $stmt=$pdo->prepare('UPDATE welfare_cases SET status=? WHERE id=?');
            $stmt->execute([$next,$id]);
            $stmt=$pdo->prepare("INSERT INTO welfare_case_events(case_id,actor_id,action,prior_status,next_status) VALUES (?,?,'status_changed',?,?)");
            $stmt->execute([$id,(int)$user['id'],$case['status'],$next]);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction()){$pdo->rollBack();}throw $e;}
    }elseif($action==='assign'){
        if(!$isHead){http_response_code(403);exit('Head approval required');}
        $id=filter_var($_POST['case_id']??null,FILTER_VALIDATE_INT);
        $assignee=filter_var($_POST['assigned_to']??null,FILTER_VALIDATE_INT);
        if(!$id||!$assignee){http_response_code(422);exit('Invalid assignment');}
        try{
            $pdo->beginTransaction();
            $stmt=$pdo->prepare("SELECT id FROM users WHERE id=? AND active=1 AND role IN ('welfare_head','welfare_member')");
            $stmt->execute([$assignee]);
            if(!$stmt->fetchColumn()){$pdo->rollBack();http_response_code(422);exit('Invalid welfare assignee');}
            $stmt=$pdo->prepare('SELECT id FROM welfare_cases WHERE id=? FOR UPDATE');
            $stmt->execute([$id]);
            if(!$stmt->fetchColumn()){$pdo->rollBack();http_response_code(404);exit('Case not found');}
            $stmt=$pdo->prepare('UPDATE welfare_cases SET assigned_to=? WHERE id=?');
            $stmt->execute([$assignee,$id]);
            $stmt=$pdo->prepare("INSERT INTO welfare_case_events(case_id,actor_id,action) VALUES (?,?,'assigned')");
            $stmt->execute([$id,(int)$user['id']]);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction()){$pdo->rollBack();}throw $e;}
    }else{http_response_code(422);exit('Unknown action');}
    header('Location: /welfare.php?updated=1',true,303);exit;
}
if($isHead){
    $cases=db()->query('SELECT id,case_reference,category,priority,status,created_by,assigned_to,encrypted_narrative,created_at FROM welfare_cases ORDER BY id DESC LIMIT 100')->fetchAll();
}else{
    $stmt=db()->prepare('SELECT id,case_reference,category,priority,status,created_by,assigned_to,encrypted_narrative,created_at FROM welfare_cases WHERE created_by=? OR assigned_to=? ORDER BY id DESC LIMIT 100');
    $stmt->execute([(int)$user['id'],(int)$user['id']]);$cases=$stmt->fetchAll();
}
$members=$isHead?db()->query("SELECT id,full_name FROM users WHERE active=1 AND role IN ('welfare_head','welfare_member') ORDER BY full_name")->fetchAll():[];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Welfare Cases — AGILE OUS</title>
<style>body{font:16px system-ui;margin:2rem;background:#faf8f5;color:#24202a}main{max-width:950px;margin:auto}article,section{background:#fff;padding:1.2rem;margin:1rem 0;border:1px solid #ddd;border-radius:10px}
input,select,textarea,button{font:inherit;padding:.55rem;margin:.3rem}textarea{width:95%;min-height:90px}button{background:#670c24;color:white;border:0;border-radius:5px}pre{white-space:pre-wrap;overflow-wrap:anywhere}</style></head><body><main>
<h1>Student Welfare Case Management</h1>
<p>Confidential staff-only records. President dashboard access does not include case details.</p>
<p><a href="/">Dashboard</a></p>
<?php if(isset($_GET['updated'])):?><p role="status">Case change recorded.</p><?php endif;?>
<section><h2>Create internal welfare case</h2>
<form method="post"><input type="hidden" name="csrf_token" value="<?=escapeHtml(csrfToken())?>">
<input type="hidden" name="action" value="create">
<label>Category <select name="category"><?php foreach(welfareCategories() as $category):?>
<option value="<?=escapeHtml($category)?>"><?=escapeHtml($category)?></option><?php endforeach;?></select></label>
<label>Priority <select name="priority"><option value="normal">Normal</option><option value="high">High</option></select></label>
<label>Case details <textarea name="narrative" minlength="10" maxlength="4000" required></textarea></label>
<button>Create encrypted case</button></form></section>
<h2>Authorized cases</h2>
<?php foreach($cases as $case):if(!canReadWelfareCase($user,$case))continue;?>
<article><h3><?=escapeHtml($case['case_reference'])?></h3>
<p><?=escapeHtml($case['category'])?> · <?=escapeHtml($case['priority'])?> · <?=escapeHtml($case['status'])?></p>
<details><summary>View confidential case description</summary>
<pre><?=escapeHtml(decryptWelfare((string)$case['encrypted_narrative']))?></pre></details>
<?php if(!empty($allowed[$case['status']])):?>
<form method="post"><input type="hidden" name="csrf_token" value="<?=escapeHtml(csrfToken())?>">
<input type="hidden" name="action" value="status"><input type="hidden" name="case_id" value="<?=(int)$case['id']?>">
<select name="next_status"><?php foreach($allowed[$case['status']] as $next):?><option value="<?=escapeHtml($next)?>"><?=escapeHtml(str_replace('_',' ',$next))?></option><?php endforeach;?></select>
<button>Update status</button></form><?php endif;?>
<?php if($isHead):?><form method="post">
<input type="hidden" name="csrf_token" value="<?=escapeHtml(csrfToken())?>">
<input type="hidden" name="action" value="assign"><input type="hidden" name="case_id" value="<?=(int)$case['id']?>">
<select name="assigned_to"><?php foreach($members as $member):?><option value="<?=(int)$member['id']?>" <?=(int)$case['assigned_to']===(int)$member['id']?'selected':''?>><?=escapeHtml($member['full_name'])?></option><?php endforeach;?></select>
<button>Assign</button></form><?php endif;?></article>
<?php endforeach;?></main></body></html>
