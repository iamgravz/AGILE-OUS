<?php /** @var \App\Core\View $this */ ?>
<?php $this->start('title') ?>Forgot your password<?php $this->end() ?>
<h1>Forgot your password?</h1>
<p>Enter your account email and we will send you a link to set a new password.</p>
<form method="post" action="<?= e(route('password.forgot')) ?>">
    <?= csrf_field() ?>
    <?= $this->partial('partials/form_field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => old('email'), 'autocomplete' => 'username']) ?>
    <button type="submit" class="btn btn--primary btn--block">Email me a reset link</button>
</form>
<ul class="auth-card__links">
    <li><a href="<?= e(route('login')) ?>">Back to log in</a></li>
</ul>
