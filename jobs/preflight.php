<?php

declare(strict_types=1);

/*
 * Go-live check (Nov 23, 2026). Run on the server before opening AGILE to real users:
 *
 *   php jobs/preflight.php
 *
 * It only reads settings, the database and folders; it changes nothing. Each line says PASS, WARN or FAIL and what
 * to do. It exits with 1 when anything fails, so a deployment script can stop there.
 */

use App\Core\Config;
use App\Core\Database;

require dirname(__DIR__) . '/database/bootstrap.php';

$results = [];
$check = static function (string $status, string $what, string $todo = '') use (&$results): void {
    $results[] = [$status, $what, $todo];
};

$env = (string) config('app.env');
$url = (string) config('app.url');
$check($env === 'production' ? 'PASS' : 'FAIL', "APP_ENV is {$env}", 'Set APP_ENV=production.');
$check(config('app.debug') === true ? 'FAIL' : 'PASS', 'APP_DEBUG is ' . (config('app.debug') === true ? 'on' : 'off'), 'Set APP_DEBUG=false.');
$check(str_starts_with($url, 'https://') ? 'PASS' : 'FAIL', "APP_URL is {$url}", 'Use the https:// address, with no trailing slash.');
$check(strlen((string) env('ENCRYPTION_KEY', '')) >= 40 ? 'PASS' : 'FAIL', 'ENCRYPTION_KEY is set', 'Run php jobs/generate_key.php once, keep a copy safe, never change it after data exists.');
$check(config('app.mail.driver') === 'smtp' ? 'PASS' : 'FAIL', 'MAIL_DRIVER is ' . (string) config('app.mail.driver'), 'Set MAIL_DRIVER=smtp with the SMTP_* values, or no email (codes, notices) will reach anyone.');
$check((string) env('DB_USER', '') === 'root' ? 'WARN' : 'PASS', 'Database user is ' . (string) env('DB_USER', ''), 'Use a user for agile_ams only, not root.');
$check((string) env('DB_PASS', '') === '' ? 'FAIL' : 'PASS', 'Database password is set', 'Give the database user a strong password.');

try {
    $db = Database::connection();
    $demo = (int) $db->query("SELECT COUNT(*) FROM users WHERE email LIKE '%@agile.test'")->fetchColumn()
        + (int) $db->query("SELECT COUNT(*) FROM applicants WHERE email LIKE '%@agile.test'")->fetchColumn();
    $check($demo === 0 ? 'PASS' : 'FAIL', "Demo people in the database: {$demo}", 'Start from a new database, or run php database/remove_demo.php --yes.');
    $demoPosts = (int) $db->query("SELECT COUNT(*) FROM content_posts WHERE title LIKE '[Demo]%'")->fetchColumn();
    $check($demoPosts === 0 ? 'PASS' : 'FAIL', "Demo posts: {$demoPosts}", 'Remove them with remove_demo, or delete them in Content.');
    $heads = (int) $db->query("SELECT COUNT(*) FROM users u JOIN roles r ON r.role_id = u.role_id WHERE r.slug = 'msw_head' AND u.status = 'active' AND u.email NOT LIKE '%@agile.test'")->fetchColumn();
    $check($heads > 0 ? 'PASS' : 'FAIL', "Real MSW Committee Head accounts: {$heads}", 'Create one with php jobs/create_user.php.');
    $pending = (int) $db->query("SELECT COUNT(*) FROM schema_migrations")->fetchColumn();
    $files = count(glob(dirname(__DIR__) . '/database/migrations/*.{php,sql}', GLOB_BRACE) ?: []);
    $check($pending >= $files ? 'PASS' : 'FAIL', "Migrations applied: {$pending} of {$files}", 'Run php database/setup.php.');
} catch (Throwable $exception) {
    $check('FAIL', 'Database connection', $exception->getMessage());
}

foreach (['uploads', 'credentials', 'backups', 'logs'] as $folder) {
    $path = Config::basePath("storage/{$folder}");
    $check(is_dir($path) && is_writable($path) ? 'PASS' : 'FAIL', "storage/{$folder} is writable", "Create it and let the web server write to it.");
}

$check(is_file(dirname(__DIR__) . '/public/.htaccess') ? 'PASS' : 'WARN', 'public/.htaccess is present', 'Needed on Apache; on Nginx, point the root at public/ and deny dotfiles.');
$check(PHP_VERSION_ID >= 80200 ? 'PASS' : 'FAIL', 'PHP ' . PHP_VERSION, 'Use PHP 8.2 or newer.');

foreach (['pdo_mysql', 'openssl', 'mbstring', 'fileinfo', 'gd'] as $extension) {
    $check(extension_loaded($extension) ? 'PASS' : 'FAIL', "PHP extension {$extension}", "Enable {$extension} in php.ini.");
}

$failed = 0;

foreach ($results as [$status, $what, $todo]) {
    $failed += $status === 'FAIL' ? 1 : 0;
    echo str_pad($status, 5) . ' ' . $what . ($status === 'PASS' || $todo === '' ? '' : "\n      -> {$todo}") . PHP_EOL;
}

echo PHP_EOL . ($failed === 0 ? 'Ready to go live.' : "{$failed} check(s) failed. Fix them before going live.") . PHP_EOL;
exit($failed === 0 ? 0 : 1);
