<?php
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'Agile\\';
    if (!str_starts_with($class, $prefix)) { return; }
    $path = dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) { require $path; }
});

/**
 * Minimal environment loader for local development. Production should inject
 * environment variables through the hosting platform. The .env file is never
 * served because the document root is public/.
 */
function loadLocalEnv(): void {
    $file = dirname(__DIR__) . '/.env';
    if (!is_file($file) || !is_readable($file)) { return; }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) { continue; }
        if (!preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/D', $line, $matches)) { continue; }
        $key = $matches[1];
        if (getenv($key) !== false) { continue; }
        $value = trim($matches[2]);
        if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') ||
            ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }
        putenv($key . '=' . $value);
    }
}
loadLocalEnv();

function envValue(string $key, string $default = ''): string {
    // Only a fixed allowlist of credentials may be loaded from container
    // secret files. No file content is ever logged or returned to a browser.
    // Existing local/test environment variables remain supported.
    static $fileSecrets = [
        'DB_PASSWORD' => true,
        'MFA_KEY_B64' => true,
        'AGILE_BACKUP_KEY_B64' => true,
        'AGILE_FILE_BACKUP_KEY_B64' => true,
    ];
    if (isset($fileSecrets[$key])) {
        $file = getenv($key . '_FILE');
        if ($file !== false && $file !== '') {
            if (getenv($key) !== false) {
                throw new RuntimeException('Conflicting secret configuration for ' . $key);
            }
            if (!is_file($file) || !is_readable($file) || is_link($file)) {
                throw new RuntimeException('Protected secret file unavailable for ' . $key);
            }
            $size = filesize($file);
            if ($size === false || $size < 1 || $size > 4096) {
                throw new RuntimeException('Protected secret file has invalid size for ' . $key);
            }
            $contents = file_get_contents($file);
            if ($contents === false) {
                throw new RuntimeException('Cannot read protected secret file for ' . $key);
            }
            $value = rtrim($contents, "\r\n");
            if ($value === '') {
                throw new RuntimeException('Protected secret value is empty for ' . $key);
            }
            return $value;
        }
    }
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
    $expected = $_SESSION['csrf'] ?? null;
    $provided = $_POST['_csrf'] ?? null;
    // Missing + missing must NEVER compare equal: public POST endpoints may
    // be the first request in a browser session.
    if (!is_string($expected) || !preg_match('/^[a-f0-9]{64}$/D', $expected) ||
        !is_string($provided) || !preg_match('/^[a-f0-9]{64}$/D', $provided) ||
        !hash_equals($expected, $provided)) {
        http_response_code(419);
        exit('Invalid request token.');
    }
}
function escape(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $path): never { header('Location: ' . $path, true, 303); exit; }
function audit(?int $actor, string $action, string $entity, ?int $entityId): void {
    $q = db()->prepare('INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id) VALUES (?, ?, ?, ?)');
    $q->execute([$actor,$action,$entity,$entityId]);
}
