<?php
/**
 * @var \App\Core\View $this
 * @var array{data: list<array<string, mixed>>, page: int, lastPage: int, total: int} $result
 * @var array<string, string> $filters
 */
$query = array_filter($filters, static fn (string $value): bool => $value !== '');
?>
<?php $this->start('title') ?>HR requests<?php $this->end() ?>
<?php $this->start('page_title') ?>HR requests<?php $this->end() ?>
<?php $this->start('page_actions') ?>
<a class="btn btn--primary btn--sm" href="<?= e(route('hr_requests.create')) ?>">File an HR request</a>
<?php $this->end() ?>

<form class="applications-filters" method="get" action="<?= e(route('hr_requests')) ?>" data-no-guard>
    <div class="field applications-filters__search">
        <label class="field__label" for="q">Search by committee or position</label>
        <input class="input" id="q" name="q" type="search" value="<?= e($filters['q']) ?>">
    </div>
    <div class="field">
        <label class="field__label" for="status">Status</label>
        <select class="select" id="status" name="status">
            <option value="">All statuses</option>
            <?php foreach (\App\Services\RecruitmentService::REQUEST_STATUSES as $status) : ?>
                <option value="<?= e($status) ?>"<?= $filters['status'] === $status ? ' selected' : '' ?>><?= e(ucfirst($status)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="applications-filters__actions">
        <button type="submit" class="btn btn--secondary">Apply filters</button>
        <a class="btn btn--ghost" href="<?= e(route('hr_requests')) ?>">Clear</a>
    </div>
</form>

<?php if ($result['data'] === []) : ?>
    <div class="empty-state">
        <p class="empty-state__text">Requests to fill committee positions appear here once they are filed.</p>
        <a class="btn btn--secondary btn--sm" href="<?= e(route('hr_requests.create')) ?>">File an HR request</a>
    </div>
<?php else : ?>
    <p class="text-muted applications-count"><?= e($result['total']) ?> <?= $result['total'] === 1 ? 'request' : 'requests' ?></p>
    <div class="table-wrap">
        <table class="table table--stack">
            <caption class="sr-only">HR requests</caption>
            <thead>
                <tr>
                    <th scope="col">Position</th>
                    <th scope="col">Committee</th>
                    <th scope="col" class="num">Slots</th>
                    <th scope="col">Filed by</th>
                    <th scope="col">Filed</th>
                    <th scope="col">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['data'] as $hrRequest) : ?>
                    <tr>
                        <td data-label="Position"><a href="<?= e(route('hr_requests.show', ['id' => (int) $hrRequest['request_id']])) ?>"><?= e($hrRequest['position_title']) ?></a></td>
                        <td data-label="Committee"><?= e($hrRequest['committee_name']) ?></td>
                        <td class="num" data-label="Slots"><?= e($hrRequest['slots']) ?></td>
                        <td data-label="Filed by"><?= e($hrRequest['requester_name']) ?></td>
                        <td data-label="Filed"><?= e(date_ph($hrRequest['created_at'])) ?></td>
                        <td data-label="Status"><?= status_badge($hrRequest['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="portal-pagination">
        <?= $this->partial('partials/pagination', ['page' => $result['page'], 'lastPage' => $result['lastPage'], 'url' => route('hr_requests'), 'query' => $query]) ?>
    </div>
<?php endif; ?>
