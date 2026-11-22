<?php
/**
 * News, events, publications or announcements, each laid out for what it is:
 * news leads with one story and the latest notices; events read like a calendar, with the next one in the band;
 * publications are The Source Code's masthead, latest issue and shelf.
 *
 * @var \App\Core\View $this
 * @var string $type
 * @var array{title: string, route: string, eyebrow: string, heading: string, intro: string} $list
 * @var string $search
 * @var string|null $when events only: upcoming or past
 * @var array{data: list<array<string, mixed>>, page: int, lastPage: int, total: int} $result
 * @var list<array<string, mixed>> $notices news only: the latest announcements
 */
$notices ??= [];
$listUrl = route($list['route']);
$query = array_filter(['when' => $when === 'past' ? 'past' : null, 'q' => $search !== '' ? $search : null]);
$tabUrl = static function (string $tab) use ($listUrl, $search): string {
    $params = array_filter(['when' => $tab === 'past' ? 'past' : null, 'q' => $search !== '' ? $search : null]);

    return $listUrl . ($params === [] ? '' : '?' . http_build_query($params));
};
$posts = $result['data'];
$firstPage = $result['page'] === 1 && $search === '';
// The first item gets the big place: the lead story, the next event, or the latest issue.
$lead = $firstPage && $posts !== [] && ($type !== 'event' || $when === 'upcoming') ? array_shift($posts) : null;
$venue = static fn (array $post): string => preg_match('#^https?://#i', (string) $post['venue_or_link']) === 1 ? 'Online' : (string) $post['venue_or_link'];

$extra = '';

if ($type === 'event' && $lead !== null) {
    $start = (int) strtotime((string) $lead['event_start']);
    $extra = '<div class="px-next"><span class="px-date px-date--gold"><small>' . e(date('M', $start)) . '</small><b>' . e(date('j', $start)) . '</b></span>'
        . '<div><p class="px-eyebrow">// next up</p><h2>' . e($lead['title']) . '</h2><p>' . e(date('l', $start)) . ', ' . e(time_ph((string) $lead['event_start'])) . ($venue($lead) !== '' ? ' · ' . e($venue($lead)) : '') . '</p></div>'
        . '<a class="btn btn--primary" href="' . e(route('posts.show', ['slug' => $lead['slug']])) . '">See details</a></div>';
}
?>
<?php $this->start('title') ?><?= e($list['title']) ?><?php $this->end() ?>
<?php $this->start('description') ?><?= e($list['title'] . ' from AGILE. ' . $list['intro']) ?><?php $this->end() ?>
<?php $this->start('head') ?><link rel="stylesheet" href="<?= e(asset('css/portal.css')) ?>"><?php $this->end() ?>
<?php $this->start('hero') ?>
<?php if ($type === 'publication') : ?>
    <section class="px-hero">
        <div class="container">
            <div class="px-masthead">
                <p class="px-eyebrow">// official student publication of agile-ous</p>
                <p class="px-title">The Source Code<span>;</span></p>
            </div>
            <p class="px-lead">News, features, opinion and comics by the publication staff. <?= e(date('F Y')) ?>.</p>
            <h1 class="sr-only">Publications</h1>
        </div>
    </section>
<?php else : ?>
    <?= $this->partial('portal/_hero', ['eyebrow' => $list['eyebrow'], 'title' => $list['heading'], 'lead' => $list['intro'], 'extra' => $extra]) ?>
<?php endif; ?>
<?php $this->end() ?>

<?php if ($notices !== []) : ?>
    <div class="px-notices" aria-label="Latest notices">
        <?php foreach ($notices as $notice) : ?>
            <a class="px-notice" href="<?= e(route('posts.show', ['slug' => $notice['slug']])) ?>">
                <span class="px-chip px-chip--gold">Notice</span>
                <b><?= e($notice['title']) ?></b>
                <span class="px-meta"><?= e(date_ph((string) $notice['published_at'])) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form class="px-search" method="get" action="<?= e($listUrl) ?>" role="search" data-no-guard>
    <?php if ($when === 'past') : ?>
        <input type="hidden" name="when" value="past">
    <?php endif; ?>
    <div class="field">
        <label class="field__label" for="q">Search by title</label>
        <input class="input" id="q" name="q" type="search" value="<?= e($search) ?>">
    </div>
    <button type="submit" class="btn btn--secondary">Search</button>
    <?php if ($when !== null) : ?>
        <nav class="px-chips" aria-label="Event dates">
            <a class="px-chip" href="<?= e($tabUrl('upcoming')) ?>"<?= $when === 'upcoming' ? ' aria-current="page"' : '' ?>>Upcoming</a>
            <a class="px-chip" href="<?= e($tabUrl('past')) ?>"<?= $when === 'past' ? ' aria-current="page"' : '' ?>>Past</a>
        </nav>
    <?php endif; ?>
