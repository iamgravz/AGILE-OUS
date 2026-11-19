<?php
/**
 * @var \App\Core\View $this
 * @var array{data: list<array<string, mixed>>, page: int, lastPage: int, total: int} $result
 * @var array<string, string> $filters
 */
$query = array_filter($filters, static fn (string $value): bool => $value !== '');
?>
<?php $this->start('title') ?>Vacancies<?php $this->end() ?>
<?php $this->start('page_title') ?>Vacancies<?php $this->end() ?>
<?php $this->start('page_actions') ?>
<a class="btn btn--primary btn--sm" href="<?= e(route('vacancies.create')) ?>">Create vacancy</a>
<?php $this->end() ?>

<form class="applications-filters" method="get" action="<?= e(route('vacancies')) ?>" data-no-guard>
    <div class="field applications-filters__search">
        <label class="field__label" for="q">Search by title or committee</label>
        <input class="input" id="q" name="q" type="search" value="<?= e($filters['q']) ?>">
    </div>
    <div class="field">
        <label class="field__label" for="status">Status</label>
        <select class="select" id="status" name="status">
            <option value="">All statuses</option>
            <?php foreach (\App\Services\RecruitmentService::VACANCY_STATUSES as $status) : ?>
                <option value="<?= e($status) ?>"<?= $filters['status'] === $status ? ' selected' : '' ?>><?= e(ucfirst($status)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="applications-filters__actions">
        <button type="submit" class="btn btn--secondary">Apply filters</button>
        <a class="btn btn--ghost" href="<?= e(route('vacancies')) ?>">Clear</a>
    </div>
</form>

<?php if ($result['data'] === []) : ?>
    <div class="empty-state">
        <p class="empty-state__text">Vacancies appear here after one is created from an approved HR request.</p>
        <a class="btn btn--secondary btn--sm" href="<?= e(route('vacancies.create')) ?>">Create a vacancy</a>
    </div>
<?php else : ?>
    <p class="text-muted applications-count"><?= e($result['total']) ?> <?= $result['total'] === 1 ? 'vacancy' : 'vacancies' ?></p>
    <div class="table-wrap">
        <table class="table table--stack">
            <caption class="sr-only">Vacancies</caption>
            <thead>
                <tr>
                    <th scope="col">Title</th>
                    <th scope="col">Committee</th>
                    <th scope="col" class="num">Slots filled</th>
                    <th scope="col">Applications</th>
                    <th scope="col">Closes</th>
                    <th scope="col">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['data'] as $vacancy) : ?>
                    <tr>
                        <td data-label="Title"><a href="<?= e(route('vacancies.show', ['id' => (int) $vacancy['vacancy_id']])) ?>"><?= e($vacancy['title']) ?></a></td>
                        <td data-label="Committee"><?= e($vacancy['committee_name'] ?? 'Executive') ?></td>
                        <td class="num" data-label="Slots filled"><?= e($vacancy['slots_filled']) ?> of <?= e($vacancy['slots']) ?></td>
                        <td data-label="Applications"><?= e($vacancy['application_count']) ?></td>
                        <td data-label="Closes"><?= e(datetime_ph($vacancy['closes_at'])) ?></td>
                        <td data-label="Status"><?= status_badge($vacancy['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="portal-pagination">
        <?= $this->partial('partials/pagination', ['page' => $result['page'], 'lastPage' => $result['lastPage'], 'url' => route('vacancies'), 'query' => $query]) ?>
    </div>
<?php endif; ?>
