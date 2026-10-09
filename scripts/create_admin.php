<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
$email = strtolower(trim((string)($argv[1] ?? '')));
$name = trim((string)($argv[2] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '') { fwrite(STDERR, "Usage: php scripts/create_admin.php email@example.com 'Full Name'\n"); exit(1); }
fwrite(STDOUT, 'Password (input visible): ');
$password = trim((string)fgets(STDIN));
if (strlen($password) < 12) { fwrite(STDERR, "Use a password of at least 12 characters.\n"); exit(1); }
$q = db()->prepare("INSERT INTO users (email, display_name, password_hash, role) VALUES (?, ?, ?, 'msw_head')");
$q->execute([$email,$name,password_hash($password,PASSWORD_DEFAULT)]);
echo "MSW Head account created. Change password input method for production.\n";
