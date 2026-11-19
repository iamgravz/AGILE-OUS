<?php
/**
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $devUsers filled only when APP_ENV=local
 */
$emailError = field_error('email');
$passwordError = field_error('password');
?>
<?php $this->start('title') ?>Log in<?php $this->end() ?>
<?php $this->start('head') ?>
<link rel="stylesheet" href="<?= e(asset('css/login-robot.css')) ?>">
<script src="<?= e(asset('js/login-robot.js')) ?>" defer></script>
<?php $this->end() ?>
<div class="robot" data-robot data-mood="idle">
    <div class="robot__top" aria-hidden="true">
        <div class="robot__bubble"><span data-robot-bubble>Hi. I'm Volt. I guard this form.</span></div>
        <div class="robot__antenna"><span class="robot__antenna-tip"></span></div>
        <div class="robot__head">
            <span class="robot__ear robot__ear--left"></span>
            <span class="robot__ear robot__ear--right"></span>
            <div class="robot__screen">
                <div class="robot__leds"><span></span><span></span><span></span></div>
                <div class="robot__eyes" data-robot-eyes><span></span><span></span></div>
                <div class="robot__bars"><span></span><span></span><span></span><span></span></div>
                <div class="robot__label">NOT LOOKING</div>
            </div>
        </div>
    </div>

    <div class="robot__body glass">
        <span class="robot__hand robot__hand--left" aria-hidden="true"></span>
        <span class="robot__hand robot__hand--right" aria-hidden="true"></span>
        <h1 class="robot__title"><span class="sr-only">Log in. </span>Beep boop. Who goes there?</h1>
        <form method="post" action="<?= e(route('login')) ?>">
            <?= csrf_field() ?>
            <div class="field <?= $emailError !== null ? 'field--error' : '' ?>">
                <label class="field__label" for="email">Email <span class="field__required" aria-hidden="true">*</span></label>
                <div class="robot__input">
                    <?= icon('mail') ?>
                    <input class="input" id="email" name="email" type="email" value="<?= e(old('email')) ?>" autocomplete="username" required<?= $emailError !== null ? ' aria-invalid="true" aria-describedby="email-error"' : '' ?>>
                </div>
                <?php if ($emailError !== null) : ?>
                    <p class="field__error" id="email-error"><?= icon('alert') ?> <?= e($emailError) ?></p>
                <?php endif; ?>
            </div>
            <div class="field <?= $passwordError !== null ? 'field--error' : '' ?>">
                <label class="field__label" for="password">Password <span class="field__required" aria-hidden="true">*</span></label>
                <div class="robot__input robot__input--password">
                    <?= icon('lock') ?>
                    <input class="input" id="password" name="password" type="password" autocomplete="current-password" required<?= $passwordError !== null ? ' aria-invalid="true" aria-describedby="password-error"' : '' ?>>
                    <button type="button" class="icon-btn robot__reveal" data-password-toggle aria-controls="password" aria-pressed="false" aria-label="Show password">
                        <span data-eye-open><?= icon('eye') ?></span>
                        <span data-eye-closed hidden><?= icon('eye-off') ?></span>
                    </button>
                </div>
                <?php if ($passwordError !== null) : ?>
                    <p class="field__error" id="password-error"><?= icon('alert') ?> <?= e($passwordError) ?></p>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn--primary btn--block robot__submit">Log in</button>
        </form>
    </div>

    <div class="robot__feet" aria-hidden="true"><span></span><span></span></div>
</div>

<div class="card login-extras">
    <div class="card__body">
        <ul class="auth-card__links">
            <li><a href="<?= e(route('password.forgot')) ?>">Forgot your password?</a></li>
            <li>Not a member yet? <a href="<?= e(route('apply')) ?>">Apply for membership</a></li>
            <li>Approved applicant? <a href="<?= e(route('register')) ?>">Create your account</a></li>
        </ul>
        <?php if ($devUsers !== []) : ?>
            <section class="dev-switcher" aria-labelledby="dev-switcher-title">
                <h2 id="dev-switcher-title" class="dev-switcher__title">Log in as... (local only)</h2>
                <form method="post" action="<?= e(route('dev.login')) ?>">
                    <?= csrf_field() ?>
                    <ul class="dev-switcher__list">
                        <?php foreach ($devUsers as $user) : ?>
                            <li>
                                <button type="submit" class="btn btn--secondary btn--sm btn--block" name="email" value="<?= e($user['email']) ?>">
                                    <?= e($user['role_name']) ?>: <?= e($user['first_name'] . ' ' . $user['last_name']) ?>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </form>
            </section>
        <?php endif; ?>
    </div>
</div>
