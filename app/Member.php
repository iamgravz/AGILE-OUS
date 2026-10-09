<?php
declare(strict_types=1);
namespace Agile;

use DomainException;

final class Member {
    public static function sendInvitation(int $memberId,array $actor): void {
        if ($actor['role']!=='msw_head') throw new DomainException('Only MSW Head can invite members.');
        $pdo=\db();$pdo->beginTransaction();
        try {
            $q=$pdo->prepare('SELECT * FROM members WHERE id=? FOR UPDATE');
            $q->execute([$memberId]);$member=$q->fetch();
            if (!$member||$member['membership_status']!=='active'||$member['user_id'])
                throw new DomainException('Active member without an account is required.');
            $token=bin2hex(random_bytes(32));
            $pdo->prepare('INSERT INTO member_invitations(member_id,token_hash,expires_at,created_by) VALUES(?, ?, DATE_ADD(NOW(), INTERVAL 48 HOUR), ?)')
                ->execute([$memberId,hash('sha256',$token),(int)$actor['id']]);
            $inviteId=(int)$pdo->lastInsertId();
            $link=rtrim(\envValue('APP_URL','http://localhost:8000'),'/').'/claim?token='.rawurlencode($token);
            Messaging::enqueue('member.invite.'.$inviteId,$member['email'],'Your AGILE OUS member account invitation',
                Messaging::branded('Activate your account',
                "You have been invited to activate your optional AGILE OUS membership account.\nThis link expires in 48 hours:\n".$link));
            \audit((int)$actor['id'],'member.invited','member',$memberId);
            $pdo->commit();
        }catch(\Throwable $e){$pdo->rollBack();throw $e;}
    }
    public static function claim(string $token,string $password): void {
        if (!preg_match('/^[a-f0-9]{64}$/',$token)||strlen($password)<12||strlen($password)>200)
            throw new DomainException('Invalid activation token or password length (12–200 characters).');
        $pdo=\db();$pdo->beginTransaction();
        try {
            $hash=hash('sha256',$token);
            $q=$pdo->prepare('SELECT i.*,m.email,m.full_name,m.user_id,m.membership_status
                FROM member_invitations i JOIN members m ON m.id=i.member_id
                WHERE i.token_hash=? FOR UPDATE');
            $q->execute([$hash]);$inv=$q->fetch();
            if (!$inv||$inv['used_at']!==null||strtotime($inv['expires_at'])<time()||
                $inv['user_id']!==null||$inv['membership_status']!=='active')
                throw new DomainException('Invitation expired, already used or unavailable.');
            $q=$pdo->prepare("INSERT INTO users(email,display_name,password_hash,role) VALUES (?, ?, ?, 'member')");
            $q->execute([$inv['email'],$inv['full_name'],password_hash($password,PASSWORD_DEFAULT)]);
            $userId=(int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE members SET user_id=? WHERE id=? AND user_id IS NULL')->execute([$userId,(int)$inv['member_id']]);
            $pdo->prepare('UPDATE member_invitations SET used_at=NOW() WHERE id=?')->execute([(int)$inv['id']]);
            \audit($userId,'member.account_activated','member',(int)$inv['member_id']);
            $pdo->commit();
        } catch (\PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode()==='23000') throw new DomainException('Email already registered; contact MSW to recover access.');
            throw $e;
        } catch (\Throwable $e){$pdo->rollBack();throw $e;}
    }
    public static function own(array $user): ?array {
        if ($user['role']!=='member')throw new DomainException('Member dashboard only.');
        $q=\db()->prepare('SELECT id,full_name,email,membership_status,membership_type,valid_until,verification_token_hash
              FROM members WHERE user_id=?');
        $q->execute([(int)$user['id']]);return $q->fetch()?:null;
    }
    public static function publicVerification(string $code): ?array {
        if (!preg_match('/^[a-f0-9]{64}$/',$code))return null;
        $q=\db()->prepare('SELECT full_name,membership_status,membership_type,valid_until
            FROM members WHERE verification_token_hash=?');
        $q->execute([$code]);$m=$q->fetch();
        if (!$m)return null;
        $valid=$m['membership_status']==='active'&&($m['valid_until']===null||$m['valid_until']>=date('Y-m-d'));
        return ['full_name'=>$m['full_name'],'membership_type'=>$m['membership_type'],'valid'=>$valid];
    }
}
