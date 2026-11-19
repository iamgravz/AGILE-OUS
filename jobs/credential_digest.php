<?php

declare(strict_types=1);

use App\Services\CredentialService;

require __DIR__ . '/_bootstrap.php';

job_run('credential_digest', static function (): array {
    return ['summary' => sprintf('%d digests sent.', (new CredentialService())->sendDigest()), 'failed' => 0];
});
