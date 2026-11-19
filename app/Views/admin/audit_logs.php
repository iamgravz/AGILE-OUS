<?php
/**
 * Read-only. There is no way to change or remove a row from here.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $result
 * @var array<string, string> $filters
 * @var list<string> $actions
 * @var list<string> $entities
 */
?>
<?php $this->start('title') ?>Audit log<?php $this->end() ?>
<?php $this->start('page_title') ?>Audit log<?php $this->end() ?>
<?php $this->start('page_actions') ?>
<form method="post" action="<?= e(route('admin.audit_logs.export')) ?>" data-no-guard>
    <?= csrf_field() ?>
    <?php foreach ($filters as $key => $value) : ?><input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>"><?php endforeach; ?>
    <button type="submit" class="btn btn--secondary btn--sm">Export these rows as CSV</button>
</form>
<?php $this->end() ?>

<form class="applications-filters admin-filters" method="get" action="<?= e(route('admin.audit_logs')) ?>" data-no-guard>
    <div class="field">
        <label class="field__label" for="user">User name or email</label>
        <input class="input" id="user" name="user" type="search" maxlength="100" value="<?= e($filters['user']) ?>">
    </div>
    <div class="field">
        <label class="field__label" for="action">Action</label>
        <select class="select" id="action" name="action">
            <option value="">All actions</option>
            <?php foreach ($actions as $action) : ?><option value="<?= e($action) ?>"<?= $filters['action'] === $action ? ' selected' : '' ?>><?= e($action) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label class="field__label" for="entity">Entity</label>
        <select class="select" id="entity" name="entity">
            <option value="">All entities</option>
            <?php foreach ($entities as $entity) : ?><option value="<?= e($entity) ?>"<?= $filters['entity'] === $entity ? ' selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $entity))) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label class="field__label" for="from">From</label>
        <input class="input" id="from" name="from" type="date" value="<?= e($filters['from']) ?>">
    </div>
    <div class="field">
        <label class="field__label" for="to">To</label>
        <input class="input" id="to" name="to" type="date" value="<?= e($filters['to']) ?>">
    </div>
    <div class="applications-filters__actions">
        <button type="submit" class="btn btn--secondary">Apply filters</button>
        <a class="btn btn--ghost" href="<?= e(route('admin.audit_logs')) ?>">Clear</a>
    </div>
</form>

<?php if ($result['data'] === []) : ?>
    <div class="empty-state"><p class="empty-state__text">No audit rows match these filters. Clear them to see everything.</p></div>
<?php else : ?>
    <p class="text-muted applications-count"><?= e($result['total']) ?> <?= $result['total'] === 1 ? 'row' : 'rows' ?>, newest first</p>
    <div class="table-wrap">
        <table class="table table--stack table--codes">
            <caption class="sr-only">Audit log</caption>
            <thead><tr><th scope="col">When</th><th scope="col">User</th><th scope="col">Action</th><th scope="col">Entity</th><th scope="col">IP address</th><th scope="col">Details</th></tr></thead>
            <tbody>
                <?php foreach ($result['data'] as $row) : ?>
                    <tr>
                        <td class="nowrap" data-label="When"><?= e(datetime_ph($row['created_at'])) ?></td>
                        <td data-label="User"><?= e($row['actor_name'] ?? 'System') ?></td>
                        <td data-label="Action"><code><?= e($row['action']) ?></code></td>
                        <td data-label="Entity"><?= e(ucfirst(trim(str_replace('_', ' ', (string) ($row['entity_type'] ?? '')) . ' ' . ($row['entity_id'] ?? '')))) ?></td>
                        <td data-label="IP address"><code><?= e($row['ip_address']) ?></code></td>
                        <td<?= $row['details_json'] === null ? ' class="is-empty"' : '' ?> data-label="Details">
                            <?php if ($row['details_json'] !== null) : ?>
                                <details class="audit-details">
                                    <summary>View<span class="sr-only"> details of row <?= e($row['log_id']) ?></span></summary>
                                    <pre><?= e(json_encode(json_decode($row['details_json'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
                                </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= $this->partial('partials/pagination', ['page' => $result['page'], 'lastPage' => $result['lastPage'], 'url' => route('admin.audit_logs'), 'query' => array_filter($filters)]) ?>
<?php endif; ?>
