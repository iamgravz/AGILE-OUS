<?php
declare(strict_types=1);
namespace Agile;

use DomainException;

/**
 * Provisional academic eligibility *preview*, grounded in the 12-page
 * AGILE-OUS Constitution & By-Laws draft dated 2026-09-24.
 *
 * Important: outputs are advisory flags only, never eligibility, acceptance,
 * academic demotion, or account permission decisions.
 */
final class DraftBylawsPolicy {
    public const VERSION='DRAFT-2026-09-24-ARTICLE-VI-2';
    public const SOURCE='Article VI Section 2 — Candidate Qualifications (unratified draft)';
    public const COVERED_ROLES=[
        'Executive Officer','Committee Head','Deputy Committee Head',
        'Committee Member','The Source Code'
    ];

    public static function preview(array $input): array {
        $role=trim((string)($input['role_category']??''));
        if ($role==='General Member' || $role==='Regular Member') {
            return self::result('not_applicable',[],[
                'The working system excludes general/regular members from semestral grade screening.'
            ],false);
        }
        if (!in_array($role,self::COVERED_ROLES,true)) {
            throw new DomainException('Unknown draft screening role.');
        }

        $issues=[];
        $incomplete=[];
        $notes=[
            'All results are provisional and require authorized human verification.',
            'An academic review every semester does not change the draft’s historical grade lookback.'
        ];
        $officerCandidate=$role==='Executive Officer';
        if (!$officerCandidate) {
            $notes[]='Article VI Section 2 explicitly concerns officer candidacy; applying its grade standards to this position is a proposed operational extension, not a provision of the draft itself.';
        }

        $records=$input['grade_records']??null;
        if (!is_array($records) || count($records)>200) {
            throw new DomainException('Provide an array of no more than 200 grade records.');
        }
        foreach ($records as $record) {
            if (!is_array($record)) throw new DomainException('Each grade record must be an object.');
            $mark=strtoupper(trim((string)($record['grade']??'')));
            $course=trim((string)($record['course']??''));
            if ($mark==='' || strlen($mark)>24 || $course==='' || mb_strlen($course)>140) {
                throw new DomainException('Each grade record needs a course and valid mark.');
            }
            // Only the exact draft codes are definitive flags. Equivalently
            // written numeric 5.00 / 5.0 are both considered potentially 5.0.
            $isFail=preg_match('/^5(?:\.0+)?$/',$mark)===1
                || in_array($mark,['F','W','D'],true);
            if ($isFail) {
                $issues[]='Draft-listed grade/status '.$mark.' found in '.self::shortName($course).'; manual record verification required.';
            } elseif ($mark==='INC' || $mark==='INCOMPLETE' || $mark==='UNREADABLE') {
                $incomplete[]='Unresolved grade/status '.$mark.' in '.self::shortName($course).'; the draft does not explicitly define its treatment.';
            }
        }
        if ($records===[] || ($input['full_history_supplied']??null)!==true) {
            $incomplete[]='Complete grade history for the institutional stay has not been confirmed.';
        }

        if ($officerCandidate) {
            $position=strtolower(trim((string)($input['executive_position']??'')));
            $presidential=in_array($position,['president','vice president','vice-president'],true);
            $minYear=$presidential?3:2;
            if ($position==='') {
                $incomplete[]='Executive position must be specified to apply the minimum year-level rule.';
            }
            $year=$input['year_level']??null;
            if (!is_int($year) || $year<1 || $year>10) {
                $incomplete[]='Year level must be confirmed.';
            } elseif ($year<$minYear) {
                $issues[]='Candidate year level is below draft minimum of '.$minYear.' for this executive position.';
            }
            if (($input['current_bsit_ous_enrollment']??null)===false) {
                $issues[]='Candidate is not currently enrolled as a bona fide BSIT OUS student.';
            } elseif (($input['current_bsit_ous_enrollment']??null)!==true) {
                $incomplete[]='Current BSIT OUS enrollment is not verified.';
            }
            if (($input['full_academic_load']??null)===false) {
                $issues[]='Candidate does not carry the full prescribed academic load.';
            } elseif (($input['full_academic_load']??null)!==true) {
                $incomplete[]='Prescribed full academic load has not been verified.';
            }
            $notes[]='Good moral character and pending administrative/disciplinary cases require separate authorized human review.';
        }

        // Prioritize direct draft-rule flags, but never produce an approval.
        $flag=$issues!==[]?'review_required'
             :($incomplete!==[]?'needs_more_information':'no_flags');
        return self::result($flag,$issues,$incomplete,true,$notes);
    }

    private static function shortName(string $value): string {
        return mb_substr($value,0,90);
    }

    private static function result(
        string $flag,array $issues,array $incomplete,bool $reviewNeeded,array $notes=[]
    ): array {
        return [
            'policy_version'=>self::VERSION,
            'source'=>self::SOURCE,
            'policy_status'=>'draft_not_ratified',
            'decision'=>'human_review_required',
            'screening_flag'=>$flag,
            'issues'=>$issues,
            'missing_information'=>$incomplete,
            'manual_review_required'=>$reviewNeeded,
            'notes'=>$notes
        ];
    }
}
