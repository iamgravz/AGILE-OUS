<?php
/**
 * My Account: ask for the digital ID or certificate, made by the MSW Committee from AGILE's template.
 *
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $requests
 */
use App\Services\CredentialRequestService;
use App\Services\CredentialService;

$tones = ['requested' => 'info', 'issued' => 'success', 'declined' => 'danger', 'cancelled' => 'neutral'];
$open = array_column(array_filter($requests, static fn (array $r): bool => $r['status'] === 'requested'), 'type');
?>
<section class="plain-section" id="credential-requests" aria-labelledby="credential-requests-title">
    <div class="card__header"><h2 class="card__title" id="credential-requests-title">Request your ID or certificate</h2></div>
    <div class="card__body">
        <p class="text-muted">The MSW Committee makes it from the AGILE template and uploads it here. Once it is ready, you can download it from this page.</p>
        <form method="post" action="<?= e(route('account.credential_request')) ?>">
            <?= csrf_field() ?>
            <div class="grid grid--2">
                <div class="field">
                    <label class="field__label" for="credential-type">What do you need? <span class="field__required" aria-hidden="true">*</span></label>
                    <select class="select" id="credential-type" name="type" required>
                        <?php foreach (CredentialService::TYPES as $type => $label) : ?>
                            <option value="<?= e($type) ?>"<?= in_array($type, $open, true) ? ' disabled' : '' ?>><?= e($label) ?><?= in_array($type, $open, true) ? ' (requested)' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?= $this->partial('partials/form_field', ['name' => 'note', 'label' => 'Note', 'value' => '', 'required' => false, 'help' => 'Optional. For example, what it is for, or a correction.']) ?>
            </div>
            <button type="submit" class="btn btn--primary"<?= count($open) === count(CredentialService::TYPES) ? ' disabled' : '' ?>>Send request</button>
        </form>

        <?php if ($requests !== []) : ?>
            <div class="table-wrap">
                <table class="table table--stack">
                    <caption class="sr-only">My requests</caption>
                    <thead><tr><th scope="col">Asked for</th><th scope="col">Requested</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Action</span></th></tr></thead>
                    <tbody>
                        <?php foreach ($requests as $row) : ?>
                            <tr>
                                <td data-label="Asked for"><?= e(CredentialService::TYPES[$row['type']]) ?></td>
                                <td data-label="Requested"><?= e(date_ph($row['created_at'])) ?></td>
                                <td data-label="Status">
                                    <span class="badge badge--<?= e($tones[$row['status']]) ?>"><?= e(CredentialRequestService::STATUSES[$row['status']]) ?></span>
                                    <?php if ($row['remarks'] !== null) : ?><br><span class="text-muted"><?= e($row['remarks']) ?></span><?php endif; ?>
                                </td>
                                <td data-label="Action">
                                    <?php if ($row['status'] === 'requested') : ?>
                                        <form method="post" action="<?= e(route('account.credential_request.cancel', ['id' => (int) $row['request_id']])) ?>">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn--ghost btn--sm">Cancel</button>
                                        </form>
                                    <?php elseif ($row['credential_id'] !== null) : ?>
                                        <a class="btn btn--secondary btn--sm" href="<?= e(route('files.show', ['type' => 'credential', 'id' => (int) $row['credential_id']])) ?>">Download</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
