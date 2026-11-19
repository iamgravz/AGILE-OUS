<?php
/**
 * @var \App\Core\View $this
 * @var array{data: list<array<string, mixed>>, page: int, lastPage: int, total: int} $result
 * @var array{status?: string, type?: string, q?: string} $filters
 */
$tabs = ['' => 'All', 'draft' => 'Draft', 'for_approval' => 'For Approval', 'returned' => 'Returned', 'published' => 'Published', 'archived' => 'Archived'];
$activeStatus = $filters['status'] ?? '';
$tabUrl = static function (string $status) use ($filters): string {
    $query = array_filter(['status' => $status, 'type' => $filters['type'] ?? '', 'q' => $filters['q'] ?? '']);

    return route('cms') . ($query === [] ? '' : '?' . http_build_query($query));
};
?>
<?php $this->start('title') ?>Content<?php $this->end() ?>
<?php $this->start('page_title') ?>Posts and announcements<?php $this->end() ?>
<?php $this->start('page_actions') ?>
<a class="btn btn--primary btn--sm" href="<?= e(route('cms.create')) ?>">Create a post</a>
<?php $this->end() ?>

<nav class="tabs__list" aria-label="Filter by status">
    <?php foreach ($tabs as $status => $label) : ?>
        <a class="tabs__tab" href="<?= e($tabUrl($status)) ?>"<?= $status === $activeStatus ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<form class="table-toolbar cms-filters" method="get" action="<?= e(route('cms')) ?>" data-no-guard>
    <?php if ($activeStatus !== '') : ?>
        <input type="hidden" name="status" value="<?= e($activeStatus) ?>">
    <?php endif; ?>
    <div class="field">
        <label class="field__label" for="q">Search by title</label>
        <input class="input" id="q" name="q" type="search" value="<?= e($filters['q'] ?? '') ?>">
    </div>
    <div class="field">
        <label class="field__label" for="type-filter">Type</label>
        <select class="select" id="type-filter" name="type">
            <option value="">All types</option>
            <?php foreach (\App\Services\CmsService::TYPES as $type) : ?>
                <option value="<?= e($type) ?>"<?= ($filters['type'] ?? '') === $type ? ' selected' : '' ?>><?= e(ucfirst($type)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="cms-filters__action"><button type="submit" class="btn btn--secondary">Apply filters</button></div>
</form>

<?php if ($result['data'] === []) : ?>
    <div class="empty-state">
        <p class="empty-state__text">Posts appear here after they are drafted.</p>
        <?php if (can('content.draft')) : ?><a class="btn btn--secondary btn--sm" href="<?= e(route('cms.create')) ?>">Create a post</a><?php endif; ?>
    </div>
<?php else : ?>
    <div class="table-wrap">
        <table class="table table--stack">
            <caption class="sr-only">Content posts</caption>
            <thead>
                <tr>
                    <th scope="col">Title</th>
                    <th scope="col">Type</th>
                    <th scope="col">Author</th>
                    <th scope="col">Status</th>
                    <th scope="col">Last updated</th>
                    <th scope="col">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['data'] as $post) : ?>
                    <?php $title = $post['title']; ?>
                    <tr>
                        <td data-label="Title"><?= e($title) ?></td>
                        <td data-label="Type"><?= e(ucfirst($post['type'])) ?></td>
                        <td data-label="Author"><?= e($post['author_name']) ?></td>
                        <td data-label="Status"><?= status_badge($post['status']) ?></td>
                        <td data-label="Last updated"><?= e(date_ph($post['updated_at'])) ?></td>
                        <td data-label="Action">
                            <?php if (\App\Services\CmsService::isEditable($post['status'])) : ?>
                                <a class="btn btn--ghost btn--sm" href="<?= e(route('cms.edit', ['id' => $post['post_id']])) ?>">Edit<span class="sr-only"> <?= e($title) ?></span></a>
                            <?php elseif (can('content.publish')) : ?>
                                <a class="btn btn--ghost btn--sm" href="<?= e(route('cms.review', ['id' => $post['post_id']])) ?>"><?= $post['status'] === 'for_approval' ? 'Review' : 'Open' ?><span class="sr-only"> <?= e($title) ?></span></a>
                            <?php else : ?>
                                <a class="btn btn--ghost btn--sm" href="<?= e(route('cms.show', ['id' => $post['post_id']])) ?>">View<span class="sr-only"> <?= e($title) ?></span></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= $this->partial('partials/pagination', [
        'page' => $result['page'],
        'lastPage' => $result['lastPage'],
        'url' => route('cms'),
        'query' => $filters,
    ]) ?>
<?php endif; ?>
