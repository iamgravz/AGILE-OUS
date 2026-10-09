<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
use Agile\Membership;
function assertCheck(bool $ok, string $label): void {
    if (!$ok) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    echo "PASS: $label\n";
}
$good=['full_name'=>'Sample Student','email'=>'test@example.invalid','student_number'=>'2026-00001','desired_role'=>'Committee Member','motivation'=>'I want to contribute to the organization.','privacy_consent'=>'yes'];
assertCheck(Membership::validate($good)===[],'valid membership input');
assertCheck(count(Membership::validate(array_merge($good,['privacy_consent'=>'no'])))===1,'privacy consent required');
assertCheck(count(Membership::validate(array_merge($good,['desired_role'=>'admin'])))===1,'invalid role rejected');
assertCheck(count(Membership::validate(array_merge($good,['email'=>'invalid'])))===1,'invalid email rejected');
assertCheck(count(Membership::validate(array_merge($good,['student_number'=>'x'])))===1,'invalid student number rejected');
assertCheck(escape('<script>')==='&lt;script&gt;','HTML escaping');
echo "Isolated checks passed. MySQL integration tests still required.\n";
