<?php
/**
 * Creating starts from $hrRequest, and editing a draft from $vacancy. One of them is null.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed>|null $vacancy
 * @var array<string, mixed>|null $hrRequest
 */
$isNew = $vacancy === null;
$source = $vacancy ?? $hrRequest;
$heading = $isNew ? 'Create vacancy' : 'Edit vacancy';
$dateValue = static function (string $key) use ($vacancy): string {
    $old = (string) old($key);

    if ($old !== '') {
        return $old;
    }

    if ($vacancy !== null) {
        return date('Y-m-d\TH:i', strtotime($vacancy[$key]));
    }

    return $key === 'opens_at' ? date('Y-m-d\TH:i') : '';
};
$descriptionError = field_error('description');
$action = $isNew ? route('vacancies.create') : route('vacancies.edit', ['id' => (int) $vacancy['vacancy_id']]);
?>
<?php $this->start('title') ?><?= e($heading) ?><?php $this->end() ?>
<?php $this->start('form_side') ?><?= $this->partial('partials/side_panel', ['blocks' => [['title' => 'About vacancies', 'items' => ['A draft stays off the public site.', 'Publishing needs a closing date in the future.', 'It closes by itself on that date, or once every slot is filled.']]]]) ?><?php $this->end() ?>
<?php $this->start('page_title') ?><?= e($heading) ?><?php $this->end() ?>

<form class="card cms-form" method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>
    <?php if ($isNew) : ?>
        <input type="hidden" name="request_id" value="<?= e($hrRequest['request_id']) ?>">
    <?php endif; ?>
    <div class="card__body">
        <dl class="detail-list member-details">
            <div><dt>Committee</dt><dd><?= e($source['committee_name'] ?? 'Executive') ?></dd></div>
            <div><dt>Position</dt><dd><?= e($source['position_title']) ?></dd></div>
            <div><dt>Slots</dt><dd><?= e($source['slots']) ?></dd></div>
            <div><dt>Qualifications</dt><dd><?= nl2br(e($source['qualifications'])) ?></dd></div>
        </dl>
        <p class="field__help">These come from the approved HR request and cannot be changed here.</p>

        <?= $this->partial('partials/form_field', ['name' => 'title', 'label' => 'Title', 'value' => (string) old('title', $vacancy['title'] ?? $hrRequest['committee_name'] . ' ' . $hrRequest['position_title']), 'autocomplete' => 'off']) ?>
        <div class="field <?= $descriptionError !== null ? 'field--error' : '' ?>">
            <label class="field__label" for="description">Description <span class="field__required" aria-hidden="true">*</span></label>
            <textarea class="textarea applications-actions__remarks" id="description" name="description" maxlength="5000" required aria-describedby="description-help<?= $descriptionError !== null ? ' description-error' : '' ?>"<?= $descriptionError !== null ? ' aria-invalid="true"' : '' ?>><?= e((string) old('description', $vacancy['description'] ?? '')) ?></textarea>
            <p class="field__help" id="description-help">Shown on the public Open Positions page.</p>
            <?php if ($descriptionError !== null) : ?>
                <p class="field__error" id="description-error"><?= icon('alert') ?> <?= e($descriptionError) ?></p>
            <?php endif; ?>
        </div>
        <div class="grid grid--2">
            <?= $this->partial('partials/form_field', ['name' => 'opens_at', 'label' => 'Opens', 'type' => 'datetime-local', 'value' => $dateValue('opens_at')]) ?>
            <?= $this->partial('partials/form_field', ['name' => 'closes_at', 'label' => 'Closes', 'type' => 'datetime-local', 'value' => $dateValue('closes_at')]) ?>
        </div>
        <div class="cluster">
            <button
                type="submit"
                name="intent"
                value="publish"
                class="btn btn--primary"
                data-confirm="It appears on the public Open Positions page from its opening date, and people can apply. A published vacancy can no longer be edited."
                data-confirm-title="Publish this vacancy?"
                data-confirm-action="Publish"
                data-confirm-tone="primary"
            >Publish vacancy</button>
            <button type="submit" name="intent" value="draft" class="btn btn--secondary">Save as draft</button>
        </div>
    </div>
</form>
