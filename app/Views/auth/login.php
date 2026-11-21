<?php
/**
 * The login page, in the site's own layout (header, footer, glass stage) like Track Status. Iskobot stays above the form.
 *
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $devUsers filled only when APP_ENV=local
 */
?>
<?php $this->start('title') ?>Log in<?php $this->end() ?>
<?php $this->start('description') ?>Log in to your AGILE member or officer account.<?php $this->end() ?>
<?php $this->start('head') ?>
<link rel="stylesheet" href="<?= e(asset('css/login.css')) ?>">
<script src="<?= e(asset('js/login.js')) ?>" defer></script>
<?php $this->end() ?>
<?php $this->start('no_flash') ?>1<?php $this->end() ?>
<?php $this->start('hero') ?>
<section class="glass-stage" data-icons="<?= e(asset('img/icons.svg')) ?>">
    <div class="container">
        <?= $this->partial('partials/flash') ?>
        <div class="form-layout">
            <div class="form-layout__main login-page">
                <h1>Log in to AGILE</h1>
                <p class="portal-lead">For AGILE members and officers. Use the email and password of your AGILE account.</p>

                <?php /* Iskobot sits on the card, and its hands (the card's ::before and ::after) hold the top corners. */ ?>
                <div class="robot" aria-hidden="true">
                    <div class="robot__bubble">Mabuhay! I'm Iskobot. Welcome back to AGILE!</div>
                    <div class="robot__antenna"><span class="robot__bulb"></span><span class="robot__stem"></span></div>
                    <div class="robot__head">
                        <span class="robot__eye"><span class="robot__pupil"></span></span>
                        <span class="robot__eye"><span class="robot__pupil"></span></span>
                    </div>
                </div>

                <form class="card glass login-card" method="post" action="<?= e(route('login')) ?>">
                    <?= csrf_field() ?>
                    <div class="card__body">
                        <?= $this->partial('partials/form_field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => (string) old('email'), 'autocomplete' => 'username']) ?>
                        <?= $this->partial('partials/form_field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'autocomplete' => 'current-password']) ?>
                        <button type="submit" class="btn btn--primary btn--block">Log in</button>
                        <p class="login-page__links">
                            <a href="<?= e(route('password.forgot')) ?>">Forgot your password?</a>
                            <a href="<?= e(route('register')) ?>">Approved applicant? Create your account</a>
                        </p>
                    </div>
                </form>

                <?php if ($devUsers !== []) : ?>
                    <details class="card login-dev">
                        <summary class="login-dev__summary">Log in as a demo account (local only)</summary>
                        <form method="post" action="<?= e(route('dev.login')) ?>">
                            <?= csrf_field() ?>
                            <ul class="dev-switcher__list login-dev__list">
                                <?php foreach ($devUsers as $user) : ?>
                                    <li>
                                        <button type="submit" class="btn btn--secondary btn--sm btn--block" name="email" value="<?= e($user['email']) ?>">
                                            <?= e($user['role_name']) ?>: <?= e($user['first_name'] . ' ' . $user['last_name']) ?>
                                        </button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </form>
                    </details>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php $this->end() ?>
