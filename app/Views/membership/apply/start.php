<?php
/**
 * @var \App\Core\View $this
 * @var bool $forPosition they came from a position's page
 */
$consentError = field_error('privacy_consent');
?>
<?php $this->start('title') ?>Apply for membership<?php $this->end() ?>
<?php $this->start('form_side') ?><?= $this->partial('partials/side_panel', ['blocks' => [['title' => 'What you need', 'items' => ['Your PUP student number', 'Your current COR', 'For a committee: your previous semester copy of grades (not for 1st years in their 1st semester)', 'PDF, JPG or PNG files, up to 5 MB each']], ['title' => 'How it works', 'items' => ['Confirm your email with a 6-digit code.', 'Choose General Membership or the committee you want to join.', 'Fill in your details and upload your documents.', 'Get a tracking code, and follow your application on Track Status.']], ['title' => 'Need help?', 'text' => 'Email the MSW Committee and we will get back to you.', 'contact' => true]]]) ?><?php $this->end() ?>
<?php $this->start('description') ?>Apply for AGILE membership. You do not need an account. We confirm your email with a one-time code.<?php $this->end() ?>
<div class="welfare-page">
    <h1>Apply for membership</h1>
    <?php if ($forPosition) : ?>
        <p>Positions are open to AGILE members. If you are a member, please <a href="<?= e(route('login')) ?>">log in</a>. If not, apply for general membership first.</p>
    <?php endif; ?>
    <p>You do not need an account. We send a 6-digit code to confirm that this email is yours. If you are approved, you become a member, and you can then create your account with your PUP student number.</p>
    <form method="post" action="<?= e(route('apply.code')) ?>">
        <?= csrf_field() ?>
        <?= $this->partial('partials/form_field', ['name' => 'first_name', 'label' => 'First name', 'value' => (string) old('first_name'), 'autocomplete' => 'given-name']) ?>
        <?= $this->partial('partials/form_field', ['name' => 'last_name', 'label' => 'Last name', 'value' => (string) old('last_name'), 'autocomplete' => 'family-name']) ?>
        <?= $this->partial('partials/form_field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => (string) old('email'), 'autocomplete' => 'email']) ?>
        <?= $this->partial('partials/form_field', ['name' => 'student_no', 'label' => 'PUP student number', 'value' => (string) old('student_no'), 'autocomplete' => 'off', 'help' => 'For example, 2024-xxxxx-MN-0.']) ?>
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
    </form>
    <p>Already a member? <a href="<?= e(route('login')) ?>">Log in</a>. Already applied? <a href="<?= e(route('track')) ?>">Track your application</a>.</p>

    <section class="apply-requirements" aria-labelledby="requirements-title">
        <h2 id="requirements-title">Membership requirements</h2>
        <p>You can apply for General Membership, or to join a committee as a Committee Member. Prepare these documents:</p>
        <?= $this->partial('membership/_requirements') ?>
    </section>
</div>
