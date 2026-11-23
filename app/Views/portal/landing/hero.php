<?php
/**
 * @var string $headline
 * @var string $subheadline
 * @var list<string> $taglines
 * @var string $heroImage
 * @var string $heroSrc
 * @var array<string, mixed>|null $term
 * @var bool $canApply
 */
$eyebrow = 'PUP Open University System';
?>
<section class="hero hero--landing" data-hero>
    <?php /* PUP campus photos, crossfading slowly behind the hero. Decorative, so hidden from screen readers. */ ?>
    <div class="hero__campus" aria-hidden="true">
        <?php foreach ([1, 2, 3, 4] as $photo) : ?>
            <span class="hero__campus-slide hero__campus-slide--<?= $photo ?>"></span>
        <?php endforeach; ?>
    </div>
    <span class="glass-glows" aria-hidden="true"></span>
    <div class="container hero__inner">
        <div class="hero__panel">
            <div class="hero-rise">
                <img class="hero__logo<?= $heroImage !== '' ? ' hero__logo--photo' : '' ?>" src="<?= e($heroSrc) ?>" alt="" width="176" height="176" fetchpriority="high">
                <p class="eyebrow hero__badge"><?= e($eyebrow) ?></p>
                <h1 class="hero__title"><?= e($headline) ?></h1>
                <?php if ($taglines !== []) : ?><p class="hero__tagline"><?= e(implode(' · ', $taglines)) ?></p><?php endif; ?>
                <p class="hero__lead"><?= e($subheadline) ?></p>
                <div class="cluster">
                    <?php if ($canApply) : ?>
                        <a class="btn btn--primary" href="<?= e(route('apply')) ?>">Apply for membership</a>
                    <?php endif; ?>
                    <a class="btn btn--on-dark" href="<?= e(route('ask_for_help')) ?>">Ask for help</a>
                </div>
            </div>
        </div>
    </div>
<?php /* The scroll arrow and the Pause animation button were removed on request (Nov 23, 2026). The photos still stay still for anyone whose device asks for reduced motion. */ ?>
</section>
