<?php
/**
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $devUsers filled only when APP_ENV=local
 */
?>
<?php $this->start('title') ?>Log in<?php $this->end() ?>
<?php $this->start('auth_style') ?>iskobot<?php $this->end() ?>
<?= $this->partial('partials/iskobot', ['label' => 'Log in to your AGILE account', 'icon' => 'lock', 'bubble' => "Mabuhay! I'm Iskobot. Welcome back to AGILE!"]) ?>
<div class="iskobot-card">
    <h1>Log in to AGILE</h1>
    <p class="iskobot-card__lead">For AGILE members and officers. Use the email and password of your AGILE account.</p>

    <div class="iskobot-note">
        <p class="iskobot-note__title"><?= icon('info') ?> With your AGILE account, you can:</p>
        <ul>
            <li>See your <strong>digital ID and certificate</strong> and download them</li>
            <li>Apply for <strong>committee and officer positions</strong></li>
            <li>Follow your <strong>welfare concerns</strong> from your own dashboard</li>
        </ul>
    </div>

    <form method="post" action="<?= e(route('login')) ?>">
        <?= csrf_field() ?>
        <?= $this->partial('partials/form_field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => (string) old('email'), 'autocomplete' => 'username']) ?>
        <?= $this->partial('partials/form_field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'autocomplete' => 'current-password']) ?>
        <button type="submit" class="iskobot-btn">Log in</button>
    </form>

    <p class="iskobot-card__foot"><a href="<?= e(route('password.forgot')) ?>">Forgot your password?</a></p>
    <p class="iskobot-card__foot">Not a member yet? <a href="<?= e(route('apply')) ?>">Apply for membership</a>. Approved applicant? <a href="<?= e(route('register')) ?>">Create your account</a>.</p>

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
