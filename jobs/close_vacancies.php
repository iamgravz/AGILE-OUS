<?php

declare(strict_types=1);

use App\Services\RecruitmentService;

require __DIR__ . '/_bootstrap.php';

job_run('close_vacancies', static function (): array {
    $counts = (new RecruitmentService())->closeDueVacancies(job_log(...));

    return ['summary' => sprintf('%d closed, %d filled, %d failed.', $counts['closed'], $counts['filled'], $counts['failed']), 'failed' => $counts['failed']];
});
