<?php
/** @var \App\Core\View $this */
$consentError = field_error('privacy_consent');
?>
<?php $this->start('title') ?>Choose your password<?php $this->end() ?>
<?php $this->start('auth_style') ?>iskobot<?php $this->end() ?>
<?= $this->partial('partials/iskobot', ['step' => 3, 'label' => 'Your password', 'bubble' => 'Last step! Pick a strong password.']) ?>
<div class="iskobot-card">
    <h1>Choose your password</h1>
    <p class="iskobot-card__lead">You will log in with the email on your application. The MSW Committee Head reviews each new account, and we email you when you can log in.</p>
    <form method="post" action="<?= e(route('register.password')) ?>">
        <?= csrf_field() ?>
        <?= $this->partial('partials/form_field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'autocomplete' => 'new-password', 'help' => 'At least 8 characters, with letters and numbers.']) ?>
        <?= $this->partial('partials/form_field', ['name' => 'password_confirmation', 'label' => 'Confirm password', 'type' => 'password', 'autocomplete' => 'new-password']) ?>
        <div class="field iskobot-consent <?= $consentError !== null ? 'field--error' : '' ?>">
            <label class="check">
                <input type="checkbox" name="privacy_consent" value="1" required<?= $consentError !== null ? ' aria-invalid="true" aria-describedby="privacy-consent-error"' : '' ?>>
                <span>I have read the <a href="<?= e(route('privacy')) ?>">Privacy Notice</a> and agree to the processing of my personal data under the <strong>Data Privacy Act of 2012 (RA 10173)</strong>. <span class="field__required" aria-hidden="true">*</span></span>
            </label>
            <?php if ($consentError !== null) : ?>
                <p class="field__error" id="privacy-consent-error"><?= icon('alert') ?> <?= e($consentError) ?></p>
            <?php endif; ?>
        </div>
        <button type="submit" class="iskobot-btn">Create my account</button>
    </form>
</div>
