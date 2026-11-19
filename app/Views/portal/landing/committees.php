<?php
/**
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $committees
 */
use App\Services\LandingService;

?>
<section class="landing-section" aria-labelledby="home-committees" data-reveal>
    <?= $this->partial('portal/landing/_head', [
        'id' => 'home-committees',
        'eyebrow' => 'Committees',
        'title' => 'What AGILE does',
        'links' => [['Learn more', route('about')], ['Our mission and vision', route('about') . '#section-our-mission']],
    ]) ?>
    <div class="landing-tiles">
        <?php foreach ($committees as $committee) : ?>
            <article class="landing-tile" data-spotlight>
                <span class="landing-tile__initials" aria-hidden="true"><?= e(LandingService::initials((string) $committee['name'])) ?></span>
                <h3><?= e($committee['name']) ?></h3>
                <?php if ((string) $committee['description'] !== '') : ?><p><?= e($committee['description']) ?></p><?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
