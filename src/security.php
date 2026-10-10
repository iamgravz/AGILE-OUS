<?php
declare(strict_types=1);

/** Shared per-account throttle; configure APP_KEY in the private .env. */
function loginIdentifier(string $email): string {
    $key = getenv('APP_KEY') ?: '';
    if (strlen($key) < 32) { throw new RuntimeException('APP_KEY configuration required'); }
    return hash_hmac('sha256', strtolower(trim($email)), $key);
}
function tooManyAttempts(string $identifier): bool {
    $stmt = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE identifier_hash = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)');
    $stmt->execute([$identifier]);
    return (int) $stmt->fetchColumn() >= 5;
}
function recordAttempt(string $identifier): void {
    $stmt = db()->prepare('INSERT INTO login_attempts(identifier_hash) VALUES (?)');
    $stmt->execute([$identifier]);
}
function resetAttempts(string $identifier): void {
    $stmt = db()->prepare('DELETE FROM login_attempts WHERE identifier_hash = ?');
    $stmt->execute([$identifier]);
}
function writeAudit(?int $actorId, string $action): void {
    $allowed = ['login_success','logout'];
    if (!in_array($action, $allowed, true)) { throw new InvalidArgumentException('Invalid action'); }
    $stmt = db()->prepare("INSERT INTO audit_events(actor_id, action, resource_type) VALUES (?, ?, 'session')");
    $stmt->execute([$actorId, $action]);
}
