<?php
/**
 * The band at the top of every public page (css/portal.css). The eyebrow is written like a code comment, because
 * AGILE is an IT guild: "// committees".
 *
 * @var \App\Core\View $this
 * @var string $eyebrow without the slashes
 * @var string $title
 * @var string $lead (optional)
 * @var list<array{0: string|int, 1: string}> $stats (optional) [number, label]
 * @var string $extra (optional) trusted HTML after the stats, built by the page
 */
$lead ??= '';
$stats ??= [];
$extra ??= '';
?>
<section class="px-hero">
    <div class="container">
        <p class="px-eyebrow">// <?= e($eyebrow) ?></p>
        <h1><?= e($title) ?></h1>
        <?php if ($lead !== '') : ?><p class="px-lead"><?= e($lead) ?></p><?php endif; ?>
        <?php if ($stats !== []) : ?>
            <ul class="px-stats" role="list">
                <?php foreach ($stats as [$number, $label]) : ?>
                    <li class="px-stat"><b><?= e($number) ?></b> <span><?= e($label) ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?= $extra ?>
    </div>
</section>