</form>

<?php if ($result['data'] === []) : ?>
    <div class="px-empty">
        <?= $search !== '' ? 'No titles match your search. Try other words.' : ($type === 'event' && $when === 'upcoming' ? 'No upcoming events yet. See what AGILE did before.' : 'New posts appear here once they are published.') ?>
        <br>
        <?php if ($search !== '') : ?>
            <a class="btn btn--secondary btn--sm" href="<?= e($listUrl . ($when === 'past' ? '?when=past' : '')) ?>">Clear the search</a>
        <?php elseif ($type === 'event' && $when === 'upcoming') : ?>
            <a class="btn btn--secondary btn--sm" href="<?= e($tabUrl('past')) ?>">See past events</a>
        <?php else : ?>
            <a class="btn btn--secondary btn--sm" href="<?= e(route('home')) ?>">Back to Home</a>
        <?php endif; ?>
    </div>
<?php elseif ($type === 'event') : ?>
    <?php if ($lead !== null && $posts === []) : ?>
        <p class="px-note">That is the only upcoming event for now.</p>
    <?php endif; ?>
    <div class="px-events">
        <?php $month = ''; ?>
        <?php foreach ($posts as $post) : ?>
            <?php $start = (int) strtotime((string) $post['event_start']); ?>
            <?php if (date('F Y', $start) !== $month) : ?>
                <?php $month = date('F Y', $start); ?>
                <h2 class="px-month"><?= e($month) ?></h2>
            <?php endif; ?>
            <a class="px-card px-event" href="<?= e(route('posts.show', ['slug' => $post['slug']])) ?>">
                <span class="px-date<?= $when === 'past' ? ' px-date--past' : '' ?>"><small><?= e(date('M', $start)) ?></small><b><?= e(date('j', $start)) ?></b></span>
                <div>
                    <h3><?= e($post['title']) ?></h3>
                    <span class="px-meta"><?= e(date('D', $start)) ?>, <?= e(time_ph((string) $post['event_start'])) ?><?= $venue($post) !== '' ? ' · ' . e($venue($post)) : '' ?></span>
                </div>
                <?php if ($venue($post) !== '') : ?><span class="px-chip px-chip--gold"><?= $venue($post) === 'Online' ? 'Online' : 'Onsite' ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php elseif ($type === 'publication') : ?>
    <?php if ($lead !== null) : ?>
        <section class="px-section" aria-label="Latest">
            <?= $this->partial('portal/_px_post', ['post' => $lead, 'feature' => true]) ?>
        </section>
    <?php endif; ?>
    <?php if ($posts !== []) : ?>
        <section class="px-section" aria-labelledby="shelf">
            <header><div><p class="px-eyebrow">// archive</p><h2 id="shelf">Past issues and stories</h2></div></header>
            <ul class="px-shelf" role="list">
                <?php foreach ($posts as $post) : ?>
                    <li>
                        <a class="px-card px-issue" href="<?= e(route('posts.show', ['slug' => $post['slug']])) ?>">
                            <span class="px-issue__cover">
                                <?php if ($post['cover_image'] !== null) : ?><img src="<?= e(media_url($post['cover_image'])) ?>" alt="" loading="lazy"><?php endif; ?>
                                <b><?= e($post['title']) ?></b>
                            </span>
                            <span class="px-post__body"><span class="px-meta"><?= e(date_ph((string) $post['published_at'])) ?></span></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
<?php else : ?>
    <?php if ($lead !== null) : ?>
        <section class="px-section" aria-label="Lead story">
            <?= $this->partial('portal/_px_post', ['post' => $lead, 'feature' => true]) ?>
        </section>
    <?php endif; ?>
    <?php if ($posts !== []) : ?>
        <ul class="px-grid" role="list">
            <?php foreach ($posts as $post) : ?>
                <li><?= $this->partial('portal/_px_post', ['post' => $post]) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
<?php endif; ?>

<?php if ($result['lastPage'] > 1) : ?>
    <div class="portal-pagination">
        <?= $this->partial('partials/pagination', ['page' => $result['page'], 'lastPage' => $result['lastPage'], 'url' => $listUrl, 'query' => $query]) ?>
    </div>
<?php endif; ?>
