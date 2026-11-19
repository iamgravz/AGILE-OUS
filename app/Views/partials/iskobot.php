<?php
/**
 * Iskobot holding a banner: the three registration steps and the login page.
 *
 * @var int|null $step 1 to 3 on a registration step; leave it out for a banner with no step list
 * @var string $label what this page is for
 * @var string $bubble what Iskobot says
 * @var string $icon (optional) the banner icon, default user
 */
$step ??= null;
$icon ??= 'user';
?>
<div class="iskobot" aria-hidden="true">
    <div class="iskobot__bubble"><?= e($bubble) ?></div>
    <div class="iskobot__antenna"><span class="iskobot__bulb"></span><span class="iskobot__stem"></span></div>
    <div class="iskobot__head">
        <span class="iskobot__eye"><span class="iskobot__pupil"></span></span>
        <span class="iskobot__eye"><span class="iskobot__pupil"></span></span>
    </div>
</div>
<p class="iskobot-banner">
    <span class="iskobot-banner__icon"><?= icon($icon) ?></span>
    <span><?= $step !== null ? 'Step ' . (int) $step . ' of 3: ' : '' ?><?= e($label) ?></span>
</p>
<?php if ($step !== null) : ?>
<ol class="iskobot-steps" aria-label="Registration progress">
    <?php foreach (['Student number', 'Email code', 'Password'] as $index => $name) : ?>
        <li class="iskobot-steps__item<?= $index + 1 < $step ? ' is-done' : '' ?><?= $index + 1 === $step ? ' is-current' : '' ?>"<?= $index + 1 === $step ? ' aria-current="step"' : '' ?>>
            <?= e($name) ?><?= $index + 1 < $step ? '<span class="sr-only"> (done)</span>' : '' ?>
        </li>
    <?php endforeach; ?>
</ol>
<?php endif; ?>
