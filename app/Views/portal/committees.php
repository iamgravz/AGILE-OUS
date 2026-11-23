<?php
/**
 * The seven standing committees (Article V), then The Source Code apart as the publication staff.
 *
 * @var \App\Core\View $this
 * @var array{standing: list<array<string, mixed>>, sourceCode: array<string, mixed>|null} $cards
 * @var array{members: int, committees: int, officers: int, open: int} $facts
 */
$hiring = count(array_filter($cards['standing'], static fn (array $c): bool => $c['slots'] > 0));
?>
<?php $this->start('title') ?>Committees<?php $this->end() ?>
<?php $this->start('description') ?>AGILE's seven standing committees and The Source Code: what each does, who leads it, and where slots are open.<?php $this->end() ?>
<?php $this->start('head') ?><link rel="stylesheet" href="<?= e(asset('css/portal.css')) ?>"><?php $this->end() ?>
<?php $this->start('hero') ?>
<?= $this->partial('portal/_hero', [
    'eyebrow' => 'committees',
    'title' => 'Find where you fit',
    'lead' => 'Seven standing committees run AGILE day to day (Article V). Each is led by a Committee Head and a Deputy, and takes Committee Members every semester.',
    'stats' => [
        [$facts['committees'], 'standing committees'],
        [$hiring, $hiring === 1 ? 'committee hiring now' : 'committees hiring now'],
        [$facts['open'], 'slots open'],
    ],
]) ?>
<?php $this->end() ?>

<section class="px-section" aria-labelledby="standing">
    <header>
        <div><p class="px-eyebrow">article v</p><h2 id="standing">Standing committees</h2></div>
        <a class="btn btn--secondary btn--sm" href="<?= e(route('positions')) ?>">See open positions</a>
    </header>
    <?php if ($cards['standing'] === []) : ?>
        <div class="px-empty">The committee list appears here once the MSW Committee adds it.</div>
    <?php else : ?>
        <ul class="px-grid" role="list">
            <?php foreach ($cards['standing'] as $committee) : ?>
                <li>
                    <a class="px-card" href="<?= e(route('committees.show', ['id' => $committee['committee_id']])) ?>">
                        <div class="px-committee__top">
                            <?= $this->partial('portal/_committee_mark', ['name' => $committee['name']]) ?>
                            <h3><?= e($committee['name']) ?></h3>
                        </div>
                        <p><?= e($committee['description']) ?></p>
                        <span class="px-committee__foot">
                            <span><?= $committee['head'] !== null ? 'Head: ' . e($committee['head']) : 'Head to be named' ?></span>
                            <?php if ($committee['slots'] > 0) : ?>
                                <span class="px-chip px-chip--gold"><?= e($committee['slots']) ?> <?= $committee['slots'] === 1 ? 'slot' : 'slots' ?> open</span>
                            <?php else : ?>
                                <span><?= e($committee['members']) ?> <?= $committee['members'] === 1 ? 'member' : 'members' ?></span>
                            <?php endif; ?>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($cards['sourceCode'] !== null) : ?>
        <?php $tsc = $cards['sourceCode']; ?>
        <div class="px-band">
            <?= $this->partial('portal/_committee_mark', ['name' => $tsc['name']]) ?>
            <div>
                <h3>The Source Code</h3>
                <p>AGILE's student publication: writers, editors, cartoonists, photojournalists and video editors.<?= $tsc['head'] !== null ? ' Led by ' . e($tsc['head']) . '.' : '' ?></p>
            </div>
            <a class="btn btn--primary" href="<?= e(route('committees.show', ['id' => $tsc['committee_id']])) ?>"><?= $tsc['slots'] > 0 ? 'Join the staff' : 'Meet the staff' ?></a>
        </div>
    <?php endif; ?>
</section>
