<?php
/**
 * @var \App\Core\View $this
 * @var array{data: list<array<string, mixed>>, page: int, lastPage: int, total: int} $result
 * @var array<string, string> $filters
 * @var list<array<string, mixed>> $categories
 */
use App\Services\WelfareService;

$query = array_filter($filters, static fn (string $value): bool => $value !== '');
?>
<?php $this->start('title') ?>Welfare cases to handle<?php $this->end() ?>
<?php $this->start('page_title') ?>Welfare cases to handle<?php $this->end() ?>

<form class="applications-filters" method="get" action="<?= e(route('welfare.queue')) ?>" data-no-guard>
    <div class="field applications-filters__search">
        <label class="field__label" for="q">Search by case code or subject</label>
        <input class="input" id="q" name="q" type="search" value="<?= e($filters['q']) ?>">
    </div>
    <div class="field">
        <label class="field__label" for="status">Status</label>
        <select class="select" id="status" name="status">
            <option value="">All statuses</option>
            <?php foreach (WelfareService::STATUSES as $status) : ?>
                <option value="<?= e($status) ?>"<?= $filters['status'] === $status ? ' selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $status))) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label class="field__label" for="category">Category</label>
        <select class="select" id="category" name="category">
            <option value="">All categories</option>
            <?php foreach ($categories as $category) : ?>
                <option value="<?= e($category['category_id']) ?>"<?= $filters['category'] === (string) $category['category_id'] ? ' selected' : '' ?>><?= e($category['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label class="field__label" for="priority">Priority</label>
        <select class="select" id="priority" name="priority">
            <option value="">All priorities</option>
            <?php foreach (WelfareService::PRIORITIES as $priority) : ?>
                <option value="<?= e($priority) ?>"<?= $filters['priority'] === $priority ? ' selected' : '' ?>><?= e(ucfirst($priority)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label class="field__label" for="assigned">Handler</label>
        <select class="select" id="assigned" name="assigned">
            <option value="">Anyone</option>
            <option value="me"<?= $filters['assigned'] === 'me' ? ' selected' : '' ?>>Assigned to me</option>
            <option value="unassigned"<?= $filters['assigned'] === 'unassigned' ? ' selected' : '' ?>>Unassigned</option>
        </select>
    </div>
    <div class="field">
        <label class="field__label" for="from">Submitted from</label>
        <input class="input" id="from" name="from" type="date" value="<?= e($filters['from']) ?>">
    </div>
    <div class="field">
        <label class="field__label" for="to">Submitted to</label>
        <input class="input" id="to" name="to" type="date" value="<?= e($filters['to']) ?>">
    </div>
    <div class="applications-filters__actions">
        <button type="submit" class="btn btn--secondary">Apply filters</button>
        <a class="btn btn--ghost" href="<?= e(route('welfare.queue')) ?>">Clear</a>
    </div>
</form>

<?php if ($result['data'] === []) : ?>
    <div class="empty-state">
        <p class="empty-state__text">New concerns appear here as soon as they are submitted.</p>
        <a class="btn btn--secondary btn--sm" href="<?= e(route('welfare.queue')) ?>">Clear the filters</a>
    </div>
<?php else : ?>
    <p class="text-muted applications-count"><?= e($result['total']) ?> <?= $result['total'] === 1 ? 'case' : 'cases' ?>, most urgent first, then oldest</p>
    <div class="table-wrap">
        <table class="table table--stack">
            <caption class="sr-only">Welfare cases</caption>
            <thead>
                <tr>
                    <th scope="col">Case code</th>
                    <th scope="col">Subject</th>
                    <th scope="col">Category</th>
                    <th scope="col">Priority</th>
                    <th scope="col">Status</th>
                    <th scope="col">Handler</th>
                    <th scope="col">Submitted</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['data'] as $case) : ?>
                    <tr>
                        <td data-label="Case code"><a href="<?= e(route('welfare.show', ['id' => (int) $case['case_id']])) ?>"><code><?= e($case['case_code']) ?></code></a></td>
                        <td data-label="Subject"><span class="truncate" title="<?= e($case['subject']) ?>"><?= e($case['subject']) ?></span></td>
                        <td data-label="Category"><?= e($case['category_name']) ?></td>
                        <td data-label="Priority">
                            <?= status_badge($case['priority']) ?>
                            <?php if ($case['overdue']) : ?>
                                <?= status_badge('overdue') ?>
                            <?php endif; ?>
                        </td>
                        <td data-label="Status"><?= status_badge($case['status']) ?></td>
                        <td data-label="Handler"><?= e($case['handler_name'] ?? 'Unassigned') ?></td>
                        <td data-label="Submitted"><?= e(date_ph($case['submitted_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="portal-pagination">
        <?= $this->partial('partials/pagination', ['page' => $result['page'], 'lastPage' => $result['lastPage'], 'url' => route('welfare.queue'), 'query' => $query]) ?>
    </div>
<?php endif; ?>
