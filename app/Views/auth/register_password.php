<?php
/**
 * Create your account, step 3 of 3: the password and consent, in the apply page's look.
 *
 * @var \App\Core\View $this
 */
$consentError = field_error('privacy_consent');
?>
<?php $this->start('title') ?>Choose your password<?php $this->end() ?>
<?php ob_start(); ?>
<form class="card glass robot-card" method="post" action="<?= e(route('register.password')) ?>">
    <?= csrf_field() ?>
    <div class="card__body">
        <?= $this->partial('partials/form_field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'autocomplete' => 'new-password', 'help' => 'At least 8 characters, with letters and numbers.']) ?>
        <?= $this->partial('partials/form_field', ['name' => 'password_confirmation', 'label' => 'Confirm password', 'type' => 'password', 'autocomplete' => 'new-password']) ?>
        <div class="field <?= $consentError !== null ? 'field--error' : '' ?>">
            <label class="check">
                <input type="checkbox" name="privacy_consent" value="1" required<?= $consentError !== null ? ' aria-invalid="true" aria-describedby="privacy-consent-error"' : '' ?>>
                <span>I have read the <a href="<?= e(route('privacy')) ?>">Privacy Notice</a> and agree to the processing of my personal data under the Data Privacy Act of 2012 (RA 10173). <span class="field__required" aria-hidden="true">*</span></span>
            </label>
            <?php if ($consentError !== null) : ?>
                <p class="field__error" id="privacy-consent-error"><?= icon('alert') ?> <?= e($consentError) ?></p>
            <?php endif; ?>
        </div>
        <button type="submit" class="btn btn--primary btn--block">Create my account</button>
    </div>
</form>
<?php $form = (string) ob_get_clean(); ?>
<?= $this->partial('auth/_robot_page', [
    'heading' => 'Choose your password',
    'lead' => 'Step 3 of 3. You will log in with the email on your application. The MSW Committee Head reviews each new account, and we email you when you can log in.',
    'bubble' => 'Last step! Pick a strong password.',
    'form' => $form,
]) ?>
