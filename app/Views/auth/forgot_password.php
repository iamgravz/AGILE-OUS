<?php
/**
 * Forgot your password, in the login page's look (Iskobot on the card).
 *
 * @var \App\Core\View $this
 */
?>
<?php $this->start('title') ?>Forgot your password<?php $this->end() ?>
<?php ob_start(); ?>
<form class="card glass robot-card" method="post" action="<?= e(route('password.forgot')) ?>">
    <?= csrf_field() ?>
    <div class="card__body">
        <?= $this->partial('partials/form_field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => (string) old('email'), 'autocomplete' => 'username']) ?>
        <button type="submit" class="btn btn--primary btn--block">Email me a reset link</button>
        <p class="login-page__links">
            <a href="<?= e(route('login')) ?>">Back to log in</a>
            <a href="<?= e(route('home')) ?>">Back to home</a>
        </p>
    </div>
</form>
<?php $form = (string) ob_get_clean(); ?>
<?= $this->partial('auth/_robot_page', [
    'heading' => 'Forgot your password?',
    'lead' => 'Enter your account email and we will send you a link to set a new password.',
    'bubble' => "Don't worry, it happens! I'll send you a reset link.",
    'form' => $form,
]) ?>
