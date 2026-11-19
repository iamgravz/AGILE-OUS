<?php
/**
 * An open vacancy. The whole card is one link, so Apply is part of it and not a second link.
 *
 * @var array<string, mixed> $position
 */
use App\Services\MembershipService;

$left = max(0, (int) $position['slots'] - (int) $position['slots_filled']);
?>
<a class="card card--link post-card" href="<?= e(route('positions.show', ['id' => $position['vacancy_id']])) ?>">
    <span class="post-card__body">
        <span class="post-card__title" title="<?= e($position['title']) ?>"><?= e($position['title']) ?></span>
        <span class="post-card__line"><?= e($position['committee_name'] ?? 'Executive') ?></span>
        <span class="post-card__line"><?= e($left) ?> <?= $left === 1 ? 'slot' : 'slots' ?> left</span>
        <span class="post-card__meta">Apply by <?= e(date_ph($position['closes_at'])) ?></span>
        <span class="post-card__line post-card__docs">Required: <?= e(MembershipService::documentLabel('school_id')) ?>, <?= e(MembershipService::documentLabel('cor')) ?></span>
        <span class="btn btn--primary btn--sm post-card__action">Apply</span>
    </span>
</a>
