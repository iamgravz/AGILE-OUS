<?php
/** @var \App\Core\View $this */
?>
<?php $this->start('title') ?>Enter your code<?php $this->end() ?>
<?php $this->start('auth_glass') ?>1<?php $this->end() ?>
<h1>Enter your code</h1>
<p>If this student number has an approved AGILE membership, we sent a 6-digit code to the email address used in the application. It expires in 15 minutes.</p>
<form method="post" action="<?= e(route('register.verify')) ?>">
    <?= csrf_field() ?>
    <?= $this->partial('partials/form_field', ['name' => 'code', 'label' => 'Code', 'autocomplete' => 'one-time-code']) ?>
    <button type="submit" class="btn btn--primary btn--block">Confirm my code</button>
</form>
<div class="cluster welfare-notice">
    <form method="post" action="<?= e(route('register.resend')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn--secondary btn--sm">Send a new code</button>
    </form>
    <form method="post" action="<?= e(route('register.restart')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn--ghost btn--sm">Use a different student number</button>
    </form>
</div>
