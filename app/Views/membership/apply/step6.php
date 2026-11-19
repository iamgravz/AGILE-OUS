<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $application
 * @var array<string, mixed> $user
 * @var list<array<string, mixed>> $academicQuestions
 * @var list<array<string, mixed>> $questions
 * @var array<int, string> $answers
 * @var list<array<string, mixed>> $documents
 * @var int $step
 * @var int $reached
 */
$consentError = field_error('privacy_consent');
?>
<?php $this->start('title') ?>Apply: Review<?php $this->end() ?>
<?php $this->start('page_title') ?>Membership application<?php $this->end() ?>
<?php $this->start('form_side') ?><?= $this->partial('membership/apply/_side', compact('application', 'step', 'reached')) ?><?php $this->end() ?>
<?= $this->partial('membership/apply/_stepper', compact('application', 'step', 'reached')) ?>
<div class="card wizard-form">
    <div class="card__body">
        <h2>Review and submit</h2>
        <p class="text-muted">Check your details. You can edit any section before submitting.</p>
        <?= $this->partial('membership/_summary', compact('application', 'user', 'academicQuestions', 'questions', 'answers', 'documents') + ['withEditLinks' => true]) ?>
    </div>
    <form method="post" action="<?= e(route('apply.submit', ['id' => $application['application_id']])) ?>">
        <?= csrf_field() ?>
        <div class="card__body">
            <div class="field <?= $consentError !== null ? 'field--error' : '' ?>">
                <label class="check">
                    <input type="checkbox" name="privacy_consent" value="1" required<?= $consentError !== null ? ' aria-invalid="true" aria-describedby="privacy-consent-error"' : '' ?>>
                    <span>I have read the <a href="<?= e(route('privacy')) ?>">Privacy Notice</a> and agree to the processing of my personal data under the Data Privacy Act of 2012 (RA 10173). <span class="field__required" aria-hidden="true">*</span></span>
                </label>
                <?php if ($consentError !== null) : ?>
                    <p class="field__error" id="privacy-consent-error"><?= icon('alert') ?> <?= e($consentError) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="card__footer wizard-actions">
            <a class="btn btn--ghost" href="<?= e(route('apply.step', ['id' => $application['application_id'], 'step' => 5])) ?>">Back</a>
            <button type="submit" class="btn btn--primary"><?= $application['status'] === 'for_revision' ? 'Resubmit application' : 'Submit application' ?></button>
        </div>
    </form>
</div>
