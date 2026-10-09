<?php
declare(strict_types=1);
namespace Agile;

use DomainException;
final class EmailCenter {
    public const TEMPLATES=[
      'application_receipt'=>'Application Received',
      'interview_invitation'=>'Interview Invitation',
      'document_request'=>'Document Verification Request',
      'approved'=>'Application Approved',
      'rejected'=>'Application Decision',
      'waitlisted'=>'Application Waitlist Update',
      'hr_request'=>'HR Request Update',
      'custom'=>'Organization Communication'
    ];
    public static function draft(array $actor,array $input): int {
        if(!in_array($actor['role'],['msw_head','msw_member'],true))
            throw new DomainException('Only MSW may draft official membership messages.');
        $to=strtolower(trim((string)($input['recipient']??'')));
        $template=(string)($input['template_code']??'');
        $subject=trim((string)($input['subject']??''));
        $message=trim((string)($input['message']??''));
        if(!filter_var($to,FILTER_VALIDATE_EMAIL)||!isset(self::TEMPLATES[$template])||
           mb_strlen($subject)<3||mb_strlen($subject)>255||mb_strlen($message)<10||mb_strlen($message)>10000)
            throw new DomainException('Recipient, subject, template or message is invalid.');
        $q=\db()->prepare("INSERT INTO email_drafts(author_id,recipient,template_code,subject,message_text) VALUES(?,?,?,?,?)");
        $q->execute([(int)$actor['id'],$to,$template,$subject,$message]);
        $id=(int)\db()->lastInsertId();
        \audit((int)$actor['id'],'email.drafted','email_draft',$id);
        return $id;
    }
    public static function queueApproved(int $id,array $actor): void {
        if($actor['role']!=='msw_head')throw new DomainException('Only MSW Head may authorize sending.');
        $pdo=\db();$pdo->beginTransaction();
        try{
            $q=$pdo->prepare('SELECT * FROM email_drafts WHERE id=? FOR UPDATE');
            $q->execute([$id]);$d=$q->fetch();
            if(!$d||$d['status']!=='draft')throw new DomainException('Draft missing or already queued.');
            $body=Messaging::branded(self::TEMPLATES[$d['template_code']],$d['message_text']);
            Messaging::enqueue('email.draft.'.$id,$d['recipient'],$d['subject'],$body);
            $pdo->prepare("UPDATE email_drafts SET status='queued',approved_by=?,approved_at=NOW() WHERE id=?")
                ->execute([(int)$actor['id'],$id]);
            \audit((int)$actor['id'],'email.queued','email_draft',$id);
            $pdo->commit();
        }catch(\Throwable $e){$pdo->rollBack();throw $e;}
    }
}
