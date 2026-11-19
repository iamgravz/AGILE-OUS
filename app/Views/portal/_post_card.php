<?php
/**
 * A post in a list. The whole card is one link.
 *
 * @var array<string, mixed> $post only published posts reach this view
 */
$link = route('posts.show', ['slug' => $post['slug']]);
$isEvent = $post['type'] === 'event';
$date = $isEvent ? $post['event_start'] : $post['published_at'];
$venue = (string) $post['venue_or_link'];
?>
<a class="card card--link post-card" href="<?= e($link) ?>">
    <?php if ($post['cover_image'] !== null) : ?>
        <img class="post-card__cover" src="<?= e(media_url($post['cover_image'])) ?>" alt="" width="640" height="360" loading="lazy">
    <?php elseif ($isEvent && $date !== null) : ?>
        <span class="post-card__cover post-card__datebox" aria-hidden="true">
            <span class="post-card__month"><?= e(date('M', (int) strtotime($date))) ?></span>
            <span class="post-card__day"><?= e(date('j', (int) strtotime($date))) ?></span>
        </span>
    <?php else : ?>
        <span class="post-card__cover post-card__placeholder" aria-hidden="true"><img src="<?= e(asset('img/logo-small.png')) ?>" alt="" width="48" height="48" loading="lazy"></span>
    <?php endif; ?>
    <span class="post-card__body">
        <span class="post-card__top">
            <span class="badge badge--neutral"><?= e(ucfirst($post['type'])) ?></span>
            <?php if ($date !== null) : ?><span class="post-card__meta"><?= e(date_ph($date)) ?></span><?php endif; ?>
        </span>
        <span class="post-card__title" title="<?= e($post['title']) ?>"><?= e($post['title']) ?></span>
        <?php if ($isEvent && $date !== null) : ?>
            <span class="post-card__meta"><?= e(time_ph($date)) ?><?= $venue === '' ? '' : ' · ' . e(preg_match('#^https?://#i', $venue) === 1 ? 'Online' : $venue) ?></span>
        <?php endif; ?>
        <span class="post-card__excerpt"><?= e($post['excerpt']) ?></span>
        <span class="post-card__more">Read more</span>
    </span>
</a>
