<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $hrRequest
 * @var list<array<string, mixed>> $history
 * @var array<string, mixed>|null $vacancy
 */
$id = (int) $hrRequest['request_id'];
$status = $hrRequest['status'];
$isRequester = (int) $hrRequest['requested_by'] === current_user_id();
$remarksError = field_error('remarks');
$canDecide = $status === 'submitted' && can('hr_request.approve');
?>
<?php $this->start('title') ?>HR request <?= e($id) ?><?php $this->end() ?>
<?php $this->start('page_title') ?>HR request <?= e($id) ?><?php $this->end() ?>
<?php $this->start('page_actions') ?>
<?= status_badge($status) ?>
<?php if ($status === 'returned' && $isRequester) : ?>
    <a class="btn btn--primary btn--sm" href="<?= e(route('hr_requests.edit', ['id' => $id])) ?>">Edit and resubmit</a>
<?php endif; ?>
<?php if ($status === 'approved' && $vacancy === null && can('vacancy.manage')) : ?>
    <a class="btn btn--primary btn--sm" href="<?= e(route('vacancies.create')) ?>?request=<?= e($id) ?>">Create vacancy</a>
<?php endif; ?>
<?php $this->end() ?>

<div class="detail-layout">
    <?php if ($canDecide) : ?>
        <form class="plain-section" method="post" action="<?= e(route('hr_requests.approve', ['id' => $id])) ?>">
            <?= csrf_field() ?>
            <div class="card__body">
                <h2>Decision</h2>
                <div class="field <?= $remarksError !== null ? 'field--error' : '' ?>">
                    <label class="field__label" for="remarks">Remarks <span class="field__required" aria-hidden="true">*</span></label>
                    <textarea class="textarea applications-actions__remarks" id="remarks" name="remarks" maxlength="2000" required aria-describedby="remarks-help<?= $remarksError !== null ? ' remarks-error' : '' ?>"></textarea>
                    <p class="field__help" id="remarks-help">Required for both decisions. The person who filed the request sees these remarks.</p>
                    <?php if ($remarksError !== null) : ?>
                        <p class="field__error" id="remarks-error"><?= icon('alert') ?> <?= e($remarksError) ?></p>
                    <?php endif; ?>
                </div>
                <div class="cluster">
                    <button
                        type="submit"
                        class="btn btn--primary"
                        data-confirm="You can then create a vacancy from this request. This cannot be undone."
                        data-confirm-title="Approve this HR request?"
                        data-confirm-action="Approve"
                        data-confirm-tone="primary"
                    >Approve request</button>
                    <button type="submit" class="btn btn--secondary" formaction="<?= e(route('hr_requests.return', ['id' => $id])) ?>">Return to requester</button>
                </div>
            </div>
        </form>
    <?php endif; ?>

    <section class="plain-section" aria-labelledby="request-details">
        <div class="card__header"><h2 class="card__title" id="request-details">Request details</h2></div>
        <div class="card__body">
            <?php if ($hrRequest['remarks'] !== null) : ?>
                <div class="alert <?= $status === 'returned' ? 'alert--warning' : 'alert--info' ?>" role="status">
                    <?= icon($status === 'returned' ? 'alert' : 'info') ?>
                    <p class="alert__text"><strong>Remarks:</strong> <?= e($hrRequest['remarks']) ?></p>
                </div>
            <?php endif; ?>
            <dl class="detail-list member-details">
                <div><dt>Committee</dt><dd><?= e($hrRequest['committee_name']) ?></dd></div>
                <div><dt>Position</dt><dd><?= e($hrRequest['position_title']) ?></dd></div>
                <div><dt>Slots</dt><dd><?= e($hrRequest['slots']) ?></dd></div>
                <div><dt>Filed by</dt><dd><?= e($hrRequest['requester_name']) ?></dd></div>
                <div><dt>Filed</dt><dd><?= e(datetime_ph($hrRequest['created_at'])) ?></dd></div>
                <div><dt>Justification</dt><dd><?= nl2br(e($hrRequest['justification'])) ?></dd></div>
                <div><dt>Qualifications</dt><dd><?= nl2br(e($hrRequest['qualifications'])) ?></dd></div>
            </dl>
            <?php if ($status === 'submitted' && $isRequester) : ?>
                <form class="member-reissue" method="post" action="<?= e(route('hr_requests.cancel', ['id' => $id])) ?>" data-confirm="The MSW Committee Head will no longer be able to act on it. A cancelled request cannot be restored." data-confirm-title="Cancel this HR request?" data-confirm-action="Cancel request">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--danger btn--sm">Cancel request</button>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($vacancy !== null) : ?>
        <section class="plain-section" aria-labelledby="request-vacancy">
            <div class="card__header"><h2 class="card__title" id="request-vacancy">Vacancy</h2></div>
            <div class="card__body">
                <dl class="detail-list member-details">
                    <div>
                        <dt>Title</dt>
                        <dd>
                            <?php if (can('vacancy.manage')) : ?>
                                <a href="<?= e(route('vacancies.show', ['id' => (int) $vacancy['vacancy_id']])) ?>"><?= e($vacancy['title']) ?></a>
                            <?php else : ?>
                                <?= e($vacancy['title']) ?>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div><dt>Status</dt><dd><?= status_badge($vacancy['status']) ?></dd></div>
                    <div><dt>Slots filled</dt><dd><?= e($vacancy['slots_filled']) ?> of <?= e($vacancy['slots']) ?></dd></div>
                    <div><dt>Closes</dt><dd><?= e(datetime_ph($vacancy['closes_at'])) ?></dd></div>
                </dl>
            </div>
        </section>
    <?php endif; ?>

    <section class="card detail-layout__side" aria-labelledby="request-history">
        <div class="card__header"><h2 class="card__title" id="request-history">History</h2></div>
        <div class="card__body"><?= $this->partial('recruitment/_history', ['history' => $history]) ?></div>
    </section>
</div>
