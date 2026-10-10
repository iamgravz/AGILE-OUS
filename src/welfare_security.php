<?php
declare(strict_types=1);

/** Never print keys or plaintext to logs. Keep the key outside the repository. */
function welfareKey(): string {
    $raw = getenv('WELFARE_ENCRYPTION_KEY') ?: '';
    if (!preg_match('/^[a-f0-9]{64}$/i', $raw)) {
        throw new RuntimeException('WELFARE_ENCRYPTION_KEY must be 64 hex characters');
    }
    return hex2bin($raw);
}
function encryptWelfare(string $plaintext): string {
    $nonce = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', welfareKey(), OPENSSL_RAW_DATA, $nonce, $tag, 'AGILE-OUS-WELFARE-v1', 16);
    if ($ciphertext === false) { throw new RuntimeException('Encryption failure'); }
    return base64_encode($nonce . $tag . $ciphertext);
}
function decryptWelfare(string $encoded): string {
    $raw = base64_decode($encoded, true);
    if ($raw === false || strlen($raw) < 29) { throw new RuntimeException('Invalid encrypted record'); }
    $value = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', welfareKey(), OPENSSL_RAW_DATA,
        substr($raw, 0, 12), substr($raw, 12, 16), 'AGILE-OUS-WELFARE-v1');
    if ($value === false) { throw new RuntimeException('Unable to decrypt record'); }
    return $value;
}
function welfareCategories(): array {
    return ['Academic','Financial & Resources','Well-being/Personal Support',
        'Skills & Self-Improvement','Accessibility','Safety/Conduct','Community Concerns','Others'];
}
function canReadWelfareCase(array $actor, array $case): bool {
    $role = (string)$actor['role'];
    if (in_array($role, ['welfare_head','admin'], true)) { return true; }
    return $role === 'welfare_member' &&
        ((int)($case['created_by'] ?? 0) === (int)$actor['id'] ||
         (int)($case['assigned_to'] ?? 0) === (int)$actor['id']);
}
