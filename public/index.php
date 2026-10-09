<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

use Agile\Auth;
use Agile\Membership;
use Agile\ApplicationForm;
use Agile\ApplicationAdminPage;
use Agile\ApplicationWorkflow;
use Agile\Attachments;

startSession();
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$errors = [];
$notice = '';
function page(string $title, string $body): never {
    $csrf = csrfToken();
    $nav = '<nav><a href="/">AGILE OUS</a> · <a href="/apply">Apply</a> · <a href="/vacancies">Vacancies</a> · <a href="/welfare">Welfare</a> · <a href="/updates">News</a> · <a href="/login">Login</a></nav>';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.escape($title).' — AGILE OUS</title><style>body{font-family:system-ui,sans-serif;max-width:780px;margin:2rem auto;padding:0 1rem;background:#faf8f7;color:#251c20}nav{padding:1rem;background:#70102d;color:white;border-radius:8px}nav a{color:white;margin-right:1rem}main{background:white;padding:2rem;border:1px solid #e8dee1;border-radius:10px;margin-top:1rem}label{display:block;margin-top:1rem;font-weight:600}input,select,textarea{display:block;width:100%;box-sizing:border-box;padding:.7rem;border:1px solid #999;border-radius:5px}button{background:#70102d;color:white;border:0;padding:.75rem 1.5rem;border-radius:5px;margin-top:1.3rem;cursor:pointer}a{color:#70102d}.error{color:#9c0020}.notice{background:#e7f4e7;padding:1rem;border-radius:5px}table{width:100%;border-collapse:collapse}td,th{border-bottom:1px solid #ddd;padding:.6rem;text-align:left}</style></head><body>'.$nav.'<main><h1>'.escape($title).'</h1>'.$body.'</main></body></html>';
    exit;
}
function formToken(): string { return '<input type="hidden" name="_csrf" value="'.escape(csrfToken()).'">'; }
function errorHtml(array $errors): string { return $errors ? '<p class="error">'.escape(implode(' ', $errors)).'</p>' : ''; }

