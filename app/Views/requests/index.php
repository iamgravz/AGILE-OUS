<?php
/**
 * My Requests: send a service request to the MSW Committee, and see your requests with their status.
 *
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $requests
 */
use App\Services\ServiceRequestService;

$tones = ['open' => 'info', 'in_progress' => 'warning', 'resolved' => 'success', 'closed' => 'neutral', 'cancelled' => 'neutral'];
$typeError = field_error('type');
$bodyError = field_error('body');
$oldType = (string) old('type', '');
?>
<?php $this->start('title') ?>My Requests<?php $this->end() ?>
<?php $this->start('page_eyebrow') ?>My account<?php $this->end() ?>
<?php $this->start('page_title') ?>My Requests<?php $this->end() ?>

<div class="account-sections">
    <section class="plain-section" aria-labelledby="new-request" id="new-request-section">
        <div class="card__header"><h2 class="card__title" id="new-request">New request</h2></div>
        <div class="card__body">
            <p class="field__help">For record corrections, documents, questions and account problems. For a personal or confidential concern, use <a href="<?= e(route('welfare.new')) ?>">Ask for Help</a> instead.</p>
            <form method="post" action="<?= e(route('requests.store')) ?>">
                <?= csrf_field() ?>
                <div class="field <?= $typeError !== null ? 'field--error' : '' ?>">
                    <label class="field__label" for="type">What is it about? <span class="field__required" aria-hidden="true">*</span></label>
                    <select class="select" id="type" name="type" required<?= $typeError !== null ? ' aria-invalid="true" aria-describedby="type-error"' : '' ?>>
                        <option value="">Choose one</option>
                        <?php foreach (ServiceRequestService::TYPES as $key => $label) : ?>
                            <option value="<?= e($key) ?>"<?= $oldType === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($typeError !== null) : ?><p class="field__error" id="type-error"><?= icon('alert') ?> <?= e($typeError) ?></p><?php endif; ?>
                </div>
                <?= $this->partial('partials/form_field', ['name' => 'subject', 'label' => 'Subject', 'value' => (string) old('subject', ''), 'autocomplete' => 'off']) ?>
                <div class="field <?= $bodyError !== null ? 'field--error' : '' ?>">
                    <label class="field__label" for="body">Details <span class="field__required" aria-hidden="true">*</span></label>
                    <textarea class="textarea" id="body" name="body" rows="5" maxlength="3000" required<?= $bodyError !== null ? ' aria-invalid="true" aria-describedby="body-error"' : '' ?>><?= e((string) old('body', '')) ?></textarea>
                    <?php if ($bodyError !== null) : ?><p class="field__error" id="body-error"><?= icon('alert') ?> <?= e($bodyError) ?></p><?php endif; ?>
                </div>
                <button type="submit" class="btn btn--primary">Send request</button>
            </form>
        </div>
    </section>

    <section class="plain-section" aria-labelledby="my-requests">
        <div class="card__header"><h2 class="card__title" id="my-requests">Your requests</h2></div>
        <div class="card__body">
            <?php if ($requests === []) : ?>
                <p class="text-muted">You have not sent a request yet.</p>
            <?php else : ?>
                <div class="table-wrap">
                    <table class="table table--stack">
                        <caption class="sr-only">Your requests</caption>
                        <thead><tr><th scope="col">Request</th><th scope="col">Type</th><th scope="col">Status</th><th scope="col">Last update</th></tr></thead>
                        <tbody>
                            <?php foreach ($requests as $row) : ?>
                                <tr>
                                    <td data-label="Request"><a href="<?= e(route('requests.show', ['id' => (int) $row['request_id']])) ?>"><code><?= e(ServiceRequestService::code((int) $row['request_id'])) ?></code> <?= e($row['subject']) ?></a></td>
                                    <td data-label="Type"><?= e(explode(' (', ServiceRequestService::TYPES[$row['type']])[0]) ?></td>
                                    <td data-label="Status"><span class="badge badge--<?= e($tones[$row['status']]) ?>"><?= e(ServiceRequestService::STATUSES[$row['status']]) ?></span></td>
                                    <td data-label="Last update"><?= e(datetime_ph((string) $row['updated_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
