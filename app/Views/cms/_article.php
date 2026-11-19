<?php
/**
 * The public look of a post, shared by the public page and the review preview so they cannot differ.
 *
 * @var array<string, mixed> $post body is stored purified
 * @var bool $bodyOnly (optional) the docs layout shows the title and details itself
 */
$bodyOnly ??= false;
$formatDate = static fn (string $date): string => datetime_ph($date);
$venue = (string) $post['venue_or_link'];
?>
<article class="article<?= $bodyOnly ? ' article--docs' : '' ?>">
    <?php if (!$bodyOnly) : ?>
        <p class="article__type"><?= e(ucfirst($post['type'])) ?></p>
        <h1 class="article__title"><?= e($post['title']) ?></h1>
        <?php if ($post['published_at'] !== null) : ?>
            <p class="article__date">Published <?= e(date_ph($post['published_at'])) ?></p>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($post['cover_image'] !== null) : ?>
        <img class="article__cover" src="<?= e(media_url($post['cover_image'])) ?>" alt="">
    <?php endif; ?>
    <?php if ($post['type'] === 'event' && !$bodyOnly) : ?>
        <dl class="detail-list article__event">
            <?php if ($post['event_start'] !== null) : ?>
                <div><dt>Starts</dt><dd><?= e($formatDate($post['event_start'])) ?></dd></div>
            <?php endif; ?>
            <?php if ($post['event_end'] !== null) : ?>
                <div><dt>Ends</dt><dd><?= e($formatDate($post['event_end'])) ?></dd></div>
            <?php endif; ?>
            <?php if ($venue !== '') : ?>
                <div>
                    <dt>Venue or link</dt>
                    <dd>
                        <?php if (preg_match('#^https?://#i', $venue) === 1) : ?>
                            <a href="<?= e($venue) ?>" rel="noopener noreferrer"><?= e($venue) ?></a>
                        <?php else : ?>
                            <?= e($venue) ?>
                        <?php endif; ?>
                    </dd>
                </div>
            <?php endif; ?>
        </dl>
    <?php endif; ?>
    <div class="article__body"><?= $post['body'] ?></div>
</article>
