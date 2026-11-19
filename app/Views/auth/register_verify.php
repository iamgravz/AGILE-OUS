<?php
/** @var \App\Core\View $this */
?>
<?php $this->start('title') ?>Enter your code<?php $this->end() ?>
<?php $this->start('auth_style') ?>iskobot<?php $this->end() ?>
<?= $this->partial('partials/iskobot', ['step' => 2, 'label' => 'The code we emailed you', 'bubble' => 'Check your email. I sent you a 6-digit code!']) ?>
<div class="iskobot-card">
    <h1>Enter your code</h1>
    <p class="iskobot-card__lead">If this student number has an approved AGILE membership, we sent a 6-digit code to the email address used in the application. It expires in 15 minutes.</p>
    <form method="post" action="<?= e(route('register.verify')) ?>">
        <?= csrf_field() ?>
        <?= $this->partial('partials/form_field', ['name' => 'code', 'label' => 'Code', 'autocomplete' => 'one-time-code', 'class' => 'iskobot-code']) ?>
        <button type="submit" class="iskobot-btn">Confirm my code</button>
    </form>
    <div class="iskobot-card__actions">
        <form method="post" action="<?= e(route('register.resend')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="iskobot-btn iskobot-btn--light">Send a new code</button>
        </form>
        <form method="post" action="<?= e(route('register.restart')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="iskobot-link-btn">Use a different student number</button>
        </form>
    </div>
</div>
