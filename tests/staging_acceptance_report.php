<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CI')!=='true'||getenv('RUN_E2E_TESTS')!=='yes'){
    fwrite(STDERR,"Acceptance-report tests require an isolated synthetic CI database.\n");exit(1);
}
require dirname(__DIR__).'/app/bootstrap.php';

$report=\Agile\AcceptanceReport::collect(db(),'synthetic_ci');
function acceptanceCheck(bool $yes,string $name):void{
    if(!$yes){fwrite(STDERR,"FAIL: $name\n");exit(1);}
    echo "PASS: $name\n";
}
acceptanceCheck($report['all_schema_invariants_passed'],
    'Database migration and security data invariants hold under synthetic acceptance fixtures');
acceptanceCheck($report['release_gate']==='BLOCKED_PENDING_HUMAN_APPROVAL_AND_REAL_STAGING_VALIDATION'
  && $report['production_release_authorized']===false,
    'Release gate never automatically authorizes production from passing tests');
acceptanceCheck($report['aggregate_operational_indicators']['draft_policies']>=1,
    'Draft bylaws remain explicitly provisional');
acceptanceCheck(count($report['manual_signoffs_pending'])>=8,
    'Actual staging and governance approvals remain outstanding');
$serialized=json_encode($report,JSON_THROW_ON_ERROR);
acceptanceCheck(!str_contains($serialized,'@example.invalid')
  && !str_contains($serialized,'password_hash')
  && !str_contains($serialized,'PHASE10_CONFIDENTIAL_'),
    'Report excludes synthetic applicant identities, private welfare notes and credentials');
echo "Synthetic acceptance metadata checks passed; production release remains BLOCKED.\n";
