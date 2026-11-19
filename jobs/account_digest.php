<?php

declare(strict_types=1);

use App\Services\AccountApprovalService;

require __DIR__ . '/_bootstrap.php';

job_run('account_digest', static function (): array {
    return ['summary' => sprintf('%d digests sent.', (new AccountApprovalService())->sendDigest()), 'failed' => 0];
});
