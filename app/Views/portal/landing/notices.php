<?php
/**
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $notices at most 3
 */
?>
<section class="landing-section" aria-labelledby="home-notices" data-reveal>
    <?= $this->partial('portal/landing/_head', [
        'id' => 'home-notices',
        'eyebrow' => 'News',
        'title' => 'Latest announcements',
        'links' => [['View all announcements', route('announcements')], ['View all news', route('news')]],
    ]) ?>
    <div class="landing-cards">
        <?php foreach ($notices as $post) : ?>
            <?= $this->partial('portal/_post_card', ['post' => $post]) ?>
        <?php endforeach; ?>
    </div>
</section>
