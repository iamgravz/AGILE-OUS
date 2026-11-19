<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $user
 * @var array<string, mixed> $dash
 */
// "Needs your action" already lists what is waiting and for how long, so only counts above zero get a card, in one grid.
// Interviews have their own section further down.
$dash['cards'] = array_values(array_filter(
    [...$dash['cards'], ...$dash['staffCards']],
    static fn (array $card): bool => $card['value'] > 0 && $card['label'] !== 'Interviews this week',
));
?>
<?php $this->start('title') ?>Waiting for your decision<?php $this->end() ?>
<?php $this->start('page_eyebrow') ?>Overview<?php $this->end() ?>
<?php $this->start('page_actions') ?><?= $this->partial('dashboard/_actions') ?><?php $this->end() ?>
<?php $this->start('page_title') ?>Waiting for your decision<?php $this->end() ?>
<?php $this->start('head') ?>
<script src="<?= e(asset('vendor/chartjs/chart.umd.js')) ?>" defer></script>
<script src="<?= e(asset('js/dashboards.js')) ?>" defer></script>
<?php $this->end() ?>
<?= $this->partial('dashboard/_cards', ['user' => $user, 'dash' => $dash]) ?>
<?= $this->partial('dashboard/_charts', ['charts' => $dash['charts']]) ?>
<?= $this->partial('dashboard/_overview', ['dash' => $dash]) ?>
<div class="grid grid--2 dashboard-section">
    <section class="card" aria-labelledby="dashboard-by-status">
        <div class="card__header"><h2 class="card__title" id="dashboard-by-status">Applications by status</h2></div>
        <div class="card__body">
            <?php if ($dash['applicationStatuses'] === []) : ?>
                <p class="text-muted">No applications yet.</p>
            <?php else : ?>
                <dl class="detail-list member-details member-details--counts">
                    <?php foreach ($dash['applicationStatuses'] as $status => $count) : ?>
                        <div><dt><a href="<?= e(route('applications')) ?>?status=<?= e($status) ?>"><?= e(status_label($status)) ?></a></dt><dd><?= e($count) ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </div>
    </section>
    <section class="card" aria-labelledby="dashboard-interviews">
        <div class="card__header"><h2 class="card__title" id="dashboard-interviews">Interviews this week</h2></div>
        <div class="card__body">
            <?php if ($dash['interviews'] === []) : ?>
                <p class="text-muted">No interviews this week.</p>
            <?php else : ?>
                <ul class="case-list case-list--rows">
                    <?php foreach ($dash['interviews'] as $interview) : ?>
                        <li>
                            <a href="<?= e(route('applications.show', ['id' => (int) $interview['application_id']])) ?>?tab=interviews"><?= e($interview['applicant_name']) ?></a>
                            <span class="timeline__meta"><?= e(datetime_ph($interview['scheduled_at'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
</div>
<?= $this->partial('dashboard/_latest_applications', ['applications' => $dash['latestApplications']]) ?>
<?= $this->partial('dashboard/_feeds', ['dash' => $dash]) ?>
