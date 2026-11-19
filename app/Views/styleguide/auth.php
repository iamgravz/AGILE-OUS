<?php /** @var \App\Core\View $this */ ?>
<?php $this->start('title') ?>Style Guide: auth layout<?php $this->end() ?>
<?php $this->start('head') ?><link rel="stylesheet" href="<?= e(asset('css/styleguide.css')) ?>"><?php $this->end() ?>
<h1>Log in</h1>
<p class="sg-note">Sample form for the auth layout. It submits nothing.</p>
<form>
    <div class="field">
        <label class="field__label" for="sg-auth-email">Email <span class="field__required" aria-hidden="true">*</span></label>
        <input class="input" id="sg-auth-email" type="email" autocomplete="username" required>
    </div>
    <div class="field">
        <label class="field__label" for="sg-auth-password">Password <span class="field__required" aria-hidden="true">*</span></label>
        <input class="input" id="sg-auth-password" type="password" autocomplete="current-password" required>
    </div>
    <div class="stack">
        <button type="button" class="btn btn--primary">Log in</button>
        <p><a href="/styleguide">Back to the style guide</a></p>
    </div>
</form>
