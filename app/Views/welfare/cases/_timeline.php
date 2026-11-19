<?php
/**
 * Every event on the case, in the order it happened (FR-WEL-13).
 *
 * @var list<array<string, mixed>> $history
 */
$labels = [
    'status_change' => 'Status changed',
    'category_change' => 'Category changed',
    'priority_change' => 'Priority changed',
    'assigned' => 'Handler assigned',
    'acknowledged' => 'Acknowledged',
    'note_added' => 'Note added',
    'referral' => 'Referral',
    'followup' => 'Follow-up',
    'resolved' => 'Resolved',
    'closed' => 'Closed',
    'disposition' => 'Attachment disposition',
    'viewed' => 'Viewed',
    'overdue_reminder' => 'Overdue reminder sent',
    'linked_to_account' => 'Linked to an account',
];
$readable = static fn (?string $value): string => $value === null ? '' : ucfirst(str_replace('_', ' ', $value));
?>
<section class="plain-section case-sections" aria-labelledby="case-timeline">
    <div class="card__header"><h2 class="card__title" id="case-timeline">Timeline</h2></div>
    <div class="card__body">
        <ol class="timeline">
            <?php foreach ($history as $entry) : ?>
                <li class="timeline__item">
                    <strong><?= e($labels[$entry['event_type']] ?? $readable($entry['event_type'])) ?></strong>
                    <?php if ($entry['new_value'] !== null) : ?>
                        <p class="timeline__note"><?= $entry['old_value'] !== null ? e($readable($entry['old_value'])) . ' to ' : '' ?><?= e($readable($entry['new_value'])) ?></p>
                    <?php endif; ?>
                    <p class="timeline__meta"><?= e(datetime_ph($entry['created_at'])) ?> by <?= e($entry['actor_name'] ?? 'the submitter or the system') ?></p>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>
