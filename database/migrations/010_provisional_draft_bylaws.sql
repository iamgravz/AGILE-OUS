-- Provisional academic policy from AGILE-OUS Constitution and By-Laws (draft),
-- supplied 2026-09-24. This is a DRAFT, NOT RATIFIED AND NEVER AUTO-APPROVED.
-- Article VI Section 2 covers candidates for elected officer positions.
-- Committee/Source Code per-semester verification is a proposed workflow extension,
-- not a quoted eligibility clause from Article VI Section 2.
INSERT IGNORE INTO eligibility_policies (policy_version,criteria_json,is_approved)
VALUES (
 'DRAFT-2026-09-24-ARTICLE-VI-2',
 JSON_OBJECT(
  'source_document','AGILE - OUS CONSTITUTION AND BY-LAWS (draft).docx',
  'source_date','2026-09-24',
  'source_article','Article VI Section 2 — Candidate Qualifications',
  'status','draft_not_ratified',
  'scope','officer_candidate_qualification',
  'minimum_year_president_or_vice_president',3,
  'minimum_year_other_executive',2,
  'requires_current_bsit_ous_enrollment',true,
  'requires_prescribed_full_academic_load',true,
  'grade_history_lookback','during_entire_stay_in_institution',
  'flag_codes',JSON_ARRAY('5.0','F','W','D'),
  'requires_good_academic_standing',true,
  'requires_good_moral_character',true,
  'requires_no_pending_disciplinary_cases',true,
  'manual_review_required',true,
  'proposed_operational_extension',JSON_OBJECT(
    'frequency','every_semester',
    'covered_roles',JSON_ARRAY('Executive Officer','Committee Head','Deputy Committee Head','Committee Member','The Source Code'),
    'excluded_roles',JSON_ARRAY('General Member'),
    'source','working_system_requirement_not_explicit_article_vi_clause'
  ),
  'unresolved',JSON_ARRAY(
    'Final ratification and PUP approval',
    'Scope of committee and publication role eligibility',
    'General vs Regular Member terminology',
    'Handling of incomplete marks and course retakes',
    'Required evidence and authorized due-process role transition'
  )
 ),
 FALSE
);
