<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';

use Agile\ReleaseReadiness;
function verifyRelease(bool $passed,string $label):void {
    if(!$passed){fwrite(STDERR,"FAIL: $label\n");exit(1);}
    echo "PASS: $label\n";
}
$accept=[
  'all_schema_invariants_passed'=>true,
  'manual_signoffs_pending'=>[
      'final_bylaws_ratified_and_approved',
      'organizational_privacy_notice_lawful_basis_and_retention',
      'external_security_and_accessibility_review'
  ]
];
$integrity=['failed'=>0,'passed'=>12];
$ready=ReleaseReadiness::summarize([],$accept,$integrity);
verifyRelease($ready['technical_checks_passed']===true,
    'All technical requirements can pass independently of governance approval');
verifyRelease($ready['production_release_authorized']===false &&
    $ready['production_release_decision']==='NO_GO_UNTIL_SEPARATE_HUMAN_APPROVAL',
    'Technical success never automatically authorizes a production release');
verifyRelease(count($ready['outstanding_manual_signoffs'])===3 &&
    $ready['policy_status']==='DRAFT_NOT_RATIFIED',
    'Unratified bylaws and human review remain explicit');
$bad=ReleaseReadiness::summarize(['TLS certificate missing'],$accept,$integrity);
verifyRelease($bad['technical_checks_passed']===false &&
    $bad['infrastructure_issue_count']===1,
    'Broken staging infrastructure fails closed');
$bad=ReleaseReadiness::summarize([],array_replace($accept,['all_schema_invariants_passed'=>false]),$integrity);
verifyRelease($bad['technical_checks_passed']===false,
    'Schema integrity failure blocks technical acceptance');
$bad=ReleaseReadiness::summarize([],$accept,['failed'=>1]);
verifyRelease($bad['technical_checks_passed']===false,
    'Any aggregate data integrity issue blocks technical acceptance');
$bad=ReleaseReadiness::summarize([],array_replace($accept,['manual_signoffs_pending'=>[]]),$integrity);
verifyRelease($bad['technical_checks_passed']===false &&
    $bad['production_release_authorized']===false,
    'Missing human-approval checklist is never treated as all signed off');
$payload=json_encode($ready,JSON_THROW_ON_ERROR);
verifyRelease(!str_contains($payload,'@example.invalid') &&
    !str_contains($payload,'password') &&
    !str_contains($payload,'student_number'),
    'Staging handoff JSON contains no sensitive identity or credential fields');
echo "PASS: all Phase 11 release-gate regression checks.\n";
