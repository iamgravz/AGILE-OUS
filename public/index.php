<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

use Agile\Auth;
use Agile\Membership;

startSession();
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$errors = [];
$notice = '';
function page(string $title, string $body): never {
    $csrf = csrfToken();
    $nav = '<nav><a href="/">AGILE OUS</a> · <a href="/apply">Apply</a> · <a href="/login">Staff Login</a></nav>';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.escape($title).' — AGILE OUS</title><style>body{font-family:system-ui,sans-serif;max-width:780px;margin:2rem auto;padding:0 1rem;background:#faf8f7;color:#251c20}nav{padding:1rem;background:#70102d;color:white;border-radius:8px}nav a{color:white;margin-right:1rem}main{background:white;padding:2rem;border:1px solid #e8dee1;border-radius:10px;margin-top:1rem}label{display:block;margin-top:1rem;font-weight:600}input,select,textarea{display:block;width:100%;box-sizing:border-box;padding:.7rem;border:1px solid #999;border-radius:5px}button{background:#70102d;color:white;border:0;padding:.75rem 1.5rem;border-radius:5px;margin-top:1.3rem;cursor:pointer}a{color:#70102d}.error{color:#9c0020}.notice{background:#e7f4e7;padding:1rem;border-radius:5px}table{width:100%;border-collapse:collapse}td,th{border-bottom:1px solid #ddd;padding:.6rem;text-align:left}</style></head><body>'.$nav.'<main><h1>'.escape($title).'</h1>'.$body.'</main></body></html>';
    exit;
}
function formToken(): string { return '<input type="hidden" name="_csrf" value="'.escape(csrfToken()).'">'; }
function errorHtml(array $errors): string { return $errors ? '<p class="error">'.escape(implode(' ', $errors)).'</p>' : ''; }

try {
    if ($path === '/' && $method === 'GET') {
        page('Welcome to AGILE OUS', '<p>Membership and Student Welfare Management System.</p><p>Apply for AGILE membership through the secure registration form.</p><a href="/apply">Start membership application</a>');
    }
    if ($path === '/apply' && $method === 'GET') {
        page('Membership Application', '<form method="post" action="/apply">'.formToken().'<label>Full name<input required maxlength="150" name="full_name"></label><label>Email<input required type="email" maxlength="190" name="email"></label><label>Student number<input required maxlength="32" name="student_number"></label><label>Desired role<select name="desired_role">'.implode('',array_map(fn($r)=>'<option>'.escape($r).'</option>',Membership::ROLES)).'</select></label><label>Why would you like to join?<textarea required minlength="10" maxlength="2000" name="motivation"></textarea></label><label><input style="display:inline;width:auto" required type="checkbox" name="privacy_consent" value="yes"> I consent to the processing of my submitted information for this application, subject to the published privacy notice.</label><button>Submit Application</button></form>');
    }
    if ($path === '/apply' && $method === 'POST') {
        verifyCsrf();
        try {
            $ref = Membership::submit($_POST);
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
            redirect('/dashboard');
        }
        $q=db()->prepare('INSERT INTO login_attempts (email_hash) VALUES (?)');$q->execute([$hash]);
        http_response_code(401);
        page('Login Failed','<p class="error">Invalid email or password.</p><p><a href="/login">Try again</a></p>');
    }
    if ($path === '/logout' && $method === 'POST') {
        verifyCsrf(); Auth::logout(); redirect('/');
    }
    if ($path === '/dashboard' && $method === 'GET') {
        $user = Auth::requireRole(['msw_head','msw_member','president','admin']);
        if ($user['role'] === 'president' || $user['role'] === 'admin') {
            $stats = db()->query('SELECT status, COUNT(*) AS total FROM membership_applications GROUP BY status')->fetchAll();
            $html = '<p>Signed in as '.escape($user['display_name']).' ('.escape($user['role']).')</p><form method="post" action="/logout">'.formToken().'<button>Sign Out</button></form><h2>Membership summary (no applicant details)</h2><table><tr><th>Status</th><th>Total</th></tr>';
            foreach ($stats as $stat) { $html .= '<tr><td>'.escape($stat['status']).'</td><td>'.(int)$stat['total'].'</td></tr>'; }
            page('Read-only Overview', $html.'</table>');
        }
        $rows = db()->query('SELECT id, reference_code, full_name, desired_role, status, created_at FROM membership_applications ORDER BY created_at DESC LIMIT 30')->fetchAll();
        $html = '<p>Signed in as '.escape($user['display_name']).' ('.escape($user['role']).')</p><form method="post" action="/logout">'.formToken().'<button>Sign Out</button></form><h2>Recent applications</h2><table><tr><th>Reference</th><th>Applicant</th><th>Role</th><th>Status</th></tr>';
        foreach($rows as $r) {
            $html .= '<tr><td>'.escape($r['reference_code']).'</td><td>'.escape($r['full_name']).'</td><td>'.escape($r['desired_role']).'</td><td>'.escape($r['status']).'</td></tr>';
        }
        page('Staff Dashboard', $html.'</table><p>Further permission-restricted workflows will be added in subsequent phases.</p>');
    }
    http_response_code(404);
    page('Not Found','<p>This page does not exist.</p>');
} catch (PDOException $e) {
    error_log('Database error: '.$e->getMessage());
    http_response_code(503);
    page('Service Unavailable','<p>Database service is temporarily unavailable.</p>');
}
