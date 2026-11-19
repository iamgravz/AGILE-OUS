<?php

declare(strict_types=1);

use App\Services\EmailQueueService;
use App\Services\Mail\MailerFactory;

require __DIR__ . '/_bootstrap.php';

job_run('send_email_queue', static function (): array {
    $counts = (new EmailQueueService())->run(MailerFactory::make(), job_log(...));

    return ['summary' => sprintf('%d sent, %d retrying, %d failed.', $counts['sent'], $counts['retrying'], $counts['failed']), 'failed' => $counts['failed']];
});
