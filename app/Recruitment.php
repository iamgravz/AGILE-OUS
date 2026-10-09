<?php
declare(strict_types=1);
namespace Agile;

use DomainException;
use PDO;

final class Recruitment {
    public const CATEGORIES = ['Committee Member','Deputy Committee Head','Committee Head','Executive Officer','The Source Code'];

    public static function openVacancies(?string $category = null): array {
        if ($category === null) {
            return \db()->query("SELECT id,committee_name,role_category,position_title,capacity,filled
               FROM vacancies WHERE status='published' AND filled < capacity ORDER BY id DESC LIMIT 100")->fetchAll();
        }
        $q=\db()->prepare("SELECT id,committee_name,role_category,position_title,capacity,filled
            FROM vacancies WHERE status='published' AND filled < capacity AND role_category=? ORDER BY id DESC LIMIT 100");
        $q->execute([$category]);
        return $q->fetchAll();
    }

    public static function request(array $actor,array $input): int {
        if (!in_array($actor['role'],['committee_head','msw_head'],true)) {
            throw new DomainException('Only an authorized committee head may request positions.');
        }
        $committee=trim((string)($input['committee_name']??''));
        $category=(string)($input['role_category']??'');
        $title=trim((string)($input['position_title']??''));
        $slots=filter_var($input['requested_slots']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>30]]);
        $reason=trim((string)($input['reason']??''));
        if (mb_strlen($committee)<3||mb_strlen($committee)>140||!in_array($category,self::CATEGORIES,true)||
            mb_strlen($title)<3||mb_strlen($title)>140||!$slots||mb_strlen($reason)<15||mb_strlen($reason)>2500) {
            throw new DomainException('Provide a committee, valid role/position, 1–30 slots and clear reason (15–2,500 characters).');
        }
        $q=\db()->prepare('INSERT INTO hr_requests (requested_by,committee_name,role_category,position_title,requested_slots,reason) VALUES (?,?,?,?,?,?)');
        $q->execute([(int)$actor['id'],$committee,$category,$title,(int)$slots,$reason]);
        $id=(int)\db()->lastInsertId();
        \audit((int)$actor['id'],'hr.requested','hr_request',$id);
        return $id;
    }

