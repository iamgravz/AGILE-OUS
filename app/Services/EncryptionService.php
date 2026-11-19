<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/** AES-256-GCM. Output is base64(iv | tag | ciphertext), with a new random IV for every value. */
final class EncryptionService
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_BYTES = 12;
    private const TAG_BYTES = 16;
    private const KEY_BYTES = 32;

    private readonly string $key;

    /** @param string|null $base64Key Defaults to ENCRYPTION_KEY from .env. */
    public function __construct(?string $base64Key = null)
    {
        $key = base64_decode($base64Key ?? (string) config('app.encryption_key'), true);

        if ($key === false || strlen($key) !== self::KEY_BYTES) {
            throw new RuntimeException('ENCRYPTION_KEY must be 32 bytes in base64. Run: php jobs/generate_key.php');
        }

        $this->key = $key;
    }

    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(self::IV_BYTES);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_BYTES);

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    /** Throws when the value was changed, was encrypted with another key, or is not valid. */
    /**
     * A keyed hash for finding a row by an encrypted value (a guest's email), without decrypting every row.
     * Lower-cased and trimmed, so the same email always gives the same hash. HMAC with a key derived from
     * ENCRYPTION_KEY, so a leaked database cannot be matched against a list of emails.
     */
    public function blindIndex(string $value): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($value)), hash_hmac('sha256', 'agile-blind-index', $this->key, true));
    }

    public function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, true);

        if ($raw === false || strlen($raw) < self::IV_BYTES + self::TAG_BYTES) {
            throw new RuntimeException('Decryption failed.');
        }

        $iv = substr($raw, 0, self::IV_BYTES);
        $tag = substr($raw, self::IV_BYTES, self::TAG_BYTES);
        $ciphertext = substr($raw, self::IV_BYTES + self::TAG_BYTES);
        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($plaintext === false) {
            throw new RuntimeException('Decryption failed.');
        }

        return $plaintext;
    }
}
