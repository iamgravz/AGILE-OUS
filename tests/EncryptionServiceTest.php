<?php

declare(strict_types=1);

use App\Services\EncryptionService;
use PHPUnit\Framework\TestCase;

/** Uses its own random keys, so it does not depend on .env. */
final class EncryptionServiceTest extends TestCase
{
    private EncryptionService $service;

    protected function setUp(): void
    {
        $this->service = new EncryptionService(base64_encode(random_bytes(32)));
    }

    public function testRoundTrip(): void
    {
        foreach (['Confidential case note', '', 'Ñandú 中文 🙂', str_repeat('long text ', 5000), "line one\nline two\0"] as $plaintext) {
            $this->assertSame($plaintext, $this->service->decrypt($this->service->encrypt($plaintext)));
        }
    }

    public function testOutputDoesNotContainThePlaintext(): void
    {
        $encrypted = $this->service->encrypt('Confidential case note');

        $this->assertStringNotContainsString('Confidential', (string) base64_decode($encrypted, true));
    }

    public function testEachValueGetsARandomIv(): void
    {
        $this->assertNotSame($this->service->encrypt('same text'), $this->service->encrypt('same text'));
    }

    public function testOutputFormatIsIvThenTagThenCiphertext(): void
    {
        $raw = base64_decode($this->service->encrypt('12345'), true);

        $this->assertSame(12 + 16 + 5, strlen($raw));
    }

    public function testChangingAnyPartIsDetected(): void
    {
        $raw = base64_decode($this->service->encrypt('Confidential case note'), true);

        foreach ([0, 12, strlen($raw) - 1] as $position) {
            $tampered = $raw;
            $tampered[$position] = $tampered[$position] ^ "\x01";

            try {
                $this->service->decrypt(base64_encode($tampered));
                $this->fail("A change at byte {$position} was not detected.");
            } catch (RuntimeException $exception) {
                $this->assertSame('Decryption failed.', $exception->getMessage());
            }
        }
    }

    public function testAnotherKeyCannotDecrypt(): void
    {
        $encrypted = $this->service->encrypt('Confidential');
        $other = new EncryptionService(base64_encode(random_bytes(32)));

        $this->expectException(RuntimeException::class);
        $other->decrypt($encrypted);
    }

    public function testInvalidInputThrows(): void
    {
        foreach (['', 'not base64!!', base64_encode('short')] as $input) {
            try {
                $this->service->decrypt($input);
                $this->fail('Invalid input was accepted.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Decryption failed.', $exception->getMessage());
            }
        }
    }

    public function testWrongKeyLengthIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        new EncryptionService(base64_encode(random_bytes(16)));
    }
}
