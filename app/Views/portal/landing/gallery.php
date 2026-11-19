<?php
/**
 * @var \App\Core\View $this
 * @var list<array{url: string, title: string, width: int, height: int}> $photos at most 6
 */
?>
<section class="landing-section" aria-labelledby="home-gallery" data-reveal>
    <?= $this->partial('portal/landing/_head', ['id' => 'home-gallery', 'eyebrow' => 'Photos', 'title' => 'Gallery']) ?>
    <ul class="landing-gallery" data-gallery>
        <?php foreach ($photos as $photo) : ?>
            <li>
                <a href="<?= e($photo['url']) ?>" data-caption="<?= e($photo['title']) ?>">
                    <img src="<?= e($photo['url']) ?>" alt="Photo from <?= e($photo['title']) ?>" width="<?= e($photo['width']) ?>" height="<?= e($photo['height']) ?>" loading="lazy">
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
