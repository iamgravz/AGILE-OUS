<?php
/**
 * A published post in the new public lists (css/portal.css): cover, type, date, title, summary. One link.
 *
 * @var array<string, mixed> $post
 * @var bool $feature (optional) the large first story
 */
$feature ??= false;
$link = route('posts.show', ['slug' => $post['slug']]);
$isEvent = $post['type'] === 'event';
$date = $isEvent ? $post['event_start'] : $post['published_at'];
$labels = ['news' => 'News', 'event' => 'Event', 'publication' => 'The Source Code', 'announcement' => 'Notice'];
$cover = $post['cover_image'] !== null
    ? '<img src="' . e(media_url($post['cover_image'])) . '" alt="" width="640" height="360" loading="lazy">'
    : '<img src="' . e(asset('img/logo-small.png')) . '" alt="" width="48" height="48" loading="lazy">';
$empty = $post['cover_image'] === null;
?>
<?php if ($feature) : ?>
    <a class="px-card px-feature" href="<?= e($link) ?>">
        <span class="px-feature__media<?= $empty ? ' px-feature__media--empty' : '' ?>"><?= $cover ?></span>
        <div class="px-feature__body">
            <span class="px-chips"><span class="px-chip px-chip--maroon"><?= e($labels[$post['type']]) ?></span><?php if ($date !== null) : ?><span class="px-meta"><?= e(date_ph($date)) ?></span><?php endif; ?></span>
            <h3><?= e($post['title']) ?></h3>
            <p><?= e($post['excerpt']) ?></p>
            <span class="btn btn--secondary btn--sm">Read the story</span>
        </div>
    </a>
<?php else : ?>
    <a class="px-card px-post" href="<?= e($link) ?>">
        <span class="px-post__media<?= $empty ? ' px-post__media--empty' : '' ?>"><?= $cover ?></span>
        <div class="px-post__body">
            <span class="px-chips"><span class="px-chip"><?= e($labels[$post['type']]) ?></span><?php if ($date !== null) : ?><span class="px-meta"><?= e(date_ph($date)) ?></span><?php endif; ?></span>
            <h3><?= e($post['title']) ?></h3>
            <p><?= e($post['excerpt']) ?></p>
        </div>
    </a>
<?php endif; ?>
