<?php
/**
 * The first page of applying, like the login page: Iskobot standing on the card that holds the form. Below the email
 * and student number: what they apply for (a dropdown) and its requirements (a dropdown that opens). The choice is kept
 * until step 3, where it is already selected.
 *
 * @var \App\Core\View $this
 * @var bool $forPosition they came from a position's page
 * @var list<array{committee_id: int, name: string, position_id: int}> $committees committees that take Committee Members
 */
$consentError = field_error('privacy_consent');
$choiceError = field_error('applying_for');
$choice = (string) old('applying_for', '');
$requirements = [
    'general' => 'Current COR',
    'committee' => 'Current COR and previous semester copy of grades. 1st years in their 1st semester: current COR only. 1st years joining in the 2nd semester: current COR and 1st semester copy of grades.',
];
$current = str_starts_with($choice, 'committee:') ? $requirements['committee'] : ($choice === 'general' ? $requirements['general'] : 'Choose what you are applying for above.');
?>
<?php $this->start('title') ?>Apply for membership<?php $this->end() ?>
<?php $this->start('description') ?>Apply for AGILE membership. You do not need an account. We confirm your email with a one-time code.<?php $this->end() ?>
<?php $this->start('head') ?>
<link rel="stylesheet" href="<?= e(asset('css/robot.css')) ?>">
<script src="<?= e(asset('js/apply-start.js')) ?>" defer></script>
<?php $this->end() ?>
<?php $this->start('no_flash') ?>1<?php $this->end() ?>
<?php $this->start('hero') ?>
<section class="glass-stage">
    <div class="container">
        <?= $this->partial('partials/flash') ?>
        <div class="form-layout">
            <div class="form-layout__main robot-stage apply-start">
                <h1>Apply for membership</h1>
                <?php if ($forPosition) : ?>
                    <p class="portal-lead">Officer positions are open to AGILE members. If you are a member, please <a href="<?= e(route('login')) ?>">log in</a>. If not, apply for membership or a committee first.</p>
                <?php else : ?>
                    <p class="portal-lead">You do not need an account. We send a 6-digit code to confirm that this email is yours.</p>
                <?php endif; ?>

                <?= $this->partial('partials/robot', ['bubble' => "Hi! I'm Iskobot. Let's get you into AGILE!"]) ?>

                <form class="card glass robot-card" method="post" action="<?= e(route('apply.code')) ?>">
                    <?= csrf_field() ?>
                    <div class="card__body">
                        <?= $this->partial('partials/form_field', ['name' => 'first_name', 'label' => 'First name', 'value' => (string) old('first_name'), 'autocomplete' => 'given-name']) ?>
                        <?= $this->partial('partials/form_field', ['name' => 'last_name', 'label' => 'Last name', 'value' => (string) old('last_name'), 'autocomplete' => 'family-name']) ?>
                        <?= $this->partial('partials/form_field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => (string) old('email'), 'autocomplete' => 'email']) ?>
                        <?= $this->partial('partials/form_field', ['name' => 'student_no', 'label' => 'PUP student number', 'value' => (string) old('student_no'), 'autocomplete' => 'off', 'help' => 'For example, 2024-xxxxx-MN-0.']) ?>

                        <div class="field <?= $choiceError !== null ? 'field--error' : '' ?>">
                            <label class="field__label" for="applying_for">Applying for <span class="field__required" aria-hidden="true">*</span></label>
                            <select class="select" id="applying_for" name="applying_for" required data-requirements-select<?= $choiceError !== null ? ' aria-invalid="true" aria-describedby="applying-for-error"' : '' ?>>
                                <option value="">Choose a position</option>
                                <option value="general" data-requirements="<?= e($requirements['general']) ?>"<?= $choice === 'general' ? ' selected' : '' ?>>General Membership</option>
                                <?php if ($committees !== []) : ?>
                                    <optgroup label="Committee Member">
                                        <?php foreach ($committees as $committee) : ?>
                                            <?php $value = 'committee:' . $committee['committee_id']; ?>
                                            <option value="<?= e($value) ?>" data-requirements="<?= e($requirements['committee']) ?>"<?= $choice === $value ? ' selected' : '' ?>>Committee Member, <?= e($committee['name']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                            <?php if ($choiceError !== null) : ?>
                                <p class="field__error" id="applying-for-error"><?= icon('alert') ?> <?= e($choiceError) ?></p>
                            <?php endif; ?>
                        </div>

                        <details class="apply-requirements-dropdown">
                            <summary>Requirements: <span data-requirements-text><?= e($current) ?></span></summary>
                            <?= $this->partial('membership/_requirements') ?>
                        </details>

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
                        <button type="submit" class="btn btn--primary btn--block">Send my code</button>
                        <p class="login-page__links">
                            <a href="<?= e(route('login')) ?>">Already a member? Log in</a>
                            <a href="<?= e(route('track')) ?>">Already applied? Track it</a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
<?php $this->end() ?>
