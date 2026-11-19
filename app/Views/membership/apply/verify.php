<?php
/**
 * @var \App\Core\View $this
 * @var string $email
 */
?>
<?php $this->start('title') ?>Enter your code<?php $this->end() ?>
<?php $this->start('form_side') ?><?= $this->partial('partials/side_panel', ['blocks' => [['title' => 'What you need', 'items' => ['Your PUP student number', 'A clear copy of your school ID', 'Your proof of enrollment (COR)', 'PDF, JPG or PNG files, up to 5 MB each']], ['title' => 'How it works', 'items' => ['Confirm your email with a 6-digit code.', 'Fill in your details and upload your documents.', 'Get a tracking code, and follow your application on Track Status.']], ['title' => 'Need help?', 'text' => 'Email the MSW Committee and we will get back to you.', 'contact' => true]]]) ?><?php $this->end() ?>
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
