<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/public_submission_guard.php';
startSecureSession();
if (getenv('APPLICATIONS_OPEN') !== 'true') {
    http_response_code(503);
    header('Cache-Control: no-store');
    exit('Recruitment applications are currently closed.');
}
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
$notice = '';
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    enforcePublicSubmissionLimit();
    $name = trim((string)($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $academicYear = trim((string)($_POST['academic_year'] ?? ''));
    $semester = trim((string)($_POST['semester'] ?? ''));
    $position = trim((string)($_POST['position'] ?? ''));
    $consent = ($_POST['consent'] ?? '') === 'yes';
    if (strlen($name) < 3 || strlen($name) > 160 || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 190 || !preg_match('/^20[0-9]{2}-20[0-9]{2}$/', $academicYear)
        || !in_array($semester, ['1st','2nd','Summer'], true)
        || strlen($position) > 120 || !$consent) {
        $notice = 'Please check your information and consent.';
    } else {
        try {
            $reference = strtoupper(bin2hex(random_bytes(12)));
            $stmt = db()->prepare("INSERT INTO membership_applications
                (application_reference,applicant_name,applicant_email,academic_year,semester,requested_position,consent_at)
                VALUES (:ref,:name,:email,:year,:sem,:position,NOW())");
            $stmt->execute(['ref'=>$reference,'name'=>$name,'email'=>$email,'year'=>$academicYear,
                'sem'=>$semester,'position'=>$position !== '' ? $position : null]);
            $notice = 'Your application was received. Keep this reference: ' . $reference;
            $success = true;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $notice = 'An application may already exist for this email and academic period.';
            } else { throw $e; }
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Apply | AGILE OUS</title><style>
body{font:16px system-ui;background:#faf8f5;color:#26202a;margin:0}header{background:#670c24;color:white;padding:1.25rem}
main{max-width:650px;margin:2rem auto;padding:1.6rem;background:white;border-radius:12px}
label{display:block;margin:1rem 0}input,select{box-sizing:border-box;width:100%;padding:.75rem;border:1px solid #aaa;border-radius:5px}
input[type=checkbox]{width:auto}button{background:#670c24;color:#fff;border:0;padding:.85rem 1.3rem;border-radius:7px;cursor:pointer}
aside{background:#f5efe9;padding:1rem;border-radius:5px}</style></head><body>
<header><strong>AGILE OUS</strong> — Membership Application</header><main>
<h1>Membership Application</h1><p>For BSIT PUP Open University applicants. Applying does not create a login account.</p>
<?php if ($notice): ?><aside role="status"><?= escapeHtml($notice) ?></aside><?php endif; ?>
<?php if (!$success): ?><form method="post">
<input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
<label>Full name <input name="full_name" maxlength="160" required></label>
<label>Email <input name="email" type="email" maxlength="190" required></label>
<label>Academic year <input name="academic_year" placeholder="2026-2027" pattern="20[0-9]{2}-20[0-9]{2}" required></label>
<label>Semester <select name="semester" required><option value="">Select</option><option value="1st">First</option><option value="2nd">Second</option><option value="Summer">Summer</option></select></label>
<label>Requested position (optional) <input name="position" maxlength="120"></label>
<p><strong>Privacy notice:</strong> Your submitted name, email, academic period, position preference and consent timestamp will be stored for application review by authorized membership personnel. This is a development form; retention/contact and privacy-rights procedures must be approved before public launch.</p>
<label><input type="checkbox" name="consent" value="yes" required> I acknowledge this privacy notice and agree to submit my application.</label>
<button type="submit">Submit application</button></form><?php endif; ?>
</main></body></html>
