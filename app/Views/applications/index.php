<?php
/**
 * @var \App\Core\View $this
 * @var array{data: list<array<string, mixed>>, page: int, lastPage: int, total: int} $result
 * @var array<string, string> $filters
 * @var string $sort
 * @var string $direction
 * @var list<array<string, mixed>> $vacancies
 */
$query = array_filter($filters, static fn (string $value): bool => $value !== '');
$sortLink = static function (string $column, string $label) use ($query, $sort, $direction): string {
    $isCurrent = $sort === $column;
    $next = $isCurrent && $direction === 'asc' ? 'desc' : 'asc';
    $url = route('applications') . '?' . http_build_query($query + ['sort' => $column, 'dir' => $next]);
    $state = $isCurrent ? ($direction === 'asc' ? ' (ascending)' : ' (descending)') : '';

    return sprintf('<a class="sort-link" href="%s">%s%s</a>', e($url), e($label), e($state));
};
$ariaSort = static fn (string $column): string => $sort === $column ? ' aria-sort="' . ($direction === 'asc' ? 'ascending' : 'descending') . '"' : '';
?>
<?php $this->start('title') ?>Applications<?php $this->end() ?>
<?php $this->start('page_title') ?>Applications for review<?php $this->end() ?>
<?php $this->start('head') ?><script src="<?= e(asset('js/applications.js')) ?>" defer></script><?php $this->end() ?>

<?php if (!can('application.update_status')) : ?>
    <p class="text-muted">You can review applications that are approved or already evaluated. This list is read-only.</p>
<?php endif; ?>

<form class="applications-filters" method="get" action="<?= e(route('applications')) ?>" data-no-guard>
    <div class="field applications-filters__search">
        <label class="field__label" for="q">Name, email or code</label>
        <input class="input" id="q" name="q" type="search" value="<?= e($filters['q']) ?>">
    </div>
    <div class="field">
        <label class="field__label" for="status">Status</label>
        <select class="select" id="status" name="status">
            <option value="">All statuses</option>
            <?php foreach (\App\Services\ApplicationStatus::ALL as $status) : ?>
                <?php if ($status !== 'draft') : ?>
                    <option value="<?= e($status) ?>"<?= $filters['status'] === $status ? ' selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $status))) ?></option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label class="field__label" for="type">Applying for</label>
        <select class="select" id="type" name="type">
            <option value="">All applications</option>
            <option value="general"<?= $filters['type'] === 'general' ? ' selected' : '' ?>>General Membership</option>
            <option value="position"<?= $filters['type'] === 'position' ? ' selected' : '' ?>>A position, without a vacancy</option>
            <?php foreach ($vacancies as $vacancy) : ?>
                <option value="<?= e($vacancy['vacancy_id']) ?>"<?= $filters['type'] === (string) $vacancy['vacancy_id'] ? ' selected' : '' ?>><?= e($vacancy['title']) ?></option>
            <?php endforeach; ?>
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
        <a class="btn btn--ghost" href="<?= e(route('applications')) ?>">Clear</a>
    </div>
</form>

<?php if ($result['data'] === []) : ?>
    <div class="empty-state">
        <p class="empty-state__text">Applications appear here after applicants submit them.</p>
        <a class="btn btn--secondary btn--sm" href="<?= e(route('applications')) ?>">Clear the filters</a>
    </div>
<?php else : ?>
    <p class="text-muted applications-count"><?= e($result['total']) ?> <?= $result['total'] === 1 ? 'application' : 'applications' ?></p>
    <div class="table-wrap">
        <table class="table table--stack">
            <caption class="sr-only">Applications</caption>
            <thead>
                <tr>
                    <th scope="col"<?= $ariaSort('applicant') ?>><?= $sortLink('applicant', 'Applicant') ?></th>
                    <th scope="col">Applying for</th>
                    <th scope="col"<?= $ariaSort('tracking_code') ?>><?= $sortLink('tracking_code', 'Tracking code') ?></th>
                    <th scope="col"<?= $ariaSort('status') ?>><?= $sortLink('status', 'Status') ?></th>
                    <th scope="col"<?= $ariaSort('submitted_at') ?>><?= $sortLink('submitted_at', 'Submitted') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['data'] as $application) : ?>
                    <?php $id = (int) $application['application_id']; ?>
                    <tr class="application-row">
                        <td data-label="Applicant">
                            <a href="<?= e(route('applications.show', ['id' => $id])) ?>" data-drawer-url="<?= e(route('applications.drawer', ['id' => $id])) ?>"><?= e($application['first_name'] . ' ' . $application['last_name']) ?></a>
                            <span class="application-row__email"><?= e($application['email']) ?></span>
                        </td>
                        <td data-label="Applying for"><span class="truncate" title="<?= e($application['vacancy_title'] ?? 'General Membership') ?>"><?= e($application['vacancy_title'] ?? 'General Membership') ?></span></td>
                        <td data-label="Tracking code"><code><?= e($application['tracking_code']) ?></code></td>
                        <td data-label="Status"><?= status_badge($application['status']) ?></td>
                        <td data-label="Submitted"><?= $application['submitted_at'] === null ? '' : e(date_ph($application['submitted_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="portal-pagination">
        <?= $this->partial('partials/pagination', ['page' => $result['page'], 'lastPage' => $result['lastPage'], 'url' => route('applications'), 'query' => $query + ['sort' => $sort, 'dir' => $direction]]) ?>
    </div>
<?php endif; ?>

<dialog class="drawer" id="application-drawer" aria-labelledby="application-drawer-title">
    <div class="drawer__header">
        <h2 id="application-drawer-title">Application</h2>
        <button type="button" class="icon-btn" data-drawer-close aria-label="Close panel"><?= icon('close') ?></button>
    </div>
    <div class="drawer__body" data-drawer-body></div>
</dialog>
