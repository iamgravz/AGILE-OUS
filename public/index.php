<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
startSecureSession();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store, private');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'logout') {
        $_SESSION = [];
        session_regenerate_id(true);
        header('Location: /');
        exit;
    }
    if ($action === 'login') {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if (strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($password) <= 4096) {
            $stmt = db()->prepare('SELECT id, password_hash, active FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email'=>$email]);
            $row = $stmt->fetch();
            if ($row && (int) $row['active'] === 1 && password_verify($password, $row['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $row['id'];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header('Location: /');
                exit;
            }
        }
        $error = 'Invalid email or password.';
    }
}
$user = authUser();
$stats = [];
if ($user) {
    if (in_array((string) $user['role'], ['admin','president','membership_head','membership_member'], true)) {
        $stats['approved'] = (int) db()->query("SELECT COUNT(*) FROM membership_applications WHERE status = 'approved'")->fetchColumn();
        $stats['pending'] = (int) db()->query("SELECT COUNT(*) FROM membership_applications WHERE status = 'pending'")->fetchColumn();
    }
    if (mayViewWelfareSummary((string) $user['role'])) {
        $stats['open_welfare'] = (int) db()->query("SELECT COUNT(*) FROM welfare_cases WHERE status NOT IN ('resolved','closed')")->fetchColumn();
    }
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>AGILE OUS | Secure Dashboard</title>
<style>
:root{font-family:system-ui,sans-serif;color:#25212a;background:#faf8f5}
body{margin:0}header{background:#670c24;color:#fff;padding:1.3rem 2rem}
main{max-width:950px;margin:3rem auto;padding:0 1rem}.panel{background:#fff;padding:2rem;border:1px solid #e6dfdc;border-radius:14px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:1rem}
.stat{border:1px solid #eee;border-radius:10px;padding:1.3rem}.stat strong{font-size:2.4rem;display:block}
input,button{font:inherit;padding:.75rem;border-radius:7px}input{width:100%;box-sizing:border-box;border:1px solid #aaa;margin:.4rem 0 1rem}
button{border:0;background:#670c24;color:#fff;cursor:pointer}.muted{color:#555}
</style></head><body>
<header><strong>AGILE OUS</strong> — Membership &amp; Student Welfare</header>
<main>
<?php if (!$user): ?>
<section class="panel"><h1>Staff Sign In</h1>
<p class="muted">Authorized organizational personnel only.</p>
<?php if ($error): ?><p role="alert"><?= escapeHtml($error) ?></p><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
<input type="hidden" name="action" value="login">
<label>Email<input type="email" name="email" autocomplete="username" required maxlength="190"></label>
<label>Password<input type="password" name="password" autocomplete="current-password" required></label>
<button type="submit">Sign In</button></form></section>
<?php else: ?>
<section class="panel"><h1>Dashboard</h1>
<p>Welcome, <?= escapeHtml((string) $user['full_name']) ?>. Role: <?= escapeHtml((string) $user['role']) ?></p>
<div class="grid">
<?php if (isset($stats['approved'])): ?><div class="stat">Approved applications<strong><?= $stats['approved'] ?></strong></div>
<div class="stat">Pending applications<strong><?= $stats['pending'] ?></strong></div><?php endif; ?>
<?php if (isset($stats['open_welfare'])): ?><div class="stat">Open welfare cases (count only)<strong><?= $stats['open_welfare'] ?></strong></div><?php endif; ?>
</div>
<p class="muted">Read-only phase 1 summary. No case narratives or applicant documents are shown.</p>
<form method="post"><input type="hidden" name="action" value="logout">
<input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>"><button>Sign Out</button></form></section>
<?php endif; ?>
</main></body></html>
