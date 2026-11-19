<?php
/**
 * @var \App\Core\View $this
 * @var string $report
 * @var array<string, string> $input
 * @var array<string, mixed>|null $built
 * @var string|null $error
 * @var int $previewRows
 * @var list<array<string, mixed>> $committees
 * @var list<array<string, mixed>> $terms
 * @var list<array<string, mixed>> $categories
 * @var list<string> $vacancyStatuses
 */
use App\Services\EligibilityService;
use App\Services\ReportService;

$hasDates = $report !== 'eligibility';
$hasCommittee = in_array($report, ['membership', 'recruitment'], true);
$heading = ReportService::REPORTS[$report] . ' report';
$rows = $built['rows'] ?? [];
$columns = $built['columns'] ?? [];
$numeric = [];

foreach ($columns as $index => $column) {
    $numeric[$index] = $rows !== [] && array_filter($rows, static fn (array $row): bool => !is_numeric($row[$index] ?? '')) === [];
}
?>
<?php $this->start('title') ?><?= e($heading) ?><?php $this->end() ?>
<?php $this->start('page_title') ?><?= e($heading) ?><?php $this->end() ?>

<nav class="tabs__list" aria-label="Report type">
    <?php foreach (ReportService::REPORTS as $key => $label) : ?>
        <a class="tabs__tab" href="<?= e(route('reports')) ?>?report=<?= e($key) ?>"<?= $key === $report ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<form class="applications-filters admin-filters" method="get" action="<?= e(route('reports')) ?>" data-no-guard>
    <input type="hidden" name="report" value="<?= e($report) ?>">
    <?php if ($hasDates) : ?>
        <div class="field">
            <label class="field__label" for="from">From</label>
            <input class="input" id="from" name="from" type="date" value="<?= e($input['from']) ?>">
        </div>
        <div class="field">
            <label class="field__label" for="to">To</label>
            <input class="input" id="to" name="to" type="date" value="<?= e($input['to']) ?>">
        </div>
    <?php endif; ?>
    <?php if ($hasCommittee) : ?>
        <div class="field">
            <label class="field__label" for="committee">Committee</label>
            <select class="select" id="committee" name="committee">
                <option value="">All committees</option>
                <?php foreach ($committees as $committee) : ?>
                    <option value="<?= e($committee['committee_id']) ?>"<?= $input['committee'] === (string) $committee['committee_id'] ? ' selected' : '' ?>><?= e($committee['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
    <?php if ($report === 'membership') : ?>
        <div class="field">
            <label class="field__label" for="type">Membership type</label>
            <select class="select" id="type" name="type">
                <option value="">All types</option>
                <option value="general"<?= $input['type'] === 'general' ? ' selected' : '' ?>>General</option>
                <option value="officer"<?= $input['type'] === 'officer' ? ' selected' : '' ?>>Officer</option>
            </select>
        </div>
    <?php elseif ($report === 'recruitment') : ?>
        <div class="field">
            <label class="field__label" for="status">Vacancy status</label>
            <select class="select" id="status" name="status">
                <option value="">All statuses</option>
                <?php foreach ($vacancyStatuses as $status) : ?>
                    <option value="<?= e($status) ?>"<?= $input['status'] === $status ? ' selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php elseif ($report === 'welfare') : ?>
        <div class="field">
            <label class="field__label" for="category">Category</label>
            <select class="select" id="category" name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $category) : ?>
                    <option value="<?= e($category['category_id']) ?>"<?= $input['category'] === (string) $category['category_id'] ? ' selected' : '' ?>><?= e($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php else : ?>
        <div class="field">
            <label class="field__label" for="term">Term</label>
            <select class="select" id="term" name="term">
                <option value="">Current term</option>
                <?php foreach ($terms as $term) : ?>
                    <option value="<?= e($term['term_id']) ?>"<?= $input['term'] === (string) $term['term_id'] ? ' selected' : '' ?>><?= e(EligibilityService::termLabel($term)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
    <div class="applications-filters__actions">
        <button type="submit" class="btn btn--secondary">Show report</button>
        <a class="btn btn--ghost" href="<?= e(route('reports')) ?>?report=<?= e($report) ?>">Clear</a>
    </div>
</form>

<?php if ($error !== null) : ?>
    <div class="empty-state">
        <p class="empty-state__text"><?= e($error) ?></p>
    </div>
<?php elseif ($built !== null) : ?>
    <?php if ($report === 'welfare') : ?>
        <p class="field__help">Aggregated counts only, with no names, case codes or text.</p>
    <?php endif; ?>
    <?php if ($rows === []) : ?>
        <div class="empty-state">
            <p class="empty-state__text">No records match these filters. Widen the dates or choose Clear.</p>
        </div>
    <?php else : ?>
        <div class="report-bar">
            <p class="text-muted applications-count"><?= e($built['subtitle']) ?>. <?= e(count($rows)) ?> <?= count($rows) === 1 ? 'row' : 'rows' ?><?= count($rows) > $previewRows ? ", showing the first {$previewRows}. Exports include all." : '.' ?></p>
            <form class="cluster" method="post" action="<?= e(route('reports.export')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="report" value="<?= e($report) ?>">
                <?php foreach ($input as $key => $value) : ?>
                    <input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>">
                <?php endforeach; ?>
                <button type="submit" class="btn btn--primary btn--sm" name="format" value="csv">Export CSV</button>
                <button type="submit" class="btn btn--secondary btn--sm" name="format" value="pdf">Export PDF</button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="table table--stack table--codes table--compact">
                <caption class="sr-only"><?= e($built['title']) ?></caption>
                <thead>
                    <tr>
                        <?php foreach ($columns as $index => $column) : ?>
                            <th scope="col"<?= $numeric[$index] ? ' class="num"' : '' ?>><?= e($column) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($rows, 0, $previewRows) as $row) : ?>
                        <tr>
                            <?php foreach ($row as $index => $cell) : ?>
                                <?php $text = (string) $cell; ?>
                                <td<?= $numeric[$index] ? ' class="num"' : ($text === '' ? ' class="is-empty"' : (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1 ? ' class="nowrap"' : '')) ?> data-label="<?= e($columns[$index]) ?>">
                                    <?php if (str_ends_with($columns[$index], 'number') && $text !== '') : ?>
                                        <code><?= e($text) ?></code>
                                    <?php elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1) : ?>
                                        <?= e(date_ph($text)) ?>
                                    <?php else : ?>
                                        <?= e($text) ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>
