<?php
/**
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $positions
 */
?>
<?php $this->start('title') ?>Open Positions<?php $this->end() ?>
<?php $this->start('description') ?>Open committee and officer positions at AGILE.<?php $this->end() ?>
<h1>Open positions</h1>
<p class="portal-lead">Committee and officer roles that are accepting applications now.</p>
<?php if ($positions === []) : ?>
    <div class="empty-state">
        <p class="empty-state__text">No open positions this semester. Watch the Announcements page for the next recruitment.</p>
        <a class="btn btn--secondary btn--sm" href="<?= e(route('announcements')) ?>">Go to Announcements</a>
    </div>
<?php else : ?>
    <div class="card-grid">
        <?php foreach ($positions as $position) : ?>
            <?= $this->partial('portal/_position_card', ['position' => $position]) ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
