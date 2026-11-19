<?php
/**
 * @var \App\Core\View $this
 * @var string $token
 */
?>
<?php $this->start('title') ?>Set your password<?php $this->end() ?>
<h1>Set your password</h1>
<p>Your AGILE member account is ready. Choose a password to log in.</p>
<form method="post" action="<?= e(route('account.setup')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <?= $this->partial('partials/form_field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'autocomplete' => 'new-password', 'help' => 'At least 8 characters, with letters and numbers.']) ?>
    <?= $this->partial('partials/form_field', ['name' => 'password_confirmation', 'label' => 'Confirm password', 'type' => 'password', 'autocomplete' => 'new-password']) ?>
    <button type="submit" class="btn btn--primary btn--block">Set my password</button>
</form>
