<?php
/**
 * The same opening for every section: a small eyebrow, one h2, an optional line, and links on the right.
 *
 * @var string $id
 * @var string $eyebrow
 * @var string $title
 * @var string|null $intro
 * @var list<array{0: string, 1: string}>|null $links label, then address
 */
?>
<div class="landing-head">
    <div>
        <p class="eyebrow"><?= e($eyebrow) ?></p>
        <h2 id="<?= e($id) ?>"><?= e($title) ?></h2>
        <?php if (($intro ?? '') !== '') : ?><p class="landing-head__intro"><?= e($intro) ?></p><?php endif; ?>
    </div>
    <?php if (($links ?? []) !== []) : ?>
        <div class="landing-head__links">
            <?php foreach ($links as [$label, $url]) : ?><a href="<?= e($url) ?>"><?= e($label) ?></a><?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
