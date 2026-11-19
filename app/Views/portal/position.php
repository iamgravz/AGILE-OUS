<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $position an open vacancy
 */
use App\Services\MembershipService;

$left = max(0, (int) $position['slots'] - (int) $position['slots_filled']);
$canApply = current_user_id() === null || can('application.submit');
?>
<?php $this->start('title') ?><?= e($position['title']) ?><?php $this->end() ?>
<?php $this->start('description') ?><?= e('Apply for ' . $position['title'] . ' at AGILE by ' . date_ph($position['closes_at']) . '.') ?><?php $this->end() ?>
<?php $this->start('docs_group') ?>Open positions<?php $this->end() ?>
<?php $this->start('docs_related') ?><li><a href="<?= e(route('positions')) ?>">Back to Open Positions</a></li><?php $this->end() ?>
<?php $this->start('docs_eyebrow') ?>Open position<?php $this->end() ?>
<?php $this->start('docs_title') ?><?= e($position['title']) ?><?php $this->end() ?>
<?php if (!empty($position['updated_at'])) : ?><?php $this->start('docs_updated') ?>Last updated <?= e(date_ph($position['updated_at'])) ?><?php $this->end() ?><?php endif; ?>
<?php $this->start('docs_context') ?>
<div class="card">
    <div class="card__header"><h2 class="card__title">Apply</h2></div>
    <div class="card__body">
        <dl class="detail-list">
            <?php if ($position['committee_name'] !== null) : ?>
                <div><dt>Committee</dt><dd><?= e($position['committee_name']) ?></dd></div>
            <?php endif; ?>
            <div><dt>Slots left</dt><dd><?= e($left) ?> of <?= e($position['slots']) ?></dd></div>
            <div><dt>Apply by</dt><dd><?= e(datetime_ph($position['closes_at'])) ?></dd></div>
        </dl>
        <h3 class="docs-card__heading">Required documents</h3>
        <ul class="docs-card__list">
            <li><?= e(MembershipService::documentLabel('school_id')) ?></li>
            <li><?= e(MembershipService::documentLabel('cor')) ?></li>
        </ul>
        <?php if ($canApply && current_user_id() === null) : ?>
            <p>Positions are open to AGILE members.</p>
            <div class="cluster">
                <a class="btn btn--primary" href="<?= e(route('login')) ?>">Log in to apply</a>
                <a class="btn btn--secondary" href="<?= e(route('apply')) ?>">Apply for membership first</a>
            </div>
        <?php elseif ($canApply) : ?>
            <a class="btn btn--primary" href="<?= e(route('apply')) ?>?<?= e(http_build_query(['vacancy' => $position['vacancy_id']])) ?>">Apply for this position</a>
        <?php endif; ?>
    </div>
</div>
<?php $this->end() ?>
<div class="article__body">
    <h2>About this position</h2>
    <p class="portal-pre"><?= e($position['description']) ?></p>
    <?php if ((string) $position['qualifications'] !== '') : ?>
        <h2>Qualifications</h2>
        <p class="portal-pre"><?= e($position['qualifications']) ?></p>
    <?php endif; ?>
</div>
