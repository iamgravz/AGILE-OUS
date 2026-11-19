<?php
/**
 * @var \App\Core\View $this
 * @var string $title
 */
?>
<?php $this->start('title') ?><?= e($title) ?><?php $this->end() ?>
<?php $this->start('form_side') ?>
<?= $this->partial('errors/_next') ?>
<?php $this->end() ?>
<div class="error-page">
    <h1><?= e($title) ?></h1>
    <p>This page is not available yet. If you need help now, please email <a href="mailto:<?= e(config('app.contact_email')) ?>"><?= e(config('app.contact_email')) ?></a>.</p>
    <a class="btn btn--primary" href="<?= e(route('home')) ?>">Go to the home page</a>
</div>
