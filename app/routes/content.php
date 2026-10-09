<?php
declare(strict_types=1);
use Agile\Auth;
use Agile\Content;

if ($path==='/updates'&&$method==='GET'){
    $html='<p>Published news, events, announcements and The Source Code publications.</p>';
    foreach(Content::published() as $post){
        $html.='<article><h2>'.escape($post['title']).'</h2><p>'.escape($post['content_type'])
            .' · '.escape((string)$post['published_at']).'</p><p>'.nl2br(escape($post['body'])).'</p></article><hr>';
    }
    page('AGILE OUS Updates',$html);
}
if ($path==='/staff/content'&&$method==='GET'){
    $actor=Auth::requireRole(['msw_head','source_editor','committee_head','executive_officer']);
    $html='<h2>Draft new content</h2><form method="post" action="/staff/content/draft">'.formToken()
        .'<label>Title<input name="title" required minlength="5" maxlength="200"></label>'
        .'<label>Content type<select name="content_type">';
    foreach(['news','event','publication','announcement'] as $t)$html.='<option>'.$t.'</option>';
    $html.='</select></label><label>Text<textarea name="body" rows="9" minlength="20" required></textarea></label>'
        .'<button>Save Draft</button></form>';
    if($actor['role']==='msw_head'||$actor['role']==='executive_officer')
        $rows=db()->query('SELECT id,title,content_type,status,author_id FROM content_posts ORDER BY id DESC LIMIT 80')->fetchAll();
    else {
        $q=db()->prepare('SELECT id,title,content_type,status,author_id FROM content_posts WHERE author_id=? ORDER BY id DESC LIMIT 80');
        $q->execute([(int)$actor['id']]);$rows=$q->fetchAll();
    }
    $html.='<h2>Editorial workflow</h2>';
    foreach($rows as $p){
        $html.='<p><b>'.escape($p['title']).'</b> · '.escape($p['content_type']).' · '.escape($p['status']).'</p>';
        $isPublisher=in_array($actor['role'],['msw_head','executive_officer'],true);
        $choices=match($p['status']){'draft'=>['review'],'review'=>['draft','published'],'published'=>['archived'],default=>[]};
        if(!$isPublisher)$choices=array_values(array_diff($choices,['published','archived']));
        foreach($choices as $target){
            $html.='<form method="post" action="/staff/content/status" style="display:inline">'.formToken()
                .'<input type="hidden" name="id" value="'.(int)$p['id'].'">'
                .'<button name="status" value="'.$target.'">'.escape(ucfirst($target)).'</button></form>';
        }
    }
    page('Content Management',$html);
}
if ($path==='/staff/content/draft'&&$method==='POST'){
    $actor=Auth::requireRole(['msw_head','source_editor','committee_head','executive_officer']);verifyCsrf();
    try{Content::draft($actor,$_POST);redirect('/staff/content');}
    catch(DomainException $e){http_response_code(422);page('Draft Error','<p class="error">'.escape($e->getMessage()).'</p>');}
}
if ($path==='/staff/content/status'&&$method==='POST'){
    $actor=Auth::requireRole(['msw_head','source_editor','executive_officer']);verifyCsrf();
    try{Content::changeStatus((int)($_POST['id']??0),$actor,(string)($_POST['status']??''));redirect('/staff/content');}
    catch(DomainException $e){http_response_code(422);page('Publishing Error','<p class="error">'.escape($e->getMessage()).'</p>');}
}
