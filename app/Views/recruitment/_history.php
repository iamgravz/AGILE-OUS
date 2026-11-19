<?php
/**
 * The audit trail of an HR request or vacancy, newest first.
 *
 * @var list<array<string, mixed>> $history
 */
$labels = [
    'hr_request.submitted' => 'Submitted',
    'hr_request.resubmitted' => 'Edited and resubmitted',
    'hr_request.approved' => 'Approved',
    'hr_request.returned' => 'Returned',
    'hr_request.cancelled' => 'Cancelled',
    'vacancy.created' => 'Created',
    'vacancy.updated' => 'Draft edited',
    'vacancy.published' => 'Published',
    'vacancy.reopened' => 'Reopened',
    'vacancy.cancelled' => 'Cancelled',
    'vacancy.closed' => 'Closed automatically',
    'vacancy.filled' => 'Marked Filled',
    'vacancy.slot_filled' => 'Slot filled by an appointment',
    'appointment.prepared' => 'Prepared',
    'appointment.approved' => 'Approved',
    'appointment.returned' => 'Returned',
];
?>
<?php if ($history === []) : ?>
    <p class="text-muted">Nothing has been recorded yet.</p>
<?php else : ?>
    <ol class="timeline">
        <?php foreach ($history as $index => $entry) : ?>
            <?php $details = json_decode((string) $entry['details_json'], true) ?: []; ?>
            <li class="timeline__item <?= $index === 0 ? 'timeline__item--current' : '' ?>">
                <strong><?= e($labels[$entry['action']] ?? $entry['action']) ?></strong>
                <p class="timeline__meta"><?= e(datetime_ph($entry['created_at'])) ?> by <?= e($entry['actor_name'] ?? 'the system') ?></p>
                <?php if (isset($details['remarks'])) : ?>
                    <p class="timeline__note"><?= e($details['remarks']) ?></p>
                <?php endif; ?>
                <?php if (isset($details['role_override'])) : ?>
                    <p class="timeline__note">Role set to <?= e($details['role_override']) ?> instead of <?= e($details['role_mapped']) ?>: <?= e($details['override_reason']) ?></p>
                <?php endif; ?>
                <?php if (isset($details['closes_at'])) : ?>
                    <p class="timeline__note">New closing date: <?= e(datetime_ph($details['closes_at'])) ?></p>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>
