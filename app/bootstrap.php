<?php
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'Agile\\';
    if (!str_starts_with($class, $prefix)) { return; }
    $path = dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) { require $path; }
});

function envValue(string $key, string $default = ''): string {
    $value = getenv($key);
    return $value === false ? $default : $value;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) { return $pdo; }
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        envValue('DB_HOST', '127.0.0.1'), (int) envValue('DB_PORT', '3306'), envValue('DB_NAME', 'agile_ous'));
    $pdo = new PDO($dsn, envValue('DB_USER', 'agile_app'), envValue('DB_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function startSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) { return; }
    $secure = envValue('SESSION_SECURE') === 'true';
    session_name('AGILESESSID');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
    ini_set('session.use_strict_mode', '1');
    session_start();
}
function csrfToken(): string {
    startSession();
    if (!isset($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf'];
}
function verifyCsrf(): void {
    startSession();
    if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_POST['_csrf'] ?? ''))) {
        http_response_code(419); exit('Invalid request token.');
    }
}
function escape(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $path): never { header('Location: ' . $path, true, 303); exit; }
function audit(?int $actor, string $action, string $entity, ?int $entityId): void {
    $q = db()->prepare('INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id) VALUES (?, ?, ?, ?)');
    $q->execute([$actor,$action,$entity,$entityId]);
}
