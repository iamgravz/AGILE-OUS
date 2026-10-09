<?php
declare(strict_types=1);
namespace Agile;

/** Idempotent workflow automation; no adverse eligibility decisions. */
final class Automation {
    public static function runDue(): array {
        $pdo=\db();
        $created=['welfare_followups'=>0,'academic_reminders'=>0,'membership_expiry'=>0];
        // Only staff alert metadata; confidential case notes are not emailed.
        $q=$pdo->query("SELECT f.id,f.case_id,f.assigned_to,c.reference_code
          FROM welfare_followups f
          JOIN welfare_cases c ON c.id=f.case_id
          WHERE f.completed_at IS NULL AND f.due_at<=NOW() AND f.reminder_sent_at IS NULL
          ORDER BY f.due_at LIMIT 100");
        foreach($q->fetchAll() as $f) {
            Messaging::notify((int)$f['assigned_to'],'welfare.due.'.$f['id'],
                'Welfare follow-up is due','A confidential case follow-up requires attention. Open your assigned-case dashboard.');
            $pdo->prepare('UPDATE welfare_followups SET reminder_sent_at=NOW() WHERE id=? AND reminder_sent_at IS NULL')->execute([(int)$f['id']]);
            $created['welfare_followups']++;
        }
        $q=$pdo->query("SELECT v.id,v.member_id,t.deadline_on,m.email
          FROM academic_verifications v
          JOIN academic_terms t ON t.id=v.term_id
          JOIN members m ON m.id=v.member_id
          WHERE t.state='active' AND v.verified_result='pending'
          AND t.deadline_on BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY)
          LIMIT 100");
        foreach($q->fetchAll() as $v) {
            Messaging::enqueue('academic.reminder.'.(int)$v['id'],$v['email'],
                'AGILE OUS — Academic verification reminder',
                Messaging::branded('Semester verification',
                'Your organizational position requires semester verification. Please contact the Membership and Student Welfare Committee for secure submission instructions. Do not email confidential grades as a reply.'));
            $created['academic_reminders']++;
        }
        $q=$pdo->query("SELECT id,email,valid_until FROM members WHERE membership_status='active'
          AND valid_until BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY) LIMIT 100");
        foreach($q->fetchAll() as $m) {
            Messaging::enqueue('membership.expiry.'.(int)$m['id'].'.'.$m['valid_until'],$m['email'],
                'AGILE OUS — Membership validity notice',
                Messaging::branded('Membership reminder',
                'Your membership validity date is approaching. Contact MSW if renewal is required.'));
            $created['membership_expiry']++;
        }
        $pdo->prepare("INSERT INTO automation_runs(job_key,job_type,status,result_summary) VALUES(?,?,?,?)
             ON DUPLICATE KEY UPDATE executed_at=NOW(),result_summary=VALUES(result_summary)")
             ->execute(['scheduled.'.date('Y-m-d-H'),'scheduled_reminders','completed',json_encode($created)]);
        return $created;
    }
}
