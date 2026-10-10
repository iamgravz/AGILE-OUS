<?php
declare(strict_types=1);
// Generate an offline test-only user row for phpMyAdmin. NEVER upload this script.
if (PHP_SAPI !== 'cli') { exit(1); }
if ($argc !== 4) {
    fwrite(STDERR, "Usage: php scripts/generate_test_user_sql.php demo@example.test membership_head \"Demo Tester\"\n");
    exit(1);
}
[, $email, $role, $name] = $argv;
$roles = ['admin','president','membership_head','membership_member','welfare_head','welfare_member'];
if (!filter_var($email, FILTER_VALIDATE_EMAIL) ||
    !str_ends_with(strtolower($email), '@example.test') ||
    !in_array($role, $roles, true) ||
    trim($name) === '' || strlen($name) > 160) {
    fwrite(STDERR, "Use a synthetic @example.test email, approved role and demo name.\n");
    exit(1);
}
$quote = static fn(string $value): string => "'" . str_replace("'", "''", $value) . "'";
$password = bin2hex(random_bytes(16));
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "TEST ACCOUNT email: $email\nTEST PASSWORD (save privately): $password\n\n";
echo "-- Run this INSERT in InfinityFree phpMyAdmin, then delete any local copies.\n";
echo 'INSERT INTO users (full_name, email, password_hash, role) VALUES (' .
    implode(', ', [$quote($name), $quote(strtolower($email)), $quote($hash), $quote($role)]) . ");\n";
