<?php
declare(strict_types=1);
namespace Agile;

use PDO;
use RuntimeException;

/**
 * Privacy-minimized release evidence. This report deliberately contains no
 * student names, e-mail addresses, grade marks, case notes, tokens or secrets.
 * It can never grant production authorization or replace external sign-off.
 */
final class AcceptanceReport {
    private const MANUAL_GATES=[
        'final_bylaws_ratified_and_approved',
        'organizational_privacy_notice_lawful_basis_and_retention',
        'student_welfare_emergency_and_referral_governance',
        'supervised_staff_mfa_recovery_and_access_review',
        'malware_scanner_operational_signature_and_incident_runbook',
        'consistent_database_and_private_file_backup_rehearsal',
        'external_security_and_accessibility_review',
        'authorized_https_staging_with_synthetic_user_signoff',
        'production_hosting_key_management_and_operational_monitoring',
        'gmail_and_external_ai_authorization_if_enabled'
    ];

    private static function count(PDO $pdo,string $sql): int {
        return (int)$pdo->query($sql)->fetchColumn();
    }

    public static function collect(PDO $pdo,string $mode): array {
        if (!in_array($mode,['synthetic_ci','staging_read_only'],true)) {
            throw new RuntimeException('Acceptance reporting requires approved synthetic CI or read-only staging mode.');
        }
        $migrations=glob(dirname(__DIR__).'/database/migrations/*.sql');
        if($migrations===false)throw new RuntimeException('Migration directory unavailable.');
        $expected=array_map('basename',$migrations);
        sort($expected);
        $applied=$pdo->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        $missing=array_values(array_diff($expected,$applied));
        $unexpected=array_values(array_diff($applied,$expected));

        $checks=[
            'migrations_complete'=>[
                'passed'=>$missing===[]&&$unexpected==[],
                'detail'=>'Source migrations and applied migration list match',
                'missing_files'=>$missing,
                'unexpected_files'=>$unexpected
            ],
            'unratified_bylaws_not_approved'=>[
                'passed'=>self::count($pdo,"SELECT COUNT(*) FROM eligibility_policies
                    WHERE policy_version LIKE 'DRAFT-%' AND is_approved=1")===0,
                'detail'=>'No draft constitution/bylaw version has been flagged approved'
            ],
            'unapproved_policy_not_used_for_decisions'=>[
                'passed'=>self::count($pdo,"SELECT COUNT(*) FROM academic_verifications v
                    JOIN eligibility_policies p ON p.id=v.policy_id
                    WHERE p.is_approved=0")===0,
                'detail'=>'No academic verification references an unapproved grade policy'
            ],
            'vacancy_capacity_invariant'=>[
                'passed'=>self::count($pdo,'SELECT COUNT(*) FROM vacancies WHERE filled>capacity')===0,
                'detail'=>'No published or closed vacancy exceeds its approved capacity'
            ],
            'member_general_role_invariant'=>[
                'passed'=>self::count($pdo,"SELECT COUNT(*) FROM members m
                    JOIN role_assignments a ON a.member_id=m.id AND a.ends_at IS NULL
                    WHERE m.membership_type='general'")===0,
                'detail'=>'No General Member retains a current privileged organizational assignment'
            ],
            'clean_documents_have_verified_hashes'=>[
                'passed'=>self::count($pdo,"SELECT COUNT(*) FROM private_attachments
                    WHERE scan_status='clean' AND (content_sha256 IS NULL
                      OR content_sha256 NOT REGEXP '^[a-f0-9]{64}$')")===0,
                'detail'=>'No clean document lacks integrity metadata'
            ],
            'approved_staff_links_correspond_to_members'=>[
                'passed'=>self::count($pdo,"SELECT COUNT(*) FROM identity_link_requests r
                    LEFT JOIN members m ON m.id=r.member_id
                    WHERE r.status='approved' AND (m.user_id IS NULL OR m.user_id<>r.user_id)")===0,
                'detail'=>'All approved staff links refer to the expected member account'
            ],
            'no_aged_unconfirmed_mail_processing'=>[
                'passed'=>self::count($pdo,"SELECT COUNT(*) FROM notification_outbox
                    WHERE status='processing' AND processing_started_at<DATE_SUB(NOW(),INTERVAL 10 MINUTE)")===0,
                'detail'=>'No mail submission remains in stale processing state'
            ],
        ];
        $outstanding=[];
        foreach($checks as $name=>$item)if(!$item['passed'])$outstanding[]=$name;
        $counts=[
            // Aggregate operational counters only; do not include identifying data.
            'draft_policies'=>self::count($pdo,"SELECT COUNT(*) FROM eligibility_policies
                WHERE policy_version LIKE 'DRAFT-%' AND is_approved=0"),
            'quarantined_documents'=>self::count($pdo,"SELECT COUNT(*) FROM private_attachments
                WHERE scan_status IN ('quarantined','scanning','scan_error','infected')"),
            'undelivered_or_reconciliation_mail'=>self::count($pdo,"SELECT COUNT(*) FROM notification_outbox
                WHERE status IN ('queued','processing','failed','disabled','needs_review')"),
            'academic_review_cases_open'=>self::count($pdo,"SELECT COUNT(*) FROM academic_review_requests
                WHERE status IN ('submitted','in_review')")
        ];
        return [
            'project'=>'AGILE OUS Membership & Student Welfare',
            'report_type'=>'staging_acceptance_read_only',
            'mode'=>$mode,
            'generated_utc'=>gmdate('Y-m-d\TH:i:s\Z'),
            'schema_checks'=>$checks,
            'all_schema_invariants_passed'=>$outstanding===[],
            'schema_invariants_requiring_review'=>$outstanding,
            'aggregate_operational_indicators'=>$counts,
            'manual_signoffs_pending'=>self::MANUAL_GATES,
            // CI and schema checks can never authorize the live service.
            'production_release_authorized'=>false,
            'release_gate'=>'BLOCKED_PENDING_HUMAN_APPROVAL_AND_REAL_STAGING_VALIDATION'
        ];
    }
}
