<?php
/**
 * Open Positions: what the MSW Committee Head opened for application, with slots left, what to prepare, and Apply.
 * Vacancies raised by committee heads (HR requests) follow, when there are any.
 *
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $open the positions shown, after the role filter
 * @var list<array<string, mixed>> $allOpen every open position, for the numbers
 * @var array<string, string> $roles
 * @var string $role
 * @var list<array<string, mixed>> $positions open vacancies
 */
$slots = array_sum(array_map(static fn (array $p): int => $p['remaining'] ?? 1, $allOpen));
$committees = count(array_unique(array_column($allOpen, 'committee_name')));
$byCommittee = [];

foreach ($open as $position) {
    $byCommittee[$position['committee_name']][] = $position;
}
?>
<?php $this->start('title') ?>Open Positions<?php $this->end() ?>
<?php $this->start('description') ?>Committee positions open for application at AGILE, with the slots left and what to prepare.<?php $this->end() ?>
<?php $this->start('head') ?><link rel="stylesheet" href="<?= e(asset('css/portal.css')) ?>"><?php $this->end() ?>
<?php $this->start('hero') ?>
<?= $this->partial('portal/_hero', [
    'eyebrow' => 'open positions',
    'title' => 'Build AGILE with us',
    'lead' => 'These are the committee and publication roles open for application this semester. Prepare your current COR and your previous semester copy of grades.',
    'stats' => [
        [count($allOpen), count($allOpen) === 1 ? 'position open' : 'positions open'],
        [$slots, $slots === 1 ? 'slot left' : 'slots left'],
        [$committees, $committees === 1 ? 'committee hiring' : 'committees hiring'],
    ],
]) ?>
<?php $this->end() ?>

<section class="px-section" aria-labelledby="open-list">
    <header>
        <div><p class="px-eyebrow">open for application</p><h2 id="open-list">Choose a position</h2></div>
        <nav class="px-chips" aria-label="Filter by role">
            <a class="px-chip" href="<?= e(route('positions')) ?>"<?= $role === '' ? ' aria-current="page"' : '' ?>>All roles</a>
            <?php foreach ($roles as $key => $label) : ?>
                <a class="px-chip" href="<?= e(route('positions')) ?>?role=<?= e($key) ?>"<?= $role === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </header>

    <?php if ($open === []) : ?>
        <div class="px-empty">
            <?= $allOpen === [] ? 'Nothing is open for application right now. The MSW Committee opens positions each semester; watch the Announcements.' : 'Nothing open for this role. Try All roles.' ?>
            <br><a class="btn btn--secondary btn--sm" href="<?= e($allOpen === [] ? route('announcements') : route('positions')) ?>"><?= $allOpen === [] ? 'Go to Announcements' : 'Show all roles' ?></a>
        </div>
    <?php else : ?>
        <?php foreach ($byCommittee as $committee => $rows) : ?>
            <h3 class="px-subhead"><?= e(strtolower($committee)) ?></h3>
            <ul class="px-grid" role="list">
                <?php foreach ($rows as $position) : ?>
                    <li><?= $this->partial('portal/_open_position', ['position' => $position, 'withCommittee' => false]) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<?php if ($positions !== []) : ?>
    <section class="px-section" aria-labelledby="vacancies">
        <header><div><p class="px-eyebrow">vacancies</p><h2 id="vacancies">Also hiring, with a deadline</h2></div></header>
        <div class="card-grid">
            <?php foreach ($positions as $position) : ?>
                <?= $this->partial('portal/_position_card', ['position' => $position]) ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="px-section" aria-labelledby="how">
    <header><div><p class="px-eyebrow">how applying works</p><h2 id="how">From application to appointment</h2></div></header>
    <ol class="px-steps">
        <li><b>Apply</b><span>Choose the position and upload your COR and grades. A 1st year in the 1st semester gives the COR only.</span></li>
        <li><b>Documents checked</b><span>The MSW Committee verifies your files and may ask you to fix one.</span></li>
        <li><b>Interview</b><span>The MSW team sets your interview date and emails you.</span></li>
        <li><b>Decision</b><span>The Committee Head approves or rejects; you see it on Track Status.</span></li>
    </ol>
</section>

<section class="px-section">
    <div class="px-callout">
        <p><b>The Executive Board and the Year-Level Representatives are elected, not applied for</b> (Constitution and By-Laws, Article VI). Members file a candidacy when the election opens.</p>
        <a class="btn btn--secondary" href="<?= e(route('candidacy')) ?>">Run for office</a>
    </div>
</section>
