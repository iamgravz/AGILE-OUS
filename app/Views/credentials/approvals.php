<?php
/**
 * @var \App\Core\View $this
 * @var array{type: string, reason: string} $filters
 * @var int $totalPending every pending credential, whatever the filters show
 * @var list<array<string, mixed>> $pending
 * @var list<array<string, mixed>> $returned
 */
use App\Services\CredentialService;

$preview = static fn (array $row): string => route('files.show', ['type' => 'credential', 'id' => (int) $row['credential_id']]) . '?inline=1';
?>
<?php $this->start('title') ?>IDs and certificates for approval<?php $this->end() ?>
<?php $this->start('page_title') ?>IDs and certificates for approval<?php $this->end() ?>
<?php $this->start('head') ?>
<script src="<?= e(asset('js/credential-approvals.js')) ?>" defer></script>
<?php $this->end() ?>
<form class="applications-filters" method="get" action="<?= e(route('credentials.approvals')) ?>" data-no-guard>
    <div class="field">
        <label class="field__label" for="type">Type</label>
        <select class="select" id="type" name="type">
            <option value="">All types</option>
            <?php foreach (CredentialService::TYPES as $value => $label) : ?>
                <option value="<?= e($value) ?>"<?= $filters['type'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label class="field__label" for="reason">Reason</label>
        <select class="select" id="reason" name="reason">
            <option value="">All reasons</option>
            <?php foreach (CredentialService::REASONS as $value => $label) : ?>
                <option value="<?= e($value) ?>"<?= $filters['reason'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="applications-filters__actions">
        <button type="submit" class="btn btn--secondary">Apply filters</button>
    </div>
</form>

<?php if ($totalPending > 0) : ?>
    <div class="cluster credential-bulk">
        <form id="bulk-credentials" method="post" action="<?= e(route('credentials.approve')) ?>" data-confirm="Nothing is selected yet." data-confirm-title="Approve selected?" data-confirm-action="Approve selected">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn--secondary">Approve selected</button>
        </form>
        <form method="post" action="<?= e(route('credentials.approve_all')) ?>" data-confirm="<?= e($totalPending) ?> credentials will be approved, and each member is emailed once." data-confirm-title="Approve all <?= e($totalPending) ?> pending?" data-confirm-action="Approve all">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn--primary">Approve all pending (<?= e($totalPending) ?>)</button>
        </form>
    </div>
<?php endif; ?>

<div class="credential-review">
    <div class="credential-review__list">
        <?php if ($pending === []) : ?>
            <p>Nothing is waiting for approval. New IDs and certificates appear here when a member is approved, a role changes or you issue new ones.</p>
        <?php else : ?>
            <div class="table-wrap">
                <table class="table table--stack">
                    <caption class="sr-only">Credentials waiting for approval</caption>
                    <thead>
                        <tr>
                            <th scope="col"><span class="sr-only">Select</span></th>
                            <th scope="col">Member</th>
                            <th scope="col">Member number</th>
                            <th scope="col">Type</th>
                            <th scope="col">Reason</th>
                            <th scope="col">Generated</th>
                            <th scope="col">Decision</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending as $row) : ?>
                            <?php $id = (int) $row['credential_id']; $label = $row['member_name'] . ', ' . CredentialService::TYPES[$row['type']]; ?>
                            <tr>
                                <td data-label="Select">
                                    <label class="sr-only" for="select-<?= e($id) ?>">Select <?= e($label) ?></label>
                                    <input id="select-<?= e($id) ?>" type="checkbox" name="ids[]" value="<?= e($id) ?>" form="bulk-credentials" data-credential-select>
                                </td>
                                <td data-label="Member"><?= e($row['member_name']) ?></td>
                                <td data-label="Member number"><code><?= e($row['member_no']) ?></code></td>
                                <td data-label="Type">
                                    <a href="<?= e($preview($row)) ?>" target="credential-preview"><?= e(CredentialService::TYPES[$row['type']]) ?><span class="sr-only">: preview for <?= e($row['member_name']) ?></span></a>
                                </td>
                                <td data-label="Reason"><?= e(CredentialService::REASONS[$row['reason']]) ?></td>
                                <td data-label="Generated"><?= e(date_ph($row['issued_at'])) ?></td>
                                <td data-label="Decision">
                                    <form method="post" action="<?= e(route('credentials.approve')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="ids[]" value="<?= e($id) ?>">
                                        <button type="submit" class="btn btn--primary btn--sm">Approve<span class="sr-only"> <?= e($label) ?></span></button>
                                    </form>
                                    <form method="post" action="<?= e(route('credentials.return', ['id' => $id])) ?>">
                                        <?= csrf_field() ?>
                                        <label class="field__label" for="remarks-<?= e($id) ?>">Remarks for returning</label>
                                        <input class="input" id="remarks-<?= e($id) ?>" name="remarks" type="text" maxlength="1000" required>
                                        <button type="submit" class="btn btn--secondary btn--sm">Return with remarks<span class="sr-only"> <?= e($label) ?></span></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($returned !== []) : ?>
            <h2>Returned for correction</h2>
            <div class="table-wrap">
                <table class="table table--stack">
                    <caption class="sr-only">Credentials returned for correction</caption>
                    <thead>
                        <tr>
                            <th scope="col">Member</th>
                            <th scope="col">Type</th>
                            <th scope="col">Remarks</th>
                            <th scope="col">Next step</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($returned as $row) : ?>
                            <tr>
                                <td data-label="Member"><?= e($row['member_name']) ?><br><code><?= e($row['member_no']) ?></code></td>
                                <td data-label="Type"><?= e(CredentialService::TYPES[$row['type']]) ?></td>
                                <td data-label="Remarks"><?= e($row['review_remarks']) ?></td>
                                <td data-label="Next step">
                                    <form method="post" action="<?= e(route('credentials.regenerate', ['id' => (int) $row['credential_id']])) ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn--secondary btn--sm">Regenerate<span class="sr-only"> <?= e($row['member_name']) ?>, <?= e(CredentialService::TYPES[$row['type']]) ?></span></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <div class="credential-review__preview">
        <h2>Preview</h2>
        <p class="text-muted">Choose a type in the list to see the file here.</p>
        <iframe name="credential-preview" title="Preview of the selected ID or certificate" tabindex="-1"></iframe>
    </div>
</div>
