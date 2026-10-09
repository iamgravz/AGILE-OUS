<?php
declare(strict_types=1);
namespace Agile;

/**
 * Composite staging handoff evidence. A passing technical report is NEVER a
 * machine-granted production authorization.
 */
final class ReleaseReadiness {
    public static function summarize(array $infrastructureIssues,array $acceptanceReport,array $integrityReport):array {
        $technicalAccepted=$infrastructureIssues===[]
            && ($acceptanceReport['all_schema_invariants_passed']??false)===true
            && ($integrityReport['failed']??1)===0;
        $pending=$acceptanceReport['manual_signoffs_pending']??[];
        if(!is_array($pending)||$pending===[]) {
            $pending=['manual_authorization_record_is_missing'];
            $technicalAccepted=false;
        }
        return [
            'project'=>'AGILE OUS Membership & Student Welfare',
            'report_type'=>'technical_staging_readiness_handoff',
            'status'=>$technicalAccepted?'TECHNICALLY_ACCEPTABLE_FOR_HUMAN_STAGING_REVIEW':'BLOCKED_TECHNICAL_ISSUES',
            'technical_checks_passed'=>$technicalAccepted,
            'infrastructure_issue_count'=>count($infrastructureIssues),
            'infrastructure_issues'=>array_values($infrastructureIssues),
            'schema_invariants_passed'=>($acceptanceReport['all_schema_invariants_passed']??false)===true,
            'integrity_gates_passed'=>($integrityReport['failed']??1)===0,
            'integrity_failed_count'=>(int)($integrityReport['failed']??-1),
            'outstanding_manual_signoffs'=>array_values($pending),
            'policy_status'=>'DRAFT_NOT_RATIFIED',
            // No codepath can auto-authorize production; requires formal external governance.
            'production_release_authorized'=>false,
            'production_release_decision'=>'NO_GO_UNTIL_SEPARATE_HUMAN_APPROVAL',
            'contains_student_personal_data'=>false,
        ];
    }
}
