<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $vacancy
 * @var list<array<string, mixed>> $applications
 * @var list<array<string, mixed>> $history
 */
$id = (int) $vacancy['vacancy_id'];
$status = $vacancy['status'];
$isLivePublicly = $status === 'open' && $vacancy['opens_at'] <= date('Y-m-d H:i:s') && $vacancy['closes_at'] > date('Y-m-d H:i:s');
$remarksError = field_error('remarks');
$closesError = field_error('closes_at');
$active = count(array_filter($applications, static fn (array $application): bool => in_array($application['status'], ['submitted', 'under_review', 'for_revision', 'waitlisted'], true)));
?>
<?php $this->start('title') ?><?= e($vacancy['title']) ?><?php $this->end() ?>
<?php $this->start('page_title') ?><?= e($vacancy['title']) ?><?php $this->end() ?>
<?php $this->start('page_actions') ?>
<?= status_badge($status) ?>
<?php if ($status === 'draft') : ?>
    <a class="btn btn--secondary btn--sm" href="<?= e(route('vacancies.edit', ['id' => $id])) ?>">Edit</a>
<?php endif; ?>
<?php if ($isLivePublicly) : ?>
    <a class="btn btn--secondary btn--sm" href="<?= e(route('positions.show', ['id' => $id])) ?>">Open public page</a>
<?php endif; ?>
<?php $this->end() ?>

<div class="detail-layout">
    <section class="plain-section" aria-labelledby="vacancy-details">
        <div class="card__header"><h2 class="card__title" id="vacancy-details">Vacancy details</h2></div>
        <div class="card__body">
            <dl class="detail-list member-details">
                <div><dt>HR request</dt><dd><a href="<?= e(route('hr_requests.show', ['id' => (int) $vacancy['request_id']])) ?>">Request <?= e($vacancy['request_id']) ?></a></dd></div>
                <div><dt>Committee</dt><dd><?= e($vacancy['committee_name'] ?? 'Executive') ?></dd></div>
                <div><dt>Position</dt><dd><?= e($vacancy['position_title']) ?></dd></div>
                <div><dt>Slots filled</dt><dd><?= e($vacancy['slots_filled']) ?> of <?= e($vacancy['slots']) ?></dd></div>
                <div><dt>Opens</dt><dd><?= e(datetime_ph($vacancy['opens_at'])) ?></dd></div>
                <div><dt>Closes</dt><dd><?= e(datetime_ph($vacancy['closes_at'])) ?></dd></div>
                <div><dt>Description</dt><dd><?= nl2br(e($vacancy['description'])) ?></dd></div>
                <div><dt>Qualifications</dt><dd><?= nl2br(e($vacancy['qualifications'] ?? '')) ?></dd></div>
            </dl>

            <?php if ($status === 'draft') : ?>
                <form class="member-reissue" method="post" action="<?= e(route('vacancies.publish', ['id' => $id])) ?>" data-confirm="It appears on the public Open Positions page from its opening date, and people can apply. A published vacancy can no longer be edited." data-confirm-title="Publish this vacancy?" data-confirm-action="Publish" data-confirm-tone="primary">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--primary">Publish vacancy</button>
                </form>
            <?php endif; ?>

            <?php if ($status === 'closed') : ?>
                <form class="member-reissue" method="post" action="<?= e(route('vacancies.reopen', ['id' => $id])) ?>">
                    <?= csrf_field() ?>
                    <?= $this->partial('partials/form_field', ['name' => 'closes_at', 'label' => 'New closing date', 'type' => 'datetime-local']) ?>
                    <button type="submit" class="btn btn--primary">Reopen vacancy</button>
                </form>
            <?php endif; ?>

            <?php if ($status === 'draft' || $status === 'open') : ?>
                <form class="member-reissue" method="post" action="<?= e(route('vacancies.cancel', ['id' => $id])) ?>">
                    <?= csrf_field() ?>
                    <div class="field <?= $remarksError !== null ? 'field--error' : '' ?>">
                        <label class="field__label" for="remarks">Reason for cancelling <span class="field__required" aria-hidden="true">*</span></label>
                        <textarea class="textarea applications-actions__remarks" id="remarks" name="remarks" maxlength="2000" required<?= $remarksError !== null ? ' aria-describedby="remarks-error" aria-invalid="true"' : '' ?>></textarea>
                        <?php if ($remarksError !== null) : ?>
                            <p class="field__error" id="remarks-error"><?= icon('alert') ?> <?= e($remarksError) ?></p>
                        <?php endif; ?>
                    </div>
                    <button
                        type="submit"
                        class="btn btn--danger"
                        data-confirm="It leaves the public site and cannot be reopened.<?= $active > 0 ? " {$active} application(s) still being processed stay in the queue." : '' ?>"
                        data-confirm-title="Cancel this vacancy?"
                        data-confirm-action="Cancel vacancy"
                    >Cancel vacancy</button>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <section class="plain-section" aria-labelledby="vacancy-applications">
        <div class="card__header"><h2 class="card__title" id="vacancy-applications">Applications received</h2></div>
        <div class="card__body">
            <?php if ($applications === []) : ?>
                <p class="text-muted">No applications have been received.</p>
            <?php else : ?>
                <div class="table-wrap">
                    <table class="table table--stack">
                        <caption class="sr-only">Applications received for this vacancy</caption>
                        <thead>
                            <tr><th scope="col">Applicant</th><th scope="col">Tracking code</th><th scope="col">Submitted</th><th scope="col">Status</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $application) : ?>
                                <tr>
                                    <td data-label="Applicant"><a href="<?= e(route('applications.show', ['id' => (int) $application['application_id']])) ?>"><?= e($application['first_name'] . ' ' . $application['last_name']) ?></a></td>
                                    <td data-label="Tracking code"><code><?= e($application['tracking_code']) ?></code></td>
                                    <td data-label="Submitted"><?= e($application['submitted_at'] === null ? '' : date_ph($application['submitted_at'])) ?></td>
                                    <td data-label="Status"><?= status_badge($application['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="card detail-layout__side" aria-labelledby="vacancy-history">
        <div class="card__header"><h2 class="card__title" id="vacancy-history">History</h2></div>
        <div class="card__body"><?= $this->partial('recruitment/_history', ['history' => $history]) ?></div>
    </section>
</div>
