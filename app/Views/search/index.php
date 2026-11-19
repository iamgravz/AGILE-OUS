<?php
/**
 * @var \App\Core\View $this
 * @var string $query
 * @var list<array<string, mixed>> $groups
 * @var int $minLength
 */
$found = array_sum(array_column($groups, 'total'));
?>
<?php $this->start('title') ?>Search<?php $this->end() ?>
<?php $this->start('page_title') ?>Search<?php $this->end() ?>

<form class="applications-filters" method="get" action="<?= e(route('search')) ?>" data-no-guard>
    <div class="field applications-filters__search">
        <label class="field__label" for="search-page-q">Search</label>
        <input class="input" id="search-page-q" name="q" type="search" maxlength="100" value="<?= e($query) ?>">
    </div>
    <div class="applications-filters__actions">
        <button type="submit" class="btn btn--secondary">Search</button>
    </div>
</form>

<?php if (mb_strlen(trim($query)) < $minLength) : ?>
    <p class="text-muted">Enter at least <?= e($minLength) ?> characters.</p>
<?php elseif ($found === 0) : ?>
    <div class="empty-state">
        <p class="empty-state__text">No results for "<?= e($query) ?>". Try another word, code or number.</p>
    </div>
<?php else : ?>
    <p class="text-muted applications-count">Results for "<?= e($query) ?>"</p>
    <div class="case-sections">
        <?php foreach ($groups as $group) : ?>
            <?php if ($group['total'] > 0) : ?>
                <section class="card">
                    <div class="card__header document-card__header">
                        <h2 class="card__title"><?= e($group['module']) ?> (<?= e($group['total']) ?>)</h2>
                        <?php if ($group['all_url'] !== null && $group['total'] > count($group['items'])) : ?>
                            <a href="<?= e($group['all_url']) ?>">See all<span class="sr-only"> <?= e($group['module']) ?></span></a>
                        <?php endif; ?>
                    </div>
                    <div class="card__body">
                        <ul class="case-list">
                            <?php foreach ($group['items'] as $item) : ?>
                                <li class="document-card__header">
                                    <span>
                                        <a href="<?= e($item['url']) ?>"><?= e($item['title']) ?></a>
                                        <?php if ($item['detail'] !== '') : ?>
                                            <span class="timeline__meta"><?= e($item['detail']) ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <?= status_badge($item['status']) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </section>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
