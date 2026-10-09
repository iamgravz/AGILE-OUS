<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('RUN_E2E_TESTS') !== 'yes') {
    fwrite(STDERR, "Only available in explicit CLI test mode.\n");
    exit(1);
}
require dirname(__DIR__) . '/app/bootstrap.php';
$pdo = db();
$password = getenv('HTTP_TEST_PASSWORD');
if (!is_string($password) || strlen($password) < 12) {
    fwrite(STDERR, "Set HTTP_TEST_PASSWORD for synthetic smoke testing.\n"); exit(1);
}
$email = 'staff-smoke@example.invalid';
$q = $pdo->prepare("INSERT INTO users (email, display_name, password_hash, role)
 VALUES (?, 'Synthetic Smoke Staff', ?, 'msw_head')
 ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), is_active = 1");
$q->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
echo "Created local synthetic smoke staff.\n";
