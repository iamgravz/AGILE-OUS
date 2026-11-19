<?php
/**
 * @var \App\Core\View $this
 * @var list<string> $shown
 * @var string $headline
 * @var string $subheadline
 * @var list<string> $taglines
 * @var string $heroImage
 * @var array<string, mixed>|null $term
 * @var list<array{label: string, value: int}> $numbers
 * @var list<array<string, mixed>> $vacancies
 * @var list<array<string, mixed>> $committees
 * @var list<array{title: string, text: string}> $joinSteps
 * @var list<array<string, mixed>> $events
 * @var list<array<string, mixed>> $notices
 * @var list<array{url: string, title: string, width: int, height: int}> $photos
 */
$canApply = current_user_id() === null || can('application.submit');
$shows = static fn (string $section): bool => in_array($section, $shown, true);
$heroSrc = $heroImage !== '' ? media_url($heroImage) : asset('img/logo-hero.webp');
?>
<?php $this->start('title') ?>Home<?php $this->end() ?>
<?php $this->start('head') ?>
<link rel="stylesheet" href="<?= e(asset('css/landing.css')) ?>">
<script src="<?= e(asset('js/landing.js')) ?>" defer></script>
<?php if ($shows('hero')) : ?><link rel="preload" as="image" href="<?= e($heroSrc) ?>" fetchpriority="high"><?php endif; ?>
<?php $this->end() ?>
<?php if ($shows('hero')) : ?>
    <?php $this->start('hero') ?>
    <?= $this->partial('portal/landing/hero', compact('headline', 'subheadline', 'taglines', 'heroImage', 'heroSrc', 'term', 'canApply')) ?>
    <?php $this->end() ?>
<?php endif; ?>

<div class="landing" id="explore" tabindex="-1">
    <?php if (!$shows('hero')) : ?><h1 class="sr-only"><?= e($headline) ?></h1><?php endif; ?>
    <?php if ($shows('numbers')) : ?>
        <?= $this->partial('portal/landing/numbers', compact('numbers')) ?>
    <?php endif; ?>
    <?php if ($shows('committees') && $committees !== []) : ?>
        <?= $this->partial('portal/landing/committees', compact('committees')) ?>
    <?php endif; ?>
    <?php if ($shows('join')) : ?>
        <?= $this->partial('portal/landing/join', compact('joinSteps')) ?>
    <?php endif; ?>
    <?php if ($shows('positions')) : ?>
        <?= $this->partial('portal/landing/lists', compact('vacancies', 'events')) ?>
    <?php endif; ?>
    <?php if ($shows('notices') && $notices !== []) : ?>
        <?= $this->partial('portal/landing/notices', compact('notices')) ?>
    <?php endif; ?>
    <?php if ($shows('gallery') && $photos !== []) : ?>
        <?= $this->partial('portal/landing/gallery', compact('photos')) ?>
    <?php endif; ?>
    <?php if ($shows('cta') && $canApply) : ?>
        <?= $this->partial('portal/landing/cta', ['showJoinLink' => $shows('join')]) ?>
    <?php endif; ?>
</div>
