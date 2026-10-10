<?php
declare(strict_types=1);
function publicSourceHash(): string {
    $secret = getenv('APP_KEY') ?: '';
    if (strlen($secret) < 32) { throw new RuntimeException('APP_KEY not configured'); }
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    // Do not trust user-supplied forwarding headers; configure trusted proxy at the server.
    return hash_hmac('sha256', $ip, $secret);
}
function enforcePublicSubmissionLimit(): void {
    $hash = publicSourceHash();
    $stmt = db()->prepare('SELECT COUNT(*) FROM public_submission_attempts WHERE source_hash=? AND attempted_at >= NOW() - INTERVAL 1 HOUR');
    $stmt->execute([$hash]);
    if ((int)$stmt->fetchColumn() >= 10) {
        http_response_code(429);
        header('Retry-After: 3600');
        exit('Too many applications from this network. Try again later.');
    }
    $stmt = db()->prepare('INSERT INTO public_submission_attempts(source_hash) VALUES(?)');
    $stmt->execute([$hash]);
}
