<?php
/**
 * @var \App\Core\View $this
 * @var string $token
 */
?>
<?php $this->start('title') ?>Continue your application<?php $this->end() ?>
<div class="welfare-page">
    <h1>Continue your application</h1>
    <p>This link works once. Choose the button below when you are ready to work on your application.</p>
    <form method="post" action="<?= e(route('apply.continue')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <button type="submit" class="btn btn--primary">Open my application</button>
    </form>
</div>
