<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $post
 * @var array{title: string, route: string} $list
 */
use App\Models\ContentPostRepository;
use App\Models\UserRepository;
?>
<?php $this->start('title') ?><?= e($post['title']) ?><?php $this->end() ?>
<?php $this->start('description') ?><?= e($post['excerpt']) ?><?php $this->end() ?>
<?php $this->start('head') ?>
<link rel="canonical" href="<?= e(url(route('posts.show', ['slug' => $post['slug']]))) ?>">
<meta property="og:site_name" content="<?= e(config('app.name')) ?>">
<meta property="og:type" content="article">
<meta property="og:title" content="<?= e($post['title']) ?>">
<meta property="og:description" content="<?= e($post['excerpt']) ?>">
<meta property="og:url" content="<?= e(url(route('posts.show', ['slug' => $post['slug']]))) ?>">
<meta property="og:image" content="<?= e(url($post['cover_image'] !== null ? media_url($post['cover_image']) : asset('img/logo-large.png'))) ?>">
<?php $this->end() ?>
<?php
$isEvent = $post['type'] === 'event';
$typeLabel = ucfirst($post['type']);
$author = (new UserRepository())->find((int) $post['author_id']);
$related = $isEvent ? [] : array_slice(array_values(array_filter(
    (new ContentPostRepository())->publishedList($post['type'], '', 1, 4)['data'],
    static fn (array $other): bool => $other['slug'] !== $post['slug'],
)), 0, 3);
$venue = (string) $post['venue_or_link'];
?>
<?php $this->start('docs_group') ?><?= e($list['title']) ?><?php $this->end() ?>
<?php $this->start('docs_related') ?><li><a href="<?= e(route($list['route'])) ?>">Back to <?= e($list['title']) ?></a></li><?php $this->end() ?>
<?php $this->start('docs_eyebrow') ?><?= e($typeLabel) ?><?php $this->end() ?>
<?php $this->start('docs_title') ?><?= e($post['title']) ?><?php $this->end() ?>
<?php $this->start('docs_updated') ?>Last updated <?= e(date_ph($post['updated_at'])) ?><?php $this->end() ?>
<?php $this->start('docs_context') ?>
<div class="card">
    <div class="card__header"><h2 class="card__title"><?= $isEvent ? 'Event details' : 'About this post' ?></h2></div>
    <div class="card__body">
        <dl class="detail-list">
            <?php if ($isEvent) : ?>
                <?php if ($post['event_start'] !== null) : ?>
                    <div><dt>Date</dt><dd><?= e(date_ph($post['event_start'])) ?><?= $post['event_end'] !== null && date_ph($post['event_end']) !== date_ph($post['event_start']) ? ' to ' . e(date_ph($post['event_end'])) : '' ?></dd></div>
                    <div><dt>Time</dt><dd><?= e(time_ph($post['event_start'])) ?><?= $post['event_end'] !== null ? ' to ' . e(time_ph($post['event_end'])) : '' ?></dd></div>
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
            <?php else : ?>
                <?php if ($post['published_at'] !== null) : ?>
                    <div><dt>Date</dt><dd><?= e(date_ph($post['published_at'])) ?></dd></div>
                <?php endif; ?>
                <?php if ($author !== null) : ?>
                    <div><dt>Author</dt><dd><?= e($author['first_name'] . ' ' . $author['last_name']) ?></dd></div>
                <?php endif; ?>
                <div><dt>Type</dt><dd><span class="badge badge--neutral"><?= e($typeLabel) ?></span></dd></div>
            <?php endif; ?>
        </dl>
        <?php if ($isEvent && $post['event_start'] !== null) : ?>
            <?= $this->partial('portal/_ics_link', ['post' => $post]) ?>
        <?php endif; ?>
        <?php if ($related !== []) : ?>
            <h3 class="docs-card__heading">Related posts</h3>
            <ul class="docs-card__list">
                <?php foreach ($related as $other) : ?>
                    <li><a href="<?= e(route('posts.show', ['slug' => $other['slug']])) ?>"><?= e($other['title']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
<?php $this->end() ?>
<?= $this->partial('cms/_article', ['post' => $post, 'bodyOnly' => true]) ?>