    public static function decide(int $id,array $actor,bool $approve,string $note): void {
        if ($actor['role']!=='msw_head') throw new DomainException('MSW Head approval is required.');
        $note=trim($note);
        if (mb_strlen($note)<10||mb_strlen($note)>1000) throw new DomainException('Document the decision in 10–1,000 characters.');
        $pdo=\db();
        $pdo->beginTransaction();
        try {
            $q=$pdo->prepare('SELECT * FROM hr_requests WHERE id=? FOR UPDATE');
            $q->execute([$id]);$r=$q->fetch();
            if (!$r||$r['status']!=='pending') throw new DomainException('Request is missing or already decided.');
            $status=$approve?'approved':'rejected';
            $pdo->prepare('UPDATE hr_requests SET status=?,decided_by=?,decision_note=?,decided_at=NOW() WHERE id=?')
                ->execute([$status,(int)$actor['id'],$note,$id]);
            if ($approve) {
                $pdo->prepare("INSERT INTO vacancies (hr_request_id,committee_name,role_category,position_title,capacity,status)
                    VALUES (?,?,?,?,?,'published')")
                    ->execute([$id,$r['committee_name'],$r['role_category'],$r['position_title'],(int)$r['requested_slots']]);
            }
            \audit((int)$actor['id'],'hr.'.$status,'hr_request',$id);
            $pdo->commit();
        } catch (\Throwable $e) {$pdo->rollBack();throw $e;}
    }

    public static function scheduleInterview(int $applicationId,array $actor,string $localDatetime,string $details): int {
        self::assertReviewer($applicationId,$actor);
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d H:i',str_replace('T',' ',$localDatetime));
        if (!$date||$date->format('Y-m-d H:i')!==str_replace('T',' ',$localDatetime)||
            $date<new \DateTimeImmutable('now')||$date>new \DateTimeImmutable('+180 days')) {
            throw new DomainException('Interview must be scheduled within the next 180 days.');
        }
        $details=trim($details);
        if (mb_strlen($details)<5||mb_strlen($details)>500) throw new DomainException('Include location or virtual meeting instructions.');
        $q=\db()->prepare("INSERT INTO interviews(application_id,interviewer_id,starts_at,meeting_details) VALUES (?,?,?,?)");
        $q->execute([$applicationId,(int)$actor['id'],$date->format('Y-m-d H:i:s'),$details]);
        $id=(int)\db()->lastInsertId();
        \audit((int)$actor['id'],'interview.scheduled','interview',$id);
        return $id;
    }

    public static function recordEvaluation(int $appId,array $actor,int $score,string $recommendation,string $notes): void {
        self::assertReviewer($appId,$actor);
        if ($score<0||$score>100||!in_array($recommendation,['recommend','hold','not_recommend'],true)||
            mb_strlen(trim($notes))<10||mb_strlen($notes)>2500) throw new DomainException('Invalid evaluation fields.');
        $q=\db()->prepare('INSERT INTO evaluations(application_id,evaluator_id,score,recommendation,notes) VALUES (?,?,?,?,?)');
        $q->execute([$appId,(int)$actor['id'],$score,$recommendation,trim($notes)]);
        \audit((int)$actor['id'],'evaluation.recorded','membership_application',$appId);
    }

    public static function completeInterview(int $id,array $actor): void {
        if (!in_array($actor['role'],['msw_head','msw_member'],true)) throw new DomainException('Unauthorized interviewer.');
        $q=\db()->prepare('SELECT application_id,interviewer_id,status FROM interviews WHERE id=?');
        $q->execute([$id]);$row=$q->fetch();
        if (!$row||$row['status']!=='scheduled'||((int)$row['interviewer_id']!==(int)$actor['id']&&$actor['role']!=='msw_head')) {
            throw new DomainException('Only the assigned interviewer or MSW Head can complete a scheduled interview.');
        }
        self::assertReviewer((int)$row['application_id'],$actor);
        $q=\db()->prepare("UPDATE interviews SET status='completed' WHERE id=? AND status='scheduled'");
        $q->execute([$id]);
        \audit((int)$actor['id'],'interview.completed','interview',$id);
    }

    public static function assertReviewer(int $appId,array $actor): void {
        if (!in_array($actor['role'],['msw_head','msw_member'],true)) throw new DomainException('Not authorized.');
        ApplicationWorkflow::findForActor($appId,$actor);
    }

    public static function finalizeApprovedMembership(PDO $pdo,array $app,array $actor): int {
        // Must be called from the parent approval transaction after app row FOR UPDATE.
        $vacancyId=$app['vacancy_id']??null;
        if ($vacancyId!==null) {
            $q=$pdo->prepare('SELECT * FROM vacancies WHERE id=? FOR UPDATE');
            $q->execute([(int)$vacancyId]);$v=$q->fetch();
            if (!$v||$v['status']!=='published'||$v['filled'] >= $v['capacity'] ||
                $v['role_category']!==$app['desired_role']) {
                throw new DomainException('Requested position is unavailable or mismatched.');
            }
            $q=$pdo->prepare("SELECT COUNT(*) FROM interviews WHERE application_id=? AND status='completed'");
            $q->execute([(int)$app['id']]);
            if ((int)$q->fetchColumn()<1) throw new DomainException('A completed interview record is required.');
            $q=$pdo->prepare('SELECT COUNT(*) FROM evaluations WHERE application_id=?');
            $q->execute([(int)$app['id']]);
            if ((int)$q->fetchColumn()<1) throw new DomainException('Recorded evaluation is required.');
            $pdo->prepare('UPDATE vacancies SET filled=filled+1 WHERE id=?')->execute([(int)$vacancyId]);
        }
        $verifyToken=hash('sha256',random_bytes(32));
        $type=$vacancyId!==null?'appointed':'general';
        $q=$pdo->prepare("INSERT INTO members(application_id,student_number,full_name,email,membership_type,verification_token_hash)
                          VALUES (?,?,?,?,?,?)");
        $q->execute([(int)$app['id'],$app['student_number'],$app['full_name'],$app['email'],$type,$verifyToken]);
        $memberId=(int)$pdo->lastInsertId();
        if ($vacancyId!==null) {
            $pdo->prepare('INSERT INTO role_assignments(member_id,vacancy_id,role_category,position_title,recorded_by) VALUES (?,?,?,?,?)')
                ->execute([$memberId,(int)$vacancyId,$v['role_category'],$v['position_title'],(int)$actor['id']]);
        }
        \audit((int)$actor['id'],'member.created','member',$memberId);
        return $memberId;
    }
}
