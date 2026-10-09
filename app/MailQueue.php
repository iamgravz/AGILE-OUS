<?php
declare(strict_types=1);
namespace Agile;

use DomainException;
use PDO;

/**
 * At-least-once job processing with conservative delivery reconciliation.
 * Gmail submission is not an exactly-once protocol. Any unknown result is
 * quarantined for human review, never blindly requeued.
 */
final class MailQueue {
    private const MAX_ATTEMPTS=4;

    public static function recoverStale(int $minutes=10): int {
        if ($minutes<5 || $minutes>1440) throw new DomainException('Invalid recovery window.');
        $q=\db()->prepare("UPDATE notification_outbox
           SET status='needs_review',
               last_error='Worker interrupted: submission outcome unknown; manual verification required',
               processed_at=NOW()
           WHERE status='processing'
             AND processing_started_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)");
        $q->execute([$minutes]);
        return $q->rowCount();
    }

    /**
     * Authorized operator must independently verify Gmail Sent items and
     * decide whether a requeue risks duplicates. All decisions are audited.
     */
    public static function review(int $jobId,array $actor,string $action,string $reason): void {
        if (($actor['role']??'')!=='msw_head') {
            throw new DomainException('Only MSW Head may resolve uncertain email delivery.');
        }
        $reason=trim($reason);
        if ($jobId<1 || !in_array($action,['requeue','mark_failed'],true)
            || mb_strlen($reason)<30 || mb_strlen($reason)>1000) {
            throw new DomainException('A supported delivery decision and 30–1,000 character review note are required.');
        }
        $pdo=\db();$pdo->beginTransaction();
        try {
            $q=$pdo->prepare('SELECT * FROM notification_outbox WHERE id=? FOR UPDATE');
            $q->execute([$jobId]);$job=$q->fetch();
            if (!$job || !in_array($job['status'],['needs_review','disabled','failed'],true)) {
                throw new DomainException('Only blocked or uncertain email jobs may be reviewed.');
            }
            if ($action==='requeue' && \envValue('MAIL_TRANSPORT','disabled')!=='gmail') {
                throw new DomainException('Gmail must be configured before a blocked job is requeued.');
            }
            $newStatus=$action==='requeue'?'queued':'failed';
            $pdo->prepare("UPDATE notification_outbox
              SET status=?,attempts=CASE WHEN ?='queued' THEN 0 ELSE attempts END,
                  next_attempt_at=NOW(),processing_started_at=NULL,
                  last_error=CASE WHEN ?='queued' THEN NULL ELSE 'Operator stopped delivery after review' END
              WHERE id=?")
              ->execute([$newStatus,$newStatus,$newStatus,$jobId]);
            $pdo->prepare('INSERT INTO mail_delivery_reviews
                (outbox_id,reviewer_id,previous_status,decision,reason)
                VALUES (?,?,?,?,?)')
              ->execute([$jobId,(int)$actor['id'],$job['status'],$action,$reason]);
            \audit((int)$actor['id'],'mail.review_'.$action,'outbox',$jobId);
            $pdo->commit();
        } catch (\Throwable $e) {$pdo->rollBack();throw $e;}
    }

    public static function work(int $limit=10): array {
        if ($limit<1 || $limit>100) throw new DomainException('Invalid mail batch size.');
        $sent=0;$failed=0;$disabled=0;$uncertain=0;
        $recovered=self::recoverStale();
        for($i=0;$i<$limit;$i++){
            $pdo=\db();
            $pdo->beginTransaction();
            try {
                $q=$pdo->query("SELECT * FROM notification_outbox
                  WHERE status='queued' AND attempts<4 AND next_attempt_at<=NOW()
                  ORDER BY id LIMIT 1 FOR UPDATE SKIP LOCKED");
                $job=$q->fetch();
                if (!$job) {$pdo->commit();break;}
                $pdo->prepare("UPDATE notification_outbox
                  SET status='processing',attempts=attempts+1,
                      processing_started_at=NOW(),last_attempt_at=NOW()
                  WHERE id=?")->execute([(int)$job['id']]);
                $pdo->commit();
            } catch (\Throwable $e) {$pdo->rollBack();throw $e;}

            $jobId=(int)$job['id'];
            if (\envValue('MAIL_TRANSPORT','disabled')!=='gmail') {
                $pdo->prepare("UPDATE notification_outbox
                  SET status='disabled',last_error='Gmail transport not configured',
                      processed_at=NOW(),processing_started_at=NULL WHERE id=?")
                  ->execute([$jobId]);
                $disabled++;
                continue;
            }
            try {
                $gmailId=Messaging::sendGmail($job['recipient'],$job['subject'],$job['body']);
                $pdo->prepare("UPDATE notification_outbox SET status='submitted',
                    provider_reference=?,last_error=NULL,processed_at=NOW(),
                    processing_started_at=NULL WHERE id=?")
                    ->execute([$gmailId,$jobId]);
                $sent++;
            } catch (MailDeliveryUncertain $e) {
                // Even one attempted Gmail API call may have been accepted;
                // never auto-retry until independently reconciled.
                $pdo->prepare("UPDATE notification_outbox SET status='needs_review',
                     last_error='Gmail submission outcome unknown; check provider before manual retry',
                     processed_at=NOW(),processing_started_at=NULL WHERE id=?")
                     ->execute([$jobId]);
                error_log('AGILE email job #'.$jobId.' requires manual delivery reconciliation.');
                $uncertain++;
            } catch (\Throwable $e) {
                // Safe-to-retry class: failure before an actual Gmail submit call.
                $nextAttempt=(int)$job['attempts']+1;
                $willRetry=$nextAttempt<self::MAX_ATTEMPTS;
                $status=$willRetry?'queued':'failed';
                $seconds=min(60*(2**(int)$job['attempts']),3600);
                $pdo->prepare("UPDATE notification_outbox
                    SET status=?,last_error='Pre-submission email configuration/auth error',
                        next_attempt_at=DATE_ADD(NOW(),INTERVAL ? SECOND),
                        processed_at=NOW(),processing_started_at=NULL
                    WHERE id=?")
                    ->execute([$status,$seconds,$jobId]);
                error_log('AGILE email job #'.$jobId.' pre-submission failure type: '.get_class($e));
                $failed++;
            }
        }
        return compact('sent','failed','disabled','uncertain','recovered');
    }
}
