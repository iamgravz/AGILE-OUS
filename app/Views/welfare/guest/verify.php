<?php
/**
 * @var \App\Core\View $this
 * @var string $email
 */
?>
<?php $this->start('title') ?>Enter your code<?php $this->end() ?>
<div class="welfare-page">
    <h1>Enter your code</h1>
    <p class="text-muted">Step 2 of 3: Enter the 6-digit code we sent to <strong><?= e($email) ?></strong>. It expires in 15 minutes.</p>
    <form class="card" method="post" action="<?= e(route('welfare.guest.verify')) ?>">
        <?= csrf_field() ?>
        <div class="card__body">
            <?= $this->partial('partials/form_field', ['name' => 'code', 'label' => 'Code', 'autocomplete' => 'one-time-code']) ?>
            <button type="submit" class="btn btn--primary">Confirm my email</button>
        </div>
    </form>
    <div class="cluster welfare-notice">
        <form method="post" action="<?= e(route('welfare.guest.resend')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn--secondary btn--sm">Send a new code</button>
        </form>
        <form method="post" action="<?= e(route('welfare.guest.restart')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn--ghost btn--sm">Use a different email</button>
        </form>
    </div>
</div>
