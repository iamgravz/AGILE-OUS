<?php
/**
 * A new account's first password, from the emailed link, in the login page's look.
 *
 * @var \App\Core\View $this
 * @var string $token
 */
?>
<?php $this->start('title') ?>Set your password<?php $this->end() ?>
<?php ob_start(); ?>
<form class="card glass robot-card" method="post" action="<?= e(route('account.setup')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <div class="card__body">
        <?= $this->partial('partials/form_field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'autocomplete' => 'new-password', 'help' => 'At least 8 characters, with letters and numbers.']) ?>
        <?= $this->partial('partials/form_field', ['name' => 'password_confirmation', 'label' => 'Confirm password', 'type' => 'password', 'autocomplete' => 'new-password']) ?>
        <button type="submit" class="btn btn--primary btn--block">Set my password</button>
    </div>
</form>
<?php $form = (string) ob_get_clean(); ?>
<?= $this->partial('auth/_robot_page', [
    'heading' => 'Set your password',
    'lead' => 'Your AGILE member account is ready. Choose a password to log in.',
    'bubble' => "Welcome to AGILE! Let's set up your password.",
    'form' => $form,
]) ?>
