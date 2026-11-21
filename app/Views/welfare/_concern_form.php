<?php
/**
 * The concern form, for logged-in users and guests. Errors come in $errors, because a failed form is rendered from
 * the same request and nothing confidential goes through the session.
 *
 * @var \App\Core\View $this
 * @var string $action
 * @var array<string, mixed> $input
 * @var array<string, string> $errors
 * @var list<array<string, mixed>> $categories
 * @var int $maxMb
 * @var list<array{case_id: int, label: string}>|null $closedCases logged-in users choose from these
 * @var bool $withConsent guests gave consent in the first step
 */
use App\Services\WelfareService;

$selected = (string) ($input['category_id'] ?? '');
$categoryError = $errors['category_id'] ?? null;
$subjectError = $errors['subject'] ?? null;
$descriptionError = $errors['description'] ?? null;
$relatedError = $errors['related'] ?? null;
$consentError = $errors['privacy_consent'] ?? null;
?>
<div class="alert alert--info welfare-notice" role="note">
    <?= icon('info') ?>
    <p class="alert__text"><strong>Your concern is confidential.</strong> Only the MSW Committee Head and the President can see it. In immediate danger? Contact emergency services first: this form is not monitored around the clock.</p>
</div>

<?php if (isset($errors['form'])) : ?>
    <?= $this->partial('partials/alert', ['tone' => 'danger', 'message' => $errors['form']]) ?>
<?php endif; ?>

<form class="card" method="post" action="<?= e($action) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="card__body">
        <fieldset class="field-group"<?= $categoryError !== null ? ' aria-describedby="category_id-error"' : '' ?>>
            <legend class="field-group__legend">What is this about? <span class="field__required" aria-hidden="true">*</span></legend>
            <?php if ($categoryError !== null) : ?>
                <p class="field__error" id="category_id-error"><?= icon('alert') ?> <?= e($categoryError) ?></p>
            <?php endif; ?>
            <div class="category-grid">
                <?php foreach ($categories as $category) : ?>
                    <?php $id = (int) $category['category_id']; ?>
                    <label class="category-card">
                        <input class="sr-only" type="radio" name="category_id" value="<?= e($id) ?>" required<?= $selected === (string) $id ? ' checked' : '' ?>>
                        <span class="category-card__body">
                            <span class="category-card__name"><?= e($category['name']) ?></span>
                            <span class="category-card__help"><?= e(WelfareService::CATEGORY_HELP[$id] ?? '') ?></span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <div class="field <?= $subjectError !== null ? 'field--error' : '' ?>">
            <label class="field__label" for="subject">Subject <span class="field__required" aria-hidden="true">*</span></label>
            <input class="input" id="subject" name="subject" type="text" maxlength="<?= e(WelfareService::SUBJECT_MAX) ?>" value="<?= e((string) ($input['subject'] ?? '')) ?>" autocomplete="off" required<?= $subjectError !== null ? ' aria-invalid="true" aria-describedby="subject-error"' : '' ?>>
            <?php if ($subjectError !== null) : ?>
                <p class="field__error" id="subject-error"><?= icon('alert') ?> <?= e($subjectError) ?></p>
            <?php endif; ?>
        </div>

        <div class="field <?= $descriptionError !== null ? 'field--error' : '' ?>">
            <label class="field__label" for="description">Description <span class="field__required" aria-hidden="true">*</span></label>
            <textarea class="textarea" id="description" name="description" rows="8" maxlength="<?= e(WelfareService::DESCRIPTION_MAX) ?>" required data-char-count="description-count" aria-describedby="description-count<?= $descriptionError !== null ? ' description-error' : '' ?>"<?= $descriptionError !== null ? ' aria-invalid="true"' : '' ?>><?= e((string) ($input['description'] ?? '')) ?></textarea>
            <p class="char-count text-muted" id="description-count"></p>
            <?php if ($descriptionError !== null) : ?>
                <p class="field__error" id="description-error"><?= icon('alert') ?> <?= e($descriptionError) ?></p>
            <?php endif; ?>
        </div>

        <div class="field">
            <label class="check">
                <input type="checkbox" name="urgent" value="1" aria-describedby="urgent-help"<?= !empty($input['urgent']) ? ' checked' : '' ?>>
                <span>This is urgent</span>
            </label>
            <p class="field__help" id="urgent-help">The MSW Committee Head and the President are alerted right away. If you are in immediate danger, please also contact your local emergency services.</p>
        </div>

        <?php if ($closedCases !== null) : ?>
            <?php if ($closedCases !== []) : ?>
                <div class="field <?= $relatedError !== null ? 'field--error' : '' ?>">
                    <label class="field__label" for="related_case_id">Related closed case</label>
                    <select class="select" id="related_case_id" name="related_case_id"<?= $relatedError !== null ? ' aria-invalid="true" aria-describedby="related-error"' : '' ?>>
                        <option value="">None</option>
                        <?php foreach ($closedCases as $case) : ?>
                            <option value="<?= e($case['case_id']) ?>"<?= (string) ($input['related_case_id'] ?? '') === (string) $case['case_id'] ? ' selected' : '' ?>><?= e($case['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($relatedError !== null) : ?>
                        <p class="field__error" id="related-error"><?= icon('alert') ?> <?= e($relatedError) ?></p>
                    <?php endif; ?>
                </div>
            <?php elseif ($relatedError !== null) : ?>
                <p class="field__error"><?= icon('alert') ?> <?= e($relatedError) ?></p>
            <?php endif; ?>
        <?php else : ?>
            <div class="field <?= $relatedError !== null ? 'field--error' : '' ?>">
                <label class="field__label" for="related_code">Earlier case code</label>
                <input class="input" id="related_code" name="related_code" type="text" maxlength="12" value="<?= e((string) ($input['related_code'] ?? '')) ?>" autocomplete="off" aria-describedby="related_code-help<?= $relatedError !== null ? ' related-error' : '' ?>"<?= $relatedError !== null ? ' aria-invalid="true"' : '' ?>>
                <p class="field__help" id="related_code-help">Optional. A case you submitted before with this email.</p>
                <?php if ($relatedError !== null) : ?>
                    <p class="field__error" id="related-error"><?= icon('alert') ?> <?= e($relatedError) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?= $this->partial('partials/upload_field', [
            'name' => 'attachments',
            'label' => 'Attachments',
            'types' => ['pdf', 'jpg', 'jpeg', 'png'],
            'maxMb' => $maxMb,
            'required' => false,
            'multiple' => true,
            'errorMessage' => $errors['attachments'] ?? null,
            'help' => 'Optional. Up to ' . WelfareService::MAX_ATTACHMENTS . " PDF, JPG or PNG files, {$maxMb} MB each." . ($errors === [] ? '' : ' Please choose your files again, because browsers do not keep them after an error.'),
        ]) ?>

        <?php if ($withConsent) : ?>
            <div class="field <?= $consentError !== null ? 'field--error' : '' ?>">
                <label class="check">
                    <input type="checkbox" name="privacy_consent" value="1" required<?= $consentError !== null ? ' aria-invalid="true" aria-describedby="privacy-consent-error"' : '' ?>>
                    <span>I have read the <a href="<?= e(route('privacy')) ?>">Privacy Notice</a> and agree to the processing of my personal data under the Data Privacy Act of 2012 (RA 10173). <span class="field__required" aria-hidden="true">*</span></span>
                </label>
                <?php if ($consentError !== null) : ?>
                    <p class="field__error" id="privacy-consent-error"><?= icon('alert') ?> <?= e($consentError) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <button type="submit" class="btn btn--primary">Submit concern</button>
    </div>
</form>
