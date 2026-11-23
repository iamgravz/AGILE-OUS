<?php
/**
 * Ask for Help without an account, step 1 of 3, like the login and apply pages: Iskobot standing on the card. Who they
 * are, the category and the concern itself, then a code to their email. A failed page comes back from the same request
 * in $errors and $input, so the concern never goes into the flashed session data.
 *
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $categories
 * @var array<string, string> $errors (optional)
 * @var array<string, string> $input (optional)
 */
$errors ??= [];
$input ??= [];
$value = static fn (string $key): string => (string) ($input[$key] ?? old($key, ''));
$error = static fn (string $key): ?string => $errors[$key] ?? field_error($key);
$categoryError = $error('category_id');
$descriptionError = $error('description');
$consentError = $error('privacy_consent');
$maxLength = \App\Services\WelfareService::DESCRIPTION_MAX;
$field = static fn (string $name, string $label, array $extra = []): array => ['name' => $name, 'label' => $label, 'value' => $value($name), 'error' => $error($name)] + $extra;
?>
<?php $this->start('title') ?>Ask for help<?php $this->end() ?>
<?php $this->start('description') ?>Submit a confidential concern to AGILE without an account. We confirm your email with a one-time code.<?php $this->end() ?>
<?php $this->start('head') ?>
<link rel="stylesheet" href="<?= e(asset('css/robot.css')) ?>">
<?php $this->end() ?>
<?php $this->start('no_flash') ?>1<?php $this->end() ?>
<?php $this->start('hero') ?>
<section class="glass-stage">
    <div class="container">
        <?= $this->partial('partials/flash') ?>
        <div class="form-layout">
            <div class="form-layout__main robot-stage">
                <h1>Ask for help</h1>
                <p class="portal-lead">Step 1 of 3. Tell us your concern. It stays confidential.</p>

                <?= $this->partial('partials/robot', ['bubble' => "I'm Iskobot. You're not alone. We're here to help."]) ?>

                <form class="card glass robot-card" method="post" action="<?= e(route('welfare.guest.code')) ?>">
                    <?= csrf_field() ?>
                    <div class="card__body">
                        <?php if (isset($errors['form'])) : ?>
                            <?= $this->partial('partials/alert', ['tone' => 'danger', 'message' => $errors['form']]) ?>
                        <?php endif; ?>

                        <?= $this->partial('partials/form_field', $field('name', 'Full name', ['autocomplete' => 'name'])) ?>
                        <?= $this->partial('partials/form_field', $field('email', 'Email', ['type' => 'email', 'autocomplete' => 'email', 'help' => 'We send a 6-digit code here to confirm that this email is yours.'])) ?>
                        <?= $this->partial('partials/form_field', $field('student_no', 'PUP student number', ['required' => false, 'autocomplete' => 'off', 'help' => 'Optional. For example, 2024-xxxxx-MN-0.'])) ?>
                        <?= $this->partial('partials/student_details', ['values' => $input, 'errors' => $errors, 'contactHelp' => 'So the MSW team can reach you. For example, 09171234567.']) ?>

                        <div class="field <?= $categoryError !== null ? 'field--error' : '' ?>">
                            <label class="field__label" for="category_id">Category <span class="field__required" aria-hidden="true">*</span></label>
                            <select class="select" id="category_id" name="category_id" required<?= $categoryError !== null ? ' aria-invalid="true" aria-describedby="category-error"' : '' ?>>
                                <option value="">Choose what your concern is about</option>
                                <?php foreach ($categories as $category) : ?>
                                    <option value="<?= e($category['category_id']) ?>"<?= $value('category_id') === (string) $category['category_id'] ? ' selected' : '' ?>><?= e($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($categoryError !== null) : ?>
                                <p class="field__error" id="category-error"><?= icon('alert') ?> <?= e($categoryError) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="field <?= $descriptionError !== null ? 'field--error' : '' ?>">
                            <label class="field__label" for="description">Your concern <span class="field__required" aria-hidden="true">*</span></label>
                            <textarea class="textarea" id="description" name="description" rows="6" maxlength="<?= e($maxLength) ?>" required aria-describedby="description-help<?= $descriptionError !== null ? ' description-error' : '' ?>"<?= $descriptionError !== null ? ' aria-invalid="true"' : '' ?>><?= e($value('description')) ?></textarea>
                            <p class="field__help" id="description-help">What happened, and how we can help. You can add a subject and files after confirming your email.</p>
                            <?php if ($descriptionError !== null) : ?>
                                <p class="field__error" id="description-error"><?= icon('alert') ?> <?= e($descriptionError) ?></p>
                            <?php endif; ?>
                        </div>

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
                        <p class="welfare-emergency"><?= icon('alert') ?> In immediate danger? Contact emergency services first: this form is not monitored around the clock.</p>
                        <button type="submit" class="btn btn--primary btn--block">Send my code</button>
                        <p class="login-page__links">
                            <a href="<?= e(route('login')) ?>">Have an account? Log in</a>
                            <a href="<?= e(route('track')) ?>">Already sent one? Track it</a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
<?php $this->end() ?>
