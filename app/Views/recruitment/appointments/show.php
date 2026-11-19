<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $appointment
 * @var string $resultingRole
 * @var string $resultingRoleSlug
 * @var array<string, string> $roleOptions
 * @var list<array<string, mixed>> $history
 */
$id = (int) $appointment['appointment_id'];
$status = $appointment['status'];
$isOwn = (int) $appointment['user_id'] === current_user_id();
$canDecide = $status === 'prepared' && can('appointment.approve') && !$isOwn;
$remarksError = field_error('remarks');
$reasonError = field_error('override_reason');
$selectedRole = (string) old('role_override', $resultingRoleSlug);
?>
<?php $this->start('title') ?>Appointment <?= e($appointment['tracking_code']) ?><?php $this->end() ?>
<?php $this->start('page_title') ?>Appointment <?= e($appointment['tracking_code']) ?><?php $this->end() ?>
<?php $this->start('page_actions') ?><?= status_badge($status) ?><?php $this->end() ?>

<div class="detail-layout">
    <?php if ($canDecide) : ?>
        <form class="plain-section" method="post" action="<?= e(route('appointments.approve', ['id' => $id])) ?>">
            <?= csrf_field() ?>
            <div class="card__header"><h2 class="card__title">Decision</h2></div>
            <div class="card__body">
                <dl class="detail-list member-details">
                    <div><dt>Resulting role</dt><dd><?= e($resultingRole) ?></dd></div>
                </dl>
                <div class="field">
                    <label class="field__label" for="role_override">Role to give</label>
                    <select class="select" id="role_override" name="role_override" aria-describedby="role_override-help">
                        <?php foreach ($roleOptions as $slug => $label) : ?>
                            <option value="<?= e($slug) ?>"<?= $selectedRole === $slug ? ' selected' : '' ?>><?= e($label) ?><?= $slug === $resultingRoleSlug ? ' (from the position)' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="field__help" id="role_override-help">Choose another role only to override the mapping. A reason is then required.</p>
                </div>
                <div class="field <?= $reasonError !== null ? 'field--error' : '' ?>">
                    <label class="field__label" for="override_reason">Reason for the override</label>
                    <input class="input" id="override_reason" name="override_reason" type="text" maxlength="255" value="<?= e((string) old('override_reason')) ?>" autocomplete="off"<?= $reasonError !== null ? ' aria-describedby="override_reason-error" aria-invalid="true"' : '' ?>>
                    <?php if ($reasonError !== null) : ?>
                        <p class="field__error" id="override_reason-error"><?= icon('alert') ?> <?= e($reasonError) ?></p>
                    <?php endif; ?>
                </div>
                <div class="field <?= $remarksError !== null ? 'field--error' : '' ?>">
                    <label class="field__label" for="remarks">Remarks <span class="field__required" aria-hidden="true">*</span></label>
                    <textarea class="textarea applications-actions__remarks" id="remarks" name="remarks" maxlength="2000" required<?= $remarksError !== null ? ' aria-describedby="remarks-error" aria-invalid="true"' : '' ?>><?= e((string) old('remarks')) ?></textarea>
                    <?php if ($remarksError !== null) : ?>
                        <p class="field__error" id="remarks-error"><?= icon('alert') ?> <?= e($remarksError) ?></p>
                    <?php endif; ?>
                </div>
                <div class="cluster">
                    <button
                        type="submit"
                        class="btn btn--primary"
                        data-confirm="The role and position change now, the old ID and certificate are revoked and new ones issued, one vacancy slot is taken, and the member is notified. This cannot be undone here."
                        data-confirm-title="Approve this appointment?"
                        data-confirm-action="Approve"
                        data-confirm-tone="primary"
                    >Approve appointment</button>
                    <button type="submit" class="btn btn--secondary" formaction="<?= e(route('appointments.return', ['id' => $id])) ?>">Return to MSW staff</button>
                </div>
            </div>
        </form>
    <?php endif; ?>

    <section class="plain-section" aria-labelledby="appointment-details">
        <div class="card__header"><h2 class="card__title" id="appointment-details">Appointment details</h2></div>
        <div class="card__body">
            <dl class="detail-list member-details">
                <div><dt>Applicant</dt><dd><?= e($appointment['applicant_name']) ?></dd></div>
                <div><dt>Application</dt><dd><a href="<?= e(route('applications.show', ['id' => (int) $appointment['application_id']])) ?>"><code><?= e($appointment['tracking_code']) ?></code></a></dd></div>
                <div><dt>Vacancy</dt><dd><?= e($appointment['vacancy_title'] ?? '') ?></dd></div>
                <div><dt>Position</dt><dd><?= e(\App\Services\MembershipService::roleLabel($appointment)) ?></dd></div>
                <div><dt>Slots filled</dt><dd><?= e($appointment['slots_filled']) ?> of <?= e($appointment['slots']) ?></dd></div>
                <div><dt>Effective date</dt><dd><?= e(date_ph($appointment['effective_date'])) ?></dd></div>
                <div><dt>Prepared by</dt><dd><?= e($appointment['preparer_name']) ?></dd></div>
                <div><dt>Latest remarks</dt><dd><?= nl2br(e($appointment['remarks'] ?? '')) ?></dd></div>
            </dl>
            <?php if ($status === 'returned' && can('appointment.prepare')) : ?>
                <p class="member-reissue"><a class="btn btn--secondary btn--sm" href="<?= e(route('appointments')) ?>">Prepare a new one from Appointments</a></p>
            <?php endif; ?>
        </div>
    </section>

    <section class="card detail-layout__side" aria-labelledby="appointment-history">
        <div class="card__header"><h2 class="card__title" id="appointment-history">History</h2></div>
        <div class="card__body"><?= $this->partial('recruitment/_history', ['history' => $history]) ?></div>
    </section>
</div>
