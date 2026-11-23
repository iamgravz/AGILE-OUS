<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $application
 * @var array<string, mixed> $vacancy
 * @var array<string, mixed> $position
 */
$remarksError = field_error('remarks');
?>
<?php $this->start('title') ?>Prepare appointment<?php $this->end() ?>
<?php $this->start('page_title') ?>Prepare appointment<?php $this->end() ?>

<form class="card cms-form" method="post" action="<?= e(route('appointments.prepare')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="application_id" value="<?= e($application['application_id']) ?>">
    <div class="card__body">
        <dl class="detail-list member-details">
            <div><dt>Application</dt><dd><a href="<?= e(route('applications.show', ['id' => (int) $application['application_id']])) ?>"><code><?= e($application['tracking_code']) ?></code></a></dd></div>
            <div><dt>Vacancy</dt><dd><?= e($vacancy['title']) ?></dd></div>
            <div><dt>Position</dt><dd><?= e($position['title'] . ($position['committee_name'] === null ? '' : ', ' . $position['committee_name'])) ?></dd></div>
            <div><dt>Slots filled</dt><dd><?= e($vacancy['slots_filled']) ?> of <?= e($vacancy['slots']) ?></dd></div>
        </dl>
        <p class="field__help">The position comes from the vacancy. Nothing changes until the MSW Committee Head approves this appointment.</p>
        <?= $this->partial('partials/form_field', ['name' => 'effective_date', 'label' => 'Effective date', 'type' => 'date', 'value' => (string) old('effective_date')]) ?>
        <div class="field <?= $remarksError !== null ? 'field--error' : '' ?>">
            <label class="field__label" for="remarks">Remarks <span class="field__required" aria-hidden="true">*</span></label>
            <textarea class="textarea applications-actions__remarks" id="remarks" name="remarks" maxlength="2000" required<?= $remarksError !== null ? ' aria-describedby="remarks-error" aria-invalid="true"' : '' ?>><?= e((string) old('remarks')) ?></textarea>
            <?php if ($remarksError !== null) : ?>
                <p class="field__error" id="remarks-error"><?= icon('alert') ?> <?= e($remarksError) ?></p>
            <?php endif; ?>
        </div>
        <button type="submit" class="btn btn--primary">Prepare appointment</button>
    </div>
</form>
