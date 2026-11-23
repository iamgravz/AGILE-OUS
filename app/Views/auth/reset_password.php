<?php
/**
 * Set a new password from the emailed link, in the login page's look.
 *
 * @var \App\Core\View $this
 * @var string $token
 */
?>
<?php $this->start('title') ?>Set a new password<?php $this->end() ?>
<?php ob_start(); ?>
<form class="card glass robot-card" method="post" action="<?= e(route('password.reset')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <div class="card__body">
        <?= $this->partial('partials/form_field', ['name' => 'password', 'label' => 'New password', 'type' => 'password', 'autocomplete' => 'new-password', 'help' => 'At least 8 characters, with letters and numbers.']) ?>
        <?= $this->partial('partials/form_field', ['name' => 'password_confirmation', 'label' => 'Confirm new password', 'type' => 'password', 'autocomplete' => 'new-password']) ?>
        <button type="submit" class="btn btn--primary btn--block">Save my new password</button>
    </div>
</form>
<?php $form = (string) ob_get_clean(); ?>
<?= $this->partial('auth/_robot_page', [
    'heading' => 'Set a new password',
    'lead' => 'Choose a new password for your AGILE account.',
    'bubble' => 'Almost there! Pick a password only you know.',
    'form' => $form,
]) ?>
