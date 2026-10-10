<?php
declare(strict_types=1);

function loadEnv(string $file): void {
    if (!is_file($file)) { return; }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) { continue; }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) { continue; }
        if (getenv($key) === false) { putenv($key . '=' . trim($value, " \t\n\r\0\x0B\"'")); }
    }
}
loadEnv(dirname(__DIR__) . '/.env');
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Manila');
ini_set('display_errors', '0');
ini_set('log_errors', '1');

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) { return $pdo; }
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'agile_ous';
    $user = getenv('DB_USER');
    $password = getenv('DB_PASSWORD');
    if (!$user || !$password) { throw new RuntimeException('Database credentials not configured'); }
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function startSecureSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) { return; }
    $secure = (getenv('SESSION_SECURE') === 'true') || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('AGILEOUSSID');
    session_set_cookie_params(['lifetime'=>0, 'path'=>'/', 'secure'=>$secure, 'httponly'=>true, 'samesite'=>'Lax']);
    ini_set('session.use_strict_mode', '1');
    session_start();
}
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf_token'];
}
function checkCsrf(): void {
    if (!hash_equals(csrfToken(), (string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(419);
        exit('Invalid request token');
    }
}
function escapeHtml(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function authUser(): ?array {
    if (empty($_SESSION['user_id'])) { return null; }
    $stmt = db()->prepare('SELECT id, full_name, role, active FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id'=>(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();
    return ($user && (int) $user['active'] === 1) ? $user : null;
}
function mayViewWelfareSummary(string $role): bool {
    return in_array($role, ['admin','president','welfare_head','welfare_member'], true);
}
function mayManageWelfare(string $role): bool {
    return in_array($role, ['admin','welfare_head'], true);
}
