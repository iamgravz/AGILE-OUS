<?php
/**
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $jobs
 * @var int $queued
 * @var int $failed
 * @var int $retryable
 * @var array{file: string, size: int, modified: int}|null $backup
 */
$purposes = [
    'send_email_queue' => 'Sends queued emails',
    'close_vacancies' => 'Closes vacancies past their date',
    'backup_database' => 'Backs up the database',
    'cleanup_expired' => 'Deletes expired codes, links and tokens',
    'account_digest' => 'Tells the MSW Committee Head about waiting accounts',
    'credential_digest' => 'Tells the MSW Committee Head about waiting IDs and certificates',
    'role_expiration_reminders' => 'Reminds officers without documents',
    'welfare_followups' => 'Sends welfare follow-ups that are due',
    'welfare_followups_urgent' => 'Flags urgent welfare cases not acknowledged',
    'interview_reminders' => 'Reminds people of interviews',
    'eligibility_reminders' => 'Reminds officers to submit for screening',
    'application_reminders' => 'Sends application reminders',
];
$jobFailed = count(array_filter($jobs, static fn (array $row): bool => ($row['run']['result'] ?? '') === 'failed'));
$jobLate = count(array_filter($jobs, static fn (array $row): bool => $row['run'] !== null && $row['run']['result'] === 'ok' && $row['late']));
$jobNever = count(array_filter($jobs, static fn (array $row): bool => $row['run'] === null));
$problems = [];

if ($jobFailed > 0) {
    $problems[] = $jobFailed . ($jobFailed === 1 ? ' job failed on its last run' : ' jobs failed on their last run');
}

if ($jobLate > 0) {
    $problems[] = $jobLate . ($jobLate === 1 ? ' job is late' : ' jobs are late');
}

if ($jobNever > 0) {
    $problems[] = $jobNever . ($jobNever === 1 ? ' job has not run yet' : ' jobs have not run yet');
}
?>
<?php $this->start('title') ?>System health<?php $this->end() ?>
<?php $this->start('page_title') ?>System health<?php $this->end() ?>

<?php if ($problems === []) : ?>
    <p>Every scheduled job ran on time. <span class="text-muted">Checked <?= e(datetime_ph(date('Y-m-d H:i:s'))) ?>.</span></p>
<?php else : ?>
    <div class="alert alert--<?= $jobFailed > 0 ? 'danger' : 'warning' ?> welfare-notice" role="status">
        <?= icon('alert') ?>
        <p class="alert__text"><?= e(ucfirst(implode(', ', $problems))) ?>. Checked <?= e(datetime_ph(date('Y-m-d H:i:s'))) ?>.</p>
    </div>
<?php endif; ?>

<h2>Scheduled jobs</h2>
<div class="table-wrap table-wrap--full dashboard-section">
    <table class="table table--stack table--codes">
        <caption class="sr-only">Each scheduled job and its last run</caption>
        <thead><tr><th scope="col">Job</th><th scope="col">Schedule</th><th scope="col">Last run</th><th scope="col">Result</th><th scope="col">Summary</th></tr></thead>
        <tbody>
            <?php foreach ($jobs as $row) : ?>
                <?php $run = $row['run']; ?>
                <tr>
                    <td data-label="Job"><div><?= e($purposes[$row['job']] ?? '') ?> <span class="job-code"><code><?= e($row['job']) ?></code></span></div></td>
                    <td data-label="Schedule"><?= e($row['schedule']) ?></td>
                    <td data-label="Last run"><?= $run === null ? 'Never' : e(datetime_ph($run['started_at'])) ?></td>
                    <td data-label="Result">
                        <?php if ($run === null) : ?>
                            <span class="badge badge--neutral">Not run yet</span>
                        <?php elseif ($run['result'] === 'failed') : ?>
                            <span class="badge badge--danger">Failed</span>
                        <?php elseif ($run['result'] === 'running') : ?>
                            <span class="badge badge--info">Running</span>
                        <?php elseif ($row['late']) : ?>
                            <span class="badge badge--warning">Late</span>
                        <?php else : ?>
                            <span class="badge badge--success">OK</span>
                        <?php endif; ?>
                    </td>
                    <td<?= ($run['summary'] ?? '') === '' ? ' class="is-empty"' : '' ?> data-label="Summary"><span class="truncate" title="<?= e($run['summary'] ?? '') ?>"><?= e($run['summary'] ?? '') ?></span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="grid grid--2">
    <section class="card" aria-labelledby="health-email">
        <div class="card__header"><h2 class="card__title" id="health-email">Email queue</h2></div>
        <div class="card__body">
            <dl class="detail-list">
                <div><dt>Waiting to send</dt><dd class="num"><?= e($queued) ?></dd></div>
                <div><dt>Failed</dt><dd class="num"><?= e($failed) ?></dd></div>
            </dl>
            <?php if ($failed > 0) : ?>
                <form class="admin-actions" method="post" action="<?= e(route('admin.system_health.retry')) ?>">
                    <?= csrf_field() ?>
                    <?php if ($retryable < $failed) : ?>
                        <p class="text-muted"><?= e($failed - $retryable) ?> cannot be retried, because the one-time link or code was erased. The person can ask for a new one.</p>
                    <?php endif; ?>
                    <button type="submit" class="btn btn--primary"<?= $retryable === 0 ? ' disabled' : '' ?>>Retry <?= e($retryable) ?> failed <?= $retryable === 1 ? 'email' : 'emails' ?></button>
                </form>
            <?php endif; ?>
        </div>
    </section>
    <section class="card" aria-labelledby="health-backup">
        <div class="card__header"><h2 class="card__title" id="health-backup">Latest backup</h2></div>
        <div class="card__body">
            <?php if ($backup === null) : ?>
                <p class="text-muted">No backup yet. The first one appears after <code>backup_database</code> runs.</p>
            <?php else : ?>
                <dl class="detail-list">
                    <div><dt>File</dt><dd><code><?= e($backup['file']) ?></code></dd></div>
                    <div><dt>Size</dt><dd class="num"><?= e($backup['size'] >= 1048576 ? number_format($backup['size'] / 1048576, 1) . ' MB' : number_format($backup['size'] / 1024, 1) . ' KB') ?></dd></div>
                    <div><dt>Made</dt><dd><?= e(datetime_ph(date('Y-m-d H:i:s', $backup['modified']))) ?></dd></div>
                </dl>
                <p class="field__help">Kept for <?= e(\App\Services\BackupService::KEEP_DAYS) ?> days. This holds the database only, so keep your own copies of the uploaded files and the .env file.</p>
            <?php endif; ?>
        </div>
    </section>
</div>
