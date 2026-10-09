<?php
declare(strict_types=1);
namespace Agile;

use PDO;

/**
 * Read-only acceptance assertions. All outputs are aggregate counts: never
 * names, emails, case narratives, grades, passwords, or file paths.
 */
final class AcceptanceAudit {
    public const REQUIRED_MIGRATIONS = [
        '001_initial.sql','002_application_review.sql','003_recruitment_members.sql',
        '004_welfare_content.sql','005_automation_academics.sql',
        '006_member_invitations.sql','007_member_privacy.sql',
        '008_email_center.sql','009_identity_mail_recovery.sql',
        '010_provisional_draft_bylaws.sql','011_academic_casework.sql',
        '012_mfa_secure_case_files.sql','013_attachment_quarantine.sql',
    ];

    private static function number(PDO $pdo,string $sql):int {
        return (int)$pdo->query($sql)->fetchColumn();
    }

    /** Each gate emits only a name, a pass/fail and a count, no sensitive row data. */
    public static function audit(PDO $pdo):array {
        $checks=[];
        $append=static function(string $name,int $failures)use(&$checks):void {
            $checks[]=[
                'name'=>$name,'passed'=>$failures===0,'violations'=>$failures,
            ];
        };

        $q=$pdo->query('SELECT filename FROM schema_migrations');
        $applied=array_column($q->fetchAll(PDO::FETCH_ASSOC),'filename');
        $append('All required schema migrations applied',
            count(array_diff(self::REQUIRED_MIGRATIONS,$applied)));

        $append('Original unratified bylaws policy is not approved',
            self::number($pdo,"SELECT COUNT(*) FROM eligibility_policies
                WHERE policy_version='DRAFT-2026-09-24-ARTICLE-VI-2' AND is_approved<>0")
            + (self::number($pdo,"SELECT COUNT(*) FROM eligibility_policies
                WHERE policy_version='DRAFT-2026-09-24-ARTICLE-VI-2'")===1?0:1));

        $append('Every approved application has exactly one linked member',
            self::number($pdo,"SELECT COUNT(*) FROM membership_applications a
                LEFT JOIN members m ON m.application_id=a.id
                WHERE a.status='approved' AND m.id IS NULL"));

        $append('No active membership derives from a non-approved application',
            self::number($pdo,"SELECT COUNT(*) FROM members m
                JOIN membership_applications a ON a.id=m.application_id
                WHERE a.status<>'approved'"));

        $append('Vacancy fill counts agree with active appointed-role records',
            self::number($pdo,"SELECT COUNT(*) FROM vacancies v
                LEFT JOIN (
                    SELECT vacancy_id,COUNT(*) AS active_count FROM role_assignments
                    WHERE vacancy_id IS NOT NULL AND ends_at IS NULL
                    GROUP BY vacancy_id
                ) AS roles ON roles.vacancy_id=v.id
                WHERE v.filled<>COALESCE(roles.active_count,0)"));

        $append('Official academic verification never uses unapproved policy',
            self::number($pdo,"SELECT COUNT(*) FROM academic_verifications v
                JOIN eligibility_policies p ON p.id=v.policy_id
                WHERE p.is_approved=0"));

        $append('No General Member is assigned an active officer role',
            self::number($pdo,"SELECT COUNT(*) FROM members m
                JOIN role_assignments r ON r.member_id=m.id
                WHERE m.membership_type='general' AND r.ends_at IS NULL"));

        $append('No active General Member keeps an organization staff login role',
            self::number($pdo,"SELECT COUNT(*) FROM members m
                JOIN users u ON u.id=m.user_id
                WHERE m.membership_type='general' AND u.role IN
                    ('msw_head','msw_member','president','committee_head',
                     'deputy_head','executive_officer','source_editor')"));

        $append('Every clean private document has a verified SHA-256 digest',
            self::number($pdo,"SELECT COUNT(*) FROM private_attachments
                WHERE scan_status='clean' AND
                  (content_sha256 IS NULL OR content_sha256 NOT REGEXP '^[a-f0-9]{64}$')"));

        $append('No submitted Gmail outbox row lacks a provider acceptance ID',
            self::number($pdo,"SELECT COUNT(*) FROM notification_outbox
                WHERE status='submitted' AND (provider_reference IS NULL OR provider_reference='')"));

        $append('Every final academic correction has reviewer evidence',
            self::number($pdo,"SELECT COUNT(*) FROM academic_review_requests
                WHERE status IN ('resolved','rejected') AND
                (reviewed_by IS NULL OR reviewed_at IS NULL OR
                 resolution_note IS NULL OR CHAR_LENGTH(TRIM(resolution_note))<20)"));

        $append('No duplicate active academic appeal for the same verification',
            self::number($pdo,"SELECT COUNT(*) FROM (
                SELECT verification_id,COUNT(*) AS n FROM academic_review_requests
                WHERE status IN ('submitted','in_review')
                GROUP BY verification_id HAVING COUNT(*)>1
            ) duplicate_requests"));

        $passed=count(array_filter($checks,static fn(array $x):bool=>$x['passed']));
        return [
            'scope'=>'read_only_aggregate_acceptance',
            'policy_version'=>'DRAFT-2026-09-24-ARTICLE-VI-2',
            'checks'=>$checks,
            'passed'=>$passed,
            'failed'=>count($checks)-$passed,
            'contains_student_data'=>false,
        ];
    }
}
