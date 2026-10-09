<?php
declare(strict_types=1);
namespace Agile;

use DomainException;
final class Content {
    public static function published(): array {
        return \db()->query("SELECT id,title,content_type,body,published_at FROM content_posts
            WHERE status='published' ORDER BY published_at DESC LIMIT 30")->fetchAll();
    }
    public static function draft(array $actor,array $input): int {
        if (!in_array($actor['role'],['source_editor','msw_head','committee_head','executive_officer'],true))
            throw new DomainException('Content editing is restricted.');
        $title=trim((string)($input['title']??''));
        $body=trim((string)($input['body']??''));
        $type=(string)($input['content_type']??'');
        if (mb_strlen($title)<5||mb_strlen($title)>200||mb_strlen($body)<20||mb_strlen($body)>50000||
            !in_array($type,['news','event','publication','announcement'],true))
            throw new DomainException('Provide a valid title, content and type.');
        $q=\db()->prepare("INSERT INTO content_posts(author_id,title,body,content_type,status) VALUES(?,?,?,?,'draft')");
        $q->execute([(int)$actor['id'],$title,$body,$type]);
        $id=(int)\db()->lastInsertId();
        \audit((int)$actor['id'],'content.drafted','content_post',$id);
        return $id;
    }
    public static function changeStatus(int $id,array $actor,string $target): void {
        if (!in_array($actor['role'],['source_editor','msw_head','executive_officer'],true))
            throw new DomainException('Content access denied.');
        $pdo=\db();$pdo->beginTransaction();
        try {
            $q=$pdo->prepare('SELECT * FROM content_posts WHERE id=? FOR UPDATE');
            $q->execute([$id]);$row=$q->fetch();
            if (!$row) throw new DomainException('Post not found.');
            $allowed=match($row['status']) {
                'draft'=>['review'],
                'review'=>['published','draft'],
                'published'=>['archived'],
                'archived'=>[],
                default=>[]
            };
            if (!in_array($target,$allowed,true)) throw new DomainException('Invalid publishing transition.');
            $isPublisher=in_array($actor['role'],['msw_head','executive_officer'],true);
            if (in_array($target,['published','archived'],true)&&!$isPublisher)
                throw new DomainException('Publication requires authorized approval.');
            if ($target==='review'&&$row['author_id']!==(int)$actor['id']&&!$isPublisher)
                throw new DomainException('Only the author or publisher can request review.');
            $q=$pdo->prepare("UPDATE content_posts SET status=?,approved_by=?,published_at=CASE WHEN ?='published' THEN NOW() ELSE published_at END WHERE id=?");
            $q->execute([$target,$target==='published'?(int)$actor['id']:null,$target,$id]);
            \audit((int)$actor['id'],'content.'.$target,'content_post',$id);
            $pdo->commit();
        }catch(\Throwable $e){$pdo->rollBack();throw $e;}
    }
}
