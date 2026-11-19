<?php
/**
 * @var \App\Core\View $this
 * @var string $token
 */
?>
<?php $this->start('title') ?>Continue your application<?php $this->end() ?>
<?php $this->start('form_side') ?><?= $this->partial('partials/side_panel', ['blocks' => [['title' => 'What you need', 'items' => ['Your PUP student number', 'A clear copy of your school ID', 'Your proof of enrollment (COR)', 'PDF, JPG or PNG files, up to 5 MB each']], ['title' => 'How it works', 'items' => ['Confirm your email with a 6-digit code.', 'Fill in your details and upload your documents.', 'Get a tracking code, and follow your application on Track Status.']], ['title' => 'Need help?', 'text' => 'Email the MSW Committee and we will get back to you.', 'contact' => true]]]) ?><?php $this->end() ?>
<div class="welfare-page">
    <h1>Continue your application</h1>
    <p>This link works once. Choose the button below when you are ready to work on your application.</p>
    <form method="post" action="<?= e(route('apply.continue')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <button type="submit" class="btn btn--primary">Open my application</button>
    </form>
</div>
