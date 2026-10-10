<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/src/bootstrap.php';
if ($argc !== 4) { fwrite(STDERR, "Usage: php scripts/create_user.php email role full_name\n"); exit(1); }
[$script, $email, $role, $name] = $argv;
$roles = ['admin','president','membership_head','membership_member','welfare_head','welfare_member'];
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role,$roles,true) || trim($name)==='') {
    fwrite(STDERR, "Invalid email, role or name\n"); exit(1);
}
fwrite(STDOUT, "Password (input visible; use a private terminal): ");
$password = trim((string) fgets(STDIN));
if (strlen($password) < 12) { fwrite(STDERR, "Password must be 12+ characters\n"); exit(1); }
$stmt=db()->prepare('INSERT INTO users (full_name,email,password_hash,role) VALUES (:name,:email,:hash,:role)');
$stmt->execute(['name'=>$name,'email'=>$email,'hash'=>password_hash($password,PASSWORD_DEFAULT),'role'=>$role]);
fwrite(STDOUT, "Account created.\n");
