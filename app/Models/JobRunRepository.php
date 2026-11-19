<?php

declare(strict_types=1);

namespace App\Models;

final class JobRunRepository extends BaseRepository
{
    private const KEEP_DAYS = 90;

    protected string $table = 'job_runs';
    protected string $primaryKey = 'run_id';

    public function start(string $job): int
    {
        return $this->insert(['job' => $job, 'started_at' => date('Y-m-d H:i:s'), 'result' => 'running']);
    }

    public function finish(int $runId, bool $ok, string $summary): void
    {
        $this->update($runId, ['finished_at' => date('Y-m-d H:i:s'), 'result' => $ok ? 'ok' : 'failed', 'summary' => mb_substr($summary, 0, 255)]);
        $this->pdo()->prepare('DELETE FROM job_runs WHERE started_at < ?')->execute([date('Y-m-d H:i:s', time() - self::KEEP_DAYS * 86400)]);
    }

    /** @return array<string, array<string, mixed>> job => its latest run */
    public function latestByJob(): array
    {
        $rows = $this->pdo()->query(
            'SELECT r.* FROM job_runs r
             JOIN (SELECT job, MAX(run_id) AS run_id FROM job_runs GROUP BY job) latest ON latest.run_id = r.run_id'
        )->fetchAll();

        return array_column($rows, null, 'job');
    }
}
