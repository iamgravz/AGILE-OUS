<?php
/**
 * Service requests (MSW): the queue, active first and oldest first, with a filter by status.
 *
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $requests
 * @var array<string, int> $counts
 * @var string $status '' for the active ones
 */
use App\Services\ServiceRequestService;

$tones = ['open' => 'info', 'in_progress' => 'warning', 'resolved' => 'success', 'closed' => 'neutral', 'cancelled' => 'neutral'];
?>
<?php $this->start('title') ?>Service requests<?php $this->end() ?>
<?php $this->start('page_title') ?>Service requests<?php $this->end() ?>

<nav class="cluster request-filters" aria-label="Filter by status">
    <a class="btn btn--sm <?= $status === '' ? 'btn--primary' : 'btn--ghost' ?>" href="<?= e(route('requests.queue')) ?>"<?= $status === '' ? ' aria-current="page"' : '' ?>>Active (<?= $counts['open'] + $counts['in_progress'] ?>)</a>
    <?php foreach (ServiceRequestService::STATUSES as $key => $label) : ?>
        <a class="btn btn--sm <?= $status === $key ? 'btn--primary' : 'btn--ghost' ?>" href="<?= e(route('requests.queue')) ?>?status=<?= e($key) ?>"<?= $status === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?> (<?= $counts[$key] ?>)</a>
    <?php endforeach; ?>
</nav>

<?php if ($requests === []) : ?>
    <div class="empty-state"><p class="empty-state__text"><?= $status === '' ? 'No request is waiting. New ones from members show here.' : 'No requests with this status.' ?></p></div>
<?php else : ?>
    <div class="table-wrap">
        <table class="table table--stack">
            <caption class="sr-only">Service requests</caption>
            <thead><tr><th scope="col">Request</th><th scope="col">From</th><th scope="col">Type</th><th scope="col">Status</th><th scope="col">Handled by</th><th scope="col">Sent</th></tr></thead>
            <tbody>
                <?php foreach ($requests as $row) : ?>
                    <tr>
                        <td data-label="Request"><a href="<?= e(route('requests.show', ['id' => (int) $row['request_id']])) ?>"><code><?= e(ServiceRequestService::code((int) $row['request_id'])) ?></code> <?= e($row['subject']) ?></a> <span class="text-muted">(<?= (int) $row['messages'] ?>)</span></td>
                        <td data-label="From"><?= e($row['requester_name']) ?></td>
                        <td data-label="Type"><?= e(explode(' (', ServiceRequestService::TYPES[$row['type']])[0]) ?></td>
                        <td data-label="Status"><span class="badge badge--<?= e($tones[$row['status']]) ?>"><?= e(ServiceRequestService::STATUSES[$row['status']]) ?></span></td>
                        <td data-label="Handled by"><?= $row['assigned_name'] !== null ? e($row['assigned_name']) : '<span class="text-muted">Nobody yet</span>' ?></td>
                        <td data-label="Sent"><?= e(datetime_ph((string) $row['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