try {
    if ($path === '/' && $method === 'GET') {
        page('Welcome to AGILE OUS', '<p>Membership and Student Welfare Management System.</p><p>Apply for AGILE membership through the secure registration form.</p><a href="/apply">Start membership application</a>');
    }
    if ($path === '/privacy' && $method === 'GET') {
        page('Application Privacy Information', '<p><strong>Purpose:</strong> AGILE OUS membership screening and recruitment administration.</p>'
            . '<p><strong>Information collected:</strong> name, email, student number, role preference, motivation and position-specific answers.</p>'
            . '<p><strong>Access:</strong> designated Membership and Student Welfare staff; other roles see summaries only.</p>'
            . '<p><strong>Important:</strong> This development version is restricted to synthetic demonstration data. The organization must approve the final privacy notice, retention period, lawful basis, and contact details before real student submissions.</p>');
    }
    if ($path === '/apply' && $method === 'GET') {
        page('Membership Application', ApplicationForm::render());
    }
    if ($path === '/apply' && $method === 'POST') {
        verifyCsrf();
        try {
            $input=$_POST; $input['_attachment']=$_FILES['attachment']??null;
            $ref = Membership::submit($input);
            page('Application Submitted', '<p class="notice">Your application has been saved.</p><p>Reference: <strong>'.escape($ref).'</strong></p><p>Keep this reference. Staff will contact you using the submitted email.</p>');
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            page('Application Error','<p class="error">'.escape($e->getMessage()).'</p><p><a href="/apply">Try again</a></p>');
        } catch (PDOException $e) {
            error_log('Application DB error: '.$e->getMessage());
            http_response_code(503);
            page('Unable to Submit','<p>We could not process your application. Please try later or contact MSW.</p>');
        }
    }
    if ($path === '/login' && $method === 'GET') {
        page('Staff Login','<form method="post" action="/login">'.formToken().'<label>Email<input type="email" name="email" required autocomplete="username"></label><label>Password<input type="password" name="password" required autocomplete="current-password"></label><button>Sign In</button></form>');
    }
    if ($path === '/login' && $method === 'POST') {
        verifyCsrf();
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $hash = hash('sha256',$email);
        $q = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE email_hash = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
        $q->execute([$hash]);
        if ((int)$q->fetchColumn() >= 5) { http_response_code(429); page('Try Again Later','<p>Too many attempts. Try again after 15 minutes.</p>'); }
        if (Auth::login($email,(string)($_POST['password'] ?? ''))) {
            $q=db()->prepare('DELETE FROM login_attempts WHERE email_hash = ?');$q->execute([$hash]);
            $signed=Auth::user();
            redirect(match($signed['role']??'') {
                'member'=>'/member/card',
                'committee_head'=>'/staff/hr',
                'source_editor','executive_officer'=>'/staff/content',
                default=>'/dashboard'
            });
        }
        $q=db()->prepare('INSERT INTO login_attempts (email_hash) VALUES (?)');$q->execute([$hash]);
        http_response_code(401);
        page('Login Failed','<p class="error">Invalid email or password.</p><p><a href="/login">Try again</a></p>');
    }
    if ($path === '/logout' && $method === 'POST') {
        verifyCsrf(); Auth::logout(); redirect('/');
    }
    if ($path === '/application' && $method === 'GET') {
        $user = Auth::requireRole(['msw_head','msw_member']);
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!$id) { http_response_code(400); page('Invalid Application', '<p>Invalid application ID.</p>'); }
        try {
            if (!ApplicationWorkflow::findForActor((int)$id, $user)) {
                http_response_code(404); page('Not Found','<p>Application not found.</p>');
            }
        } catch (DomainException $e) {
            http_response_code(403); page('Access Denied','<p>Application is not assigned to your account.</p>');
        }
        page('Application Review', ApplicationAdminPage::render((int)$id, $user));
    }
    if ($path === '/application/assign' && $method === 'POST') {
        $user = Auth::requireRole(['msw_head']);
        verifyCsrf();
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!$id) { http_response_code(400); page('Invalid Application','<p>Invalid application ID.</p>'); }
        $rawReviewer = $_POST['reviewer_id'] ?? '';
        $reviewerId = $rawReviewer === '' ? null : filter_var($rawReviewer, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if ($rawReviewer !== '' && !$reviewerId) { http_response_code(400); page('Invalid Reviewer','<p>Invalid reviewer.</p>'); }
        try {
            ApplicationWorkflow::assignReviewer((int)$id, $user, $reviewerId === null ? null : (int)$reviewerId);
            redirect('/application?id='.(int)$id);
        } catch (DomainException $e) {
            http_response_code(422); page('Assignment Not Accepted', '<p class="error">'.escape($e->getMessage()).'</p>');
        }
    }
    if ($path === '/application/status' && $method === 'POST') {
        $user = Auth::requireRole(['msw_head','msw_member']);
        verifyCsrf();
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!$id) { http_response_code(400); page('Invalid Application','<p>Invalid application ID.</p>'); }
        try {
            ApplicationWorkflow::changeStatus((int)$id, $user,
                (string)($_POST['new_status'] ?? ''), (string)($_POST['note'] ?? ''));
            redirect('/application?id=' . (int)$id);
        } catch (DomainException $e) {
            http_response_code(422);
            page('Review Not Accepted','<p class="error">'.escape($e->getMessage()).'</p><p><a href="/application?id='.(int)$id.'">Return to application</a></p>');
        }
    }
    if ($path === '/application/verification' && $method === 'POST') {
        $user = Auth::requireRole(['msw_head']);
        verifyCsrf();
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!$id) { http_response_code(400); page('Invalid Application','<p>Invalid application ID.</p>'); }
        try {
            ApplicationWorkflow::updateVerification((int)$id, $user,
                ($_POST['interview_completed'] ?? '') === 'yes',
                ($_POST['documents_verified'] ?? '') === 'yes',
                (string)($_POST['note'] ?? ''));
            redirect('/application?id=' . (int)$id);
        } catch (DomainException $e) {
            http_response_code(422);
            page('Verification Not Accepted','<p class="error">'.escape($e->getMessage()).'</p><p><a href="/application?id='.(int)$id.'">Return to application</a></p>');
        }
    }
    if ($path === '/dashboard' && $method === 'GET') {
        $user = Auth::requireRole(['msw_head','msw_member','president','admin']);
        if ($user['role'] === 'president' || $user['role'] === 'admin') {
            $stats = db()->query('SELECT status, COUNT(*) AS total FROM membership_applications GROUP BY status')->fetchAll();
            $html = '<p>Signed in as '.escape($user['display_name']).' ('.escape($user['role']).')</p><form method="post" action="/logout">'.formToken().'<button>Sign Out</button></form><h2>Membership summary (no applicant details)</h2><table><tr><th>Status</th><th>Total</th></tr>';
            foreach ($stats as $stat) { $html .= '<tr><td>'.escape($stat['status']).'</td><td>'.(int)$stat['total'].'</td></tr>'; }
            page('Read-only Overview', $html.'</table>');
        }
        if ($user['role'] === 'msw_member') {
            $q = db()->prepare('SELECT id, reference_code, full_name, desired_role, status, created_at FROM membership_applications WHERE assigned_to = ? ORDER BY created_at DESC LIMIT 30');
            $q->execute([(int)$user['id']]);
            $rows = $q->fetchAll();
        } else {
            $rows = db()->query('SELECT id, reference_code, full_name, desired_role, status, created_at FROM membership_applications ORDER BY created_at DESC LIMIT 30')->fetchAll();
        }
        $html = '<p>Signed in as '.escape($user['display_name']).' ('.escape($user['role']).')</p><form method="post" action="/logout">'.formToken().'<button>Sign Out</button></form><h2>Recent applications</h2><table><tr><th>Reference</th><th>Applicant</th><th>Role</th><th>Status</th></tr>';
        foreach($rows as $r) {
            $html .= '<tr><td><a href="/application?id='.(int)$r['id'].'">'.escape($r['reference_code']).'</a></td><td>'.escape($r['full_name']).'</td><td>'.escape($r['desired_role']).'</td><td>'.escape($r['status']).'</td></tr>';
        }
        page('Staff Dashboard', $html.'</table><p>Further permission-restricted workflows will be added in subsequent phases.</p>');
    }
    if ($path==='/staff/attachment' && $method==='GET') {
        $actor=Auth::requireRole(['msw_head','msw_member']);
        Attachments::retrieve((int)($_GET['id']??0),$actor);
    }
    if ($path==='/staff/attachment/upload' && $method==='POST') {
        $actor=Auth::requireRole(['msw_head','msw_member']);
        verifyCsrf();
        $type=(string)($_POST['owner_type']??'');
        $id=(int)($_POST['owner_id']??0);
        try{
            Attachments::store($_FILES['attachment']??[],$type,$id,$actor);
            $next=$type==='welfare_case'?'/staff/welfare/case?id='.$id:'/application?id='.$id;
            redirect($next);
        }catch(DomainException $e){
            http_response_code(422);page('Document Upload Error','<p class="error">'.escape($e->getMessage()).'</p>');
        }
    }
    require dirname(__DIR__).'/app/routes/recruitment.php';
    require dirname(__DIR__).'/app/routes/welfare.php';
    require dirname(__DIR__).'/app/routes/content.php';
    require dirname(__DIR__).'/app/routes/member.php';
    require dirname(__DIR__).'/app/routes/academic.php';
    require dirname(__DIR__).'/app/routes/email.php';
    require dirname(__DIR__).'/app/routes/identity.php';
    http_response_code(404);
    page('Not Found','<p>This page does not exist.</p>');
} catch (PDOException $e) {
    error_log('Database error: '.$e->getMessage());
    http_response_code(503);
    page('Service Unavailable','<p>Database service is temporarily unavailable.</p>');
}
