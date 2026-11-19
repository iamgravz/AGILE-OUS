<?php
/**
 * News, events, publications or announcements.
 *
 * @var \App\Core\View $this
 * @var string $type
 * @var array{title: string, route: string, intro: string} $list
 * @var string $search
 * @var string|null $when events only: upcoming or past
 * @var array{data: list<array<string, mixed>>, page: int, lastPage: int, total: int} $result
 */
$listUrl = route($list['route']);
$query = array_filter(['when' => $when === 'past' ? 'past' : null, 'q' => $search !== '' ? $search : null]);
$tabUrl = static function (string $tab) use ($listUrl, $search): string {
    $params = array_filter(['when' => $tab === 'past' ? 'past' : null, 'q' => $search !== '' ? $search : null]);

    return $listUrl . ($params === [] ? '' : '?' . http_build_query($params));
};
?>
<?php $this->start('title') ?><?= e($list['title']) ?><?php $this->end() ?>
<?php $this->start('description') ?><?= e($list['title'] . ' from AGILE. ' . $list['intro']) ?><?php $this->end() ?>
<h1><?= e($list['title']) ?></h1>
<p class="portal-lead"><?= e($list['intro']) ?></p>

<?php if ($when !== null) : ?>
    <nav class="tabs__list" aria-label="Event dates">
        <a class="tabs__tab" href="<?= e($tabUrl('upcoming')) ?>"<?= $when === 'upcoming' ? ' aria-current="page"' : '' ?>>Upcoming</a>
        <a class="tabs__tab" href="<?= e($tabUrl('past')) ?>"<?= $when === 'past' ? ' aria-current="page"' : '' ?>>Past</a>
    </nav>
<?php endif; ?>

<form class="portal-search" method="get" action="<?= e($listUrl) ?>" role="search" data-no-guard>
    <?php if ($when === 'past') : ?>
        <input type="hidden" name="when" value="past">
    <?php endif; ?>
    <div class="field">
        <label class="field__label" for="q">Search by title</label>
        <input class="input" id="q" name="q" type="search" value="<?= e($search) ?>">
    </div>
    <button type="submit" class="btn btn--secondary">Search</button>
</form>

<?php if ($result['data'] === []) : ?>
    <div class="empty-state">
        <p class="empty-state__text"><?= $search !== '' ? 'No titles match your search. Try other words.' : 'New posts appear here once they are published.' ?></p>
        <?php if ($search !== '') : ?>
            <a class="btn btn--secondary btn--sm" href="<?= e($listUrl . ($when === 'past' ? '?when=past' : '')) ?>">Clear the search</a>
        <?php else : ?>
            <a class="btn btn--secondary btn--sm" href="<?= e(route('home')) ?>">Back to Home</a>
        <?php endif; ?>
    </div>
<?php else : ?>
    <div class="card-grid">
        <?php foreach ($result['data'] as $post) : ?>
            <?= $this->partial('portal/_post_card', ['post' => $post]) ?>
        <?php endforeach; ?>
    </div>
    <div class="portal-pagination">
        <?= $this->partial('partials/pagination', ['page' => $result['page'], 'lastPage' => $result['lastPage'], 'url' => $listUrl, 'query' => $query]) ?>
    </div>
<?php endif; ?>
