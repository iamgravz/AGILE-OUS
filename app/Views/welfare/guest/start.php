<?php
/** @var \App\Core\View $this */
$consentError = field_error('privacy_consent');
?>
<?php $this->start('title') ?>Ask for help as a guest<?php $this->end() ?>
<?php $this->start('description') ?>Submit a confidential concern to AGILE without an account. We confirm your email with a one-time code.<?php $this->end() ?>
<div class="welfare-page">
    <h1>Ask for help as a guest</h1>
    <p class="text-muted">Step 1 of 3: Confirm your email</p>
    <div class="alert alert--info welfare-notice" role="note">
        <?= icon('info') ?>
        <p class="alert__text"><strong>Your concern is confidential.</strong> Only the MSW Committee Head and the President can see it. First, we send a 6-digit code to confirm that this email is yours.</p>
    </div>
    <form class="card" method="post" action="<?= e(route('welfare.guest.code')) ?>">
        <?= csrf_field() ?>
        <div class="card__body">
            <?= $this->partial('partials/form_field', ['name' => 'name', 'label' => 'Full name', 'value' => (string) old('name'), 'autocomplete' => 'name']) ?>
            <?= $this->partial('partials/form_field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => (string) old('email'), 'autocomplete' => 'email']) ?>
            <?= $this->partial('partials/form_field', ['name' => 'student_no', 'label' => 'PUP student number', 'value' => (string) old('student_no'), 'required' => false, 'autocomplete' => 'off', 'help' => 'Optional. For example, 2024-xxxxx-MN-0.']) ?>
            <div class="hp-field" aria-hidden="true">
                <label for="website">Leave this field empty</label>
                <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>
            <div class="field <?= $consentError !== null ? 'field--error' : '' ?>">
                <label class="check">
                    <input type="checkbox" name="privacy_consent" value="1" required<?= $consentError !== null ? ' aria-invalid="true" aria-describedby="privacy-consent-error"' : '' ?>>
                    <span>I have read the <a href="<?= e(route('privacy')) ?>">Privacy Notice</a> and agree to the processing of my personal data under the Data Privacy Act of 2012 (RA 10173). <span class="field__required" aria-hidden="true">*</span></span>
                </label>
                <?php if ($consentError !== null) : ?>
                    <p class="field__error" id="privacy-consent-error"><?= icon('alert') ?> <?= e($consentError) ?></p>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn--primary">Send my code</button>
        </div>
    </form>
</div>
