<?php
/**
 * A position open for application (MembershipService::openPositions): slots meter, requirements, Apply.
 *
 * @var array{position_id: int, title: string, role_type: string, committee_name: string, target_slots: ?int, filled: int, remaining: ?int} $position
 * @var bool $withCommittee (optional, default true)
 */
$withCommittee ??= true;
$target = $position['target_slots'];
$share = $target === null || $target === 0 ? 0 : (int) round(100 * min($position['filled'], $target) / $target);
?>
<article class="px-card px-card--lift px-position">
    <h3><?= e($position['title']) ?></h3>
    <?php if ($withCommittee) : ?><p><?= e($position['committee_name']) ?></p><?php endif; ?>
    <?php if ($target !== null) : ?>
        <div class="px-meter" role="img" aria-label="<?= e($position['filled']) ?> of <?= e($target) ?> filled"><i class="px-w-<?= e(intdiv($share, 10) * 10) ?>"></i></div>
        <span class="px-meta"><?= e($position['filled']) ?> of <?= e($target) ?> filled · <b><?= e($position['remaining']) ?> left</b></span>
    <?php else : ?>
        <span class="px-meta">Open, no set number of slots</span>
    <?php endif; ?>
    <span class="px-chips"><span class="px-chip px-chip--gold">Current COR</span><span class="px-chip px-chip--gold">Previous semester grades</span></span>
    <a class="btn btn--primary btn--sm" href="<?= e(route('apply')) ?>?position=<?= e($position['position_id']) ?>">Apply</a>
</article>
