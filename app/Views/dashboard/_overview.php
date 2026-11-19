<?php
/**
 * Eligibility progress and the optional welfare queue counts, shared by the overview dashboards.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $dash
 */
$eligibility = $dash['eligibility'] ?? null;
$queue = $dash['welfareQueue'] ?? null;
?>
<div class="grid grid--2 dashboard-section">
    <?php if ($queue !== null) : ?>
        <section class="card" aria-labelledby="dashboard-welfare">
            <div class="card__header"><h2 class="card__title" id="dashboard-welfare">Welfare queue</h2></div>
            <div class="card__body">
                <dl class="detail-list member-details member-details--counts">
                    <?php foreach ($queue['priorities'] as $label => $count) : ?>
                        <div><dt><?= e($label) ?> priority</dt><dd><?= e($count) ?></dd></div>
                    <?php endforeach; ?>
                    <div><dt>Not acknowledged after 24 hours</dt><dd><?= e($queue['overdue']) ?></dd></div>
                </dl>
                <p class="member-section"><a href="<?= e(route('welfare.queue')) ?>">Open the welfare queue</a></p>
            </div>
        </section>
    <?php endif; ?>
    <section class="card" aria-labelledby="dashboard-eligibility">
        <div class="card__header"><h2 class="card__title" id="dashboard-eligibility">Eligibility progress</h2></div>
        <div class="card__body">
            <?php if ($eligibility === null) : ?>
                <p class="text-muted">No term is set as current, so screening progress cannot show yet.</p>
            <?php elseif ($eligibility['total'] === 0) : ?>
                <p class="text-muted">Screening has not started for <?= e($eligibility['term']) ?>.</p>
            <?php else : ?>
                <p><?= e($eligibility['term']) ?>: <?= e($eligibility['total']) ?> officers.</p>
                <?php $done = (int) ($eligibility['counts']['eligible'] ?? 0) + (int) ($eligibility['counts']['cleared'] ?? 0); ?>
                <p class="stat-card__sub"><?= e($done) ?> of <?= e($eligibility['total']) ?> cleared or eligible</p>
                <progress class="progress" value="<?= e($done) ?>" max="<?= e($eligibility['total']) ?>" aria-hidden="true"></progress>
                <dl class="detail-list member-details member-details--counts">
                    <?php foreach ($eligibility['counts'] as $result => $count) : ?>
                        <div><dt><?= e(status_label($result)) ?></dt><dd><?= e($count) ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </div>
    </section>
</div>
