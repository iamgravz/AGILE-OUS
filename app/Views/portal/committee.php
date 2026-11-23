<?php
/**
 * One committee: what it does, who leads it, and the positions open in it now.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $committee from PortalService::committeeCard()
 */
$isSourceCode = $committee['name'] === 'The Source Code';
?>
<?php $this->start('title') ?><?= e($committee['name']) ?><?php $this->end() ?>
<?php $this->start('description') ?><?= e($committee['description']) ?><?php $this->end() ?>
<?php $this->start('head') ?><link rel="stylesheet" href="<?= e(asset('css/portal.css')) ?>"><?php $this->end() ?>
<?php $this->start('hero') ?>
<?= $this->partial('portal/_hero', [
    'eyebrow' => $isSourceCode ? 'publication staff' : 'committees / ' . strtolower($committee['name']),
    'title' => $committee['name'],
    'lead' => $committee['description'],
    'stats' => [
        [$committee['members'], $committee['members'] === 1 ? 'member' : 'members'],
        [$committee['slots'], $committee['slots'] === 1 ? 'slot open' : 'slots open'],
    ],
]) ?>
<?php $this->end() ?>

<div class="px-detail">
    <section class="px-section" aria-labelledby="open-here">
        <header><div><p class="px-eyebrow">open now</p><h2 id="open-here">Positions open in this <?= $isSourceCode ? 'staff' : 'committee' ?></h2></div></header>
        <?php if ($committee['open'] === []) : ?>
            <div class="px-empty">
                Nothing is open here right now. The MSW Committee opens positions each semester.
                <br><a class="btn btn--secondary btn--sm" href="<?= e(route('positions')) ?>">See all open positions</a>
            </div>
        <?php else : ?>
            <ul class="px-grid" role="list">
                <?php foreach ($committee['open'] as $position) : ?>
                    <li><?= $this->partial('portal/_open_position', ['position' => $position, 'withCommittee' => false]) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <aside class="px-card" aria-label="Who leads it">
        <p class="px-eyebrow">leads</p>
        <dl class="px-facts">
            <div><dt><?= $isSourceCode ? 'Editor-in-Chief' : 'Committee Head' ?></dt><dd><?= e($committee['head'] ?? 'To be named') ?></dd></div>
            <?php if (!$isSourceCode) : ?>
                <div><dt>Deputy Committee Head</dt><dd><?= e($committee['deputy'] ?? 'To be named') ?></dd></div>
            <?php endif; ?>
            <div><dt>Members</dt><dd><?= e($committee['members']) ?></dd></div>
        </dl>
        <a class="btn btn--ghost btn--sm" href="<?= e(route('committees')) ?>">All committees</a>
    </aside>
</div>
