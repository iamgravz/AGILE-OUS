<?php
/**
 * About AGILE: who the guild is, its Article II vision, mission and objectives, and the people who lead it.
 *
 * @var \App\Core\View $this
 * @var string $mission one commitment per line (Constitution, Article II Section 2)
 * @var string $vision
 * @var string $objectives one objective per line (Article II Section 3)
 * @var list<array<string, mixed>> $committees
 * @var array{members: int, committees: int, officers: int, open: int} $facts
 * @var array{executive: list<array<string, string>>, council: list<array<string, string>>, heads: list<array<string, string>>} $officers
 */
$objectives ??= '';
// A setting with several lines is a list; one line is a paragraph.
$lines = static fn (string $text): array => array_values(array_filter(array_map('trim', preg_split('/\R/', $text) ?: []), static fn (string $l): bool => $l !== ''));
$missionLines = $lines($mission);
$objectiveLines = $lines($objectives);
$initials = static fn (string $name): string => implode('', array_map(static fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)), array_slice(preg_split('/\s+/', trim($name)) ?: [], 0, 2)));
$person = static function (array $officer, bool $lead = false) use ($initials): string {
    return '<li class="px-person' . ($lead ? ' px-person--lead' : '') . '"><span class="px-avatar" aria-hidden="true">' . e($initials($officer['name'])) . '</span>'
        . '<b>' . e($officer['name']) . '</b><span>' . e($officer['title']) . ($officer['committee'] !== '' && $officer['committee'] !== \App\Services\ElectedOfficerService::COUNCIL ? ', ' . e($officer['committee']) : '') . '</span></li>';
};
?>
<?php $this->start('title') ?>About<?php $this->end() ?>
<?php $this->start('description') ?><?= e($vision !== '' ? $vision : ($missionLines[0] ?? '')) ?><?php $this->end() ?>
<?php $this->start('head') ?><link rel="stylesheet" href="<?= e(asset('css/portal.css')) ?>"><?php $this->end() ?>
<?php $this->start('hero') ?>
<?= $this->partial('portal/_hero', [
    'eyebrow' => 'about agile',
    'title' => 'Academic Guild of IT Learners and Experts',
    'lead' => 'The BSIT student organization of the PUP Open University System: we learn together, build together, and look after each other.',
    'stats' => [
        [$facts['members'], 'active members'],
        [$facts['committees'], 'standing committees'],
        [$facts['officers'], 'officers this term'],
        ['NALLRC', 'PUP Sta. Mesa, Manila'],
    ],
]) ?>
<?php $this->end() ?>

<section class="px-section" aria-labelledby="who-we-are">
    <header><div><p class="px-eyebrow">// article ii</p><h2 id="who-we-are">What we stand for</h2></div></header>
    <div class="px-bento">
        <article class="px-card px-tile--vision">
            <p class="px-eyebrow">// vision</p>
            <blockquote><?= e($vision) ?></blockquote>
        </article>
        <article class="px-card">
            <p class="px-eyebrow">// mission</p>
            <?php if (count($missionLines) > 1) : ?>
                <p>The Organization commits to:</p>
                <ul class="px-list"><?php foreach ($missionLines as $line) : ?><li><?= e($line) ?></li><?php endforeach; ?></ul>
            <?php else : ?>
                <p><?= e($mission) ?></p>
            <?php endif; ?>
        </article>
        <article class="px-card px-tile--gold">
            <p class="px-eyebrow">// what agile does</p>
            <ul class="px-list">
                <li><b>Membership</b>: member records, digital IDs and certificates for BSIT-OU students.</li>
                <li><b>Recruitment</b>: committee positions through applications, interviews and decisions.</li>
                <li><b>Student welfare</b>: a confidential way to ask for help.</li>
                <li><b>News and events</b>: announcements, activities and The Source Code.</li>
            </ul>
        </article>
    </div>

    <?php if ($objectiveLines !== []) : ?>
        <h3 class="px-subhead">// objectives, article ii section 3</h3>
        <ul class="px-objectives" role="list">
            <?php foreach ($objectiveLines as $index => $line) : ?>
                <li class="px-card px-card--lift"><p class="px-eyebrow">(<?= e(chr(97 + $index % 26)) ?>)</p><p><?= e($line) ?></p></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="px-section" aria-labelledby="officers">
    <header>
        <div><p class="px-eyebrow">// officers</p><h2 id="officers">The people behind AGILE</h2></div>
        <a class="btn btn--secondary btn--sm" href="<?= e(route('committees')) ?>">Meet the committees</a>
    </header>
    <?php if ($officers['executive'] === [] && $officers['council'] === [] && $officers['heads'] === []) : ?>
        <div class="px-empty">The officer list appears here once appointments are approved.</div>
    <?php else : ?>
        <?php if ($officers['executive'] !== []) : ?>
            <h3 class="px-subhead">// executive board</h3>
            <ul class="px-people" role="list">
                <?php foreach ($officers['executive'] as $index => $officer) : ?><?= $person($officer, $index === 0 && $officer['title'] === 'President') ?><?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($officers['council'] !== []) : ?>
            <h3 class="px-subhead">// year-level council</h3>
            <ul class="px-people" role="list">
                <?php foreach ($officers['council'] as $officer) : ?><?= $person($officer) ?><?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($officers['heads'] !== []) : ?>
            <h3 class="px-subhead">// committee heads</h3>
            <ul class="px-people" role="list">
                <?php foreach ($officers['heads'] as $officer) : ?><?= $person($officer) ?><?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
</section>

<section class="px-section" aria-labelledby="reach-us">
    <div class="px-callout">
        <div>
            <p class="px-eyebrow">// reach us</p>
            <h2 id="reach-us" class="px-callout__title">Visit or write to AGILE</h2>
            <p><?= e(config('app.address')) ?> · <a href="mailto:<?= e(org_contact_email()) ?>"><?= e(org_contact_email()) ?></a></p>
        </div>
        <a class="btn btn--primary" href="<?= e(route('apply')) ?>">Become a member</a>
    </div>
</section>
