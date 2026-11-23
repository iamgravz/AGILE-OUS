<?php
/**
 * The login page, in the site's own layout (header, footer, glass stage) like Track Status. Iskobot stays above the form.
 *
 * @var \App\Core\View $this
 */
?>
<?php $this->start('title') ?>Log in<?php $this->end() ?>
<?php $this->start('description') ?>Log in to your AGILE member or officer account.<?php $this->end() ?>
<?php $this->start('head') ?>
<link rel="stylesheet" href="<?= e(asset('css/robot.css')) ?>">
<script src="<?= e(asset('js/login.js')) ?>" defer></script>
<?php $this->end() ?>
<?php $this->start('no_flash') ?>1<?php $this->end() ?>
<?php $this->start('hero') ?>
<section class="glass-stage" data-icons="<?= e(asset('img/icons.svg')) ?>">
    <div class="container">
        <?= $this->partial('partials/flash') ?>
        <div class="form-layout">
            <div class="form-layout__main login-page robot-stage">
                <h1>Log in to AGILE</h1>
                <p class="portal-lead">For AGILE members and officers. Use the email and password of your AGILE account.</p>

                <?= $this->partial('partials/robot', ['bubble' => "Mabuhay! I'm Iskobot. Welcome back to AGILE!"]) ?>

                <form class="card glass robot-card" method="post" action="<?= e(route('login')) ?>">
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

            </div>
        </div>
    </div>
</section>
<?php $this->end() ?>
