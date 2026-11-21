<?php
/**
 * @var \App\Core\View $this
 * @var string $email
 */
?>
<?php $this->start('title') ?>Enter your code<?php $this->end() ?>
<div class="welfare-page">
    <h1>Enter your code</h1>
    <p>Enter the 6-digit code we sent to <strong><?= e($email) ?></strong>. It expires in 15 minutes.</p>
    <form method="post" action="<?= e(route('apply.verify')) ?>">
        <?= csrf_field() ?>
        <?= $this->partial('partials/form_field', ['name' => 'code', 'label' => 'Code', 'autocomplete' => 'one-time-code']) ?>
        <button type="submit" class="btn btn--primary">Confirm my email</button>
    </form>
    <div class="cluster welfare-notice">
        <form method="post" action="<?= e(route('apply.resend')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn--secondary btn--sm">Send a new code</button>
        </form>
        <form method="post" action="<?= e(route('apply.restart')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn--ghost btn--sm">Use a different email</button>
        </form>
    </div>
</div>
