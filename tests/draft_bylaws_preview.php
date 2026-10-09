<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use Agile\DraftBylawsPolicy as Draft;

function expectDraft(bool $value,string $message): void {
    if (!$value) {fwrite(STDERR,"FAIL: $message\n");exit(1);}
    echo "PASS: $message\n";
}
$base=[
    'role_category'=>'Executive Officer',
    'executive_position'=>'President',
    'year_level'=>3,
    'current_bsit_ous_enrollment'=>true,
    'full_academic_load'=>true,
    'full_history_supplied'=>true,
    'grade_records'=>[['course'=>'IT101','grade'=>'1.5']]
];
$screen=Draft::preview($base);
expectDraft($screen['screening_flag']==='no_flags'&&$screen['decision']==='human_review_required',
    'No flags never equals an automatic eligibility decision');
foreach(['5.0','5.00','F','W','D'] as $mark) {
    $result=Draft::preview(array_replace($base,['grade_records'=>[['course'=>'IT101','grade'=>$mark]]]));
    expectDraft($result['screening_flag']==='review_required','Draft flag recognized: '.$mark);
}
$result=Draft::preview(array_replace($base,['year_level'=>2]));
expectDraft($result['screening_flag']==='review_required',
    'President requires third-year standing under draft');
$result=Draft::preview(array_replace($base,[
    'executive_position'=>'General Secretary','year_level'=>2
]));
expectDraft($result['screening_flag']==='no_flags',
    'Other executive positions use second-year threshold');
$result=Draft::preview(array_replace($base,['full_academic_load'=>false]));
expectDraft($result['screening_flag']==='review_required',
    'Full prescribed load requirement is checked for officer candidacy');
$result=Draft::preview(array_replace($base,['full_history_supplied'=>false]));
expectDraft($result['screening_flag']==='needs_more_information',
    'One semestral grade sheet does not prove whole institutional history');
$result=Draft::preview(array_replace($base,['grade_records'=>[['course'=>'IT101','grade'=>'INC']]]));
expectDraft($result['screening_flag']==='needs_more_information',
    'INC is not automatically treated as a failing mark');
$result=Draft::preview([
    'role_category'=>'The Source Code','full_history_supplied'=>true,
    'grade_records'=>[['course'=>'IT101','grade'=>'F']]
]);
expectDraft($result['screening_flag']==='review_required'
    && str_contains(implode(' ',$result['notes']),'proposed operational extension'),
    'The Source Code checks are marked as an extension, not claimed as a draft clause');
$result=Draft::preview(['role_category'=>'General Member']);
expectDraft($result['screening_flag']==='not_applicable',
    'General Members excluded from semestral screening');
expectDraft($result['policy_status']==='draft_not_ratified',
    'All previews label draft as unratified');
echo "All provisional bylaws preview checks passed with synthetic-only inputs.\n";
