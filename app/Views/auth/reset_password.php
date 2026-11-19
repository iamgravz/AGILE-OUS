<?php
/**
 * @var \App\Core\View $this
 * @var string $token
 */
?>
<?php $this->start('title') ?>Set a new password<?php $this->end() ?>
<h1>Set a new password</h1>
<form method="post" action="<?= e(route('password.reset')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <?= $this->partial('partials/form_field', ['name' => 'password', 'label' => 'New password', 'type' => 'password', 'autocomplete' => 'new-password', 'help' => 'At least 8 characters, with letters and numbers.']) ?>
    <?= $this->partial('partials/form_field', ['name' => 'password_confirmation', 'label' => 'Confirm new password', 'type' => 'password', 'autocomplete' => 'new-password']) ?>
    <button type="submit" class="btn btn--primary btn--block">Save my new password</button>
</form>
