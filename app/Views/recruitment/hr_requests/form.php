<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed>|null $hrRequest
 * @var list<array<string, mixed>> $positions
 * @var string $heading
 */
$value = static fn (string $key): string => (string) old($key, $hrRequest[$key] ?? '');
$positionError = field_error('position_id');
$action = $hrRequest === null ? route('hr_requests.create') : route('hr_requests.edit', ['id' => (int) $hrRequest['request_id']]);
?>
<?php $this->start('title') ?><?= e($heading) ?><?php $this->end() ?>
<?php $this->start('page_title') ?><?= e($heading) ?><?php $this->end() ?>

<?php if ($positions === []) : ?>
    <div class="empty-state">
        <p class="empty-state__text">HR requests are filed by the head of the committee that needs the position, so please contact the MSW Committee if this looks wrong.</p>
    </div>
<?php else : ?>
    <?php if ($hrRequest !== null && $hrRequest['remarks'] !== null) : ?>
        <div class="alert alert--warning" role="status">
            <?= icon('alert') ?>
            <p class="alert__text"><strong>Returned for changes:</strong> <?= e($hrRequest['remarks']) ?></p>
        </div>
    <?php endif; ?>

    <form class="card cms-form" method="post" action="<?= e($action) ?>">
        <?= csrf_field() ?>
        <div class="card__body">
            <div class="field <?= $positionError !== null ? 'field--error' : '' ?>">
                <label class="field__label" for="position_id">Position <span class="field__required" aria-hidden="true">*</span></label>
                <select class="select" id="position_id" name="position_id" required<?= $positionError !== null ? ' aria-describedby="position_id-error" aria-invalid="true"' : '' ?>>
                    <option value="">Choose a position</option>
                    <?php foreach ($positions as $position) : ?>
                        <option value="<?= e($position['position_id']) ?>"<?= $value('position_id') === (string) $position['position_id'] ? ' selected' : '' ?>><?= e($position['committee_name'] . ': ' . $position['title']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($positionError !== null) : ?>
                    <p class="field__error" id="position_id-error"><?= icon('alert') ?> <?= e($positionError) ?></p>
                <?php endif; ?>
            </div>
            <?= $this->partial('partials/form_field', ['name' => 'slots', 'label' => 'Slots', 'type' => 'number', 'value' => $value('slots'), 'help' => 'How many people the committee needs in this position.']) ?>
            <?php foreach (['justification' => ['Justification', 'Why the committee needs this position filled now.'], 'qualifications' => ['Qualifications', 'What applicants need. These are copied to the vacancy and shown on the public site.']] as $field => [$label, $help]) : ?>
                <?php $error = field_error($field); ?>
                <div class="field <?= $error !== null ? 'field--error' : '' ?>">
                    <label class="field__label" for="<?= e($field) ?>"><?= e($label) ?> <span class="field__required" aria-hidden="true">*</span></label>
                    <textarea class="textarea applications-actions__remarks" id="<?= e($field) ?>" name="<?= e($field) ?>" maxlength="2000" required aria-describedby="<?= e($field) ?>-help<?= $error !== null ? " {$field}-error" : '' ?>"<?= $error !== null ? ' aria-invalid="true"' : '' ?>><?= e($value($field)) ?></textarea>
                    <p class="field__help" id="<?= e($field) ?>-help"><?= e($help) ?></p>
                    <?php if ($error !== null) : ?>
                        <p class="field__error" id="<?= e($field) ?>-error"><?= icon('alert') ?> <?= e($error) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <button type="submit" class="btn btn--primary"><?= $hrRequest === null ? 'Submit request' : 'Save and resubmit' ?></button>
        </div>
    </form>
<?php endif; ?>
