<?php /** @var \App\Core\View $this */ ?>
<?php $this->start('title') ?>Access denied<?php $this->end() ?>
<?php $this->start('form_side') ?>
<?= $this->partial('errors/_next') ?>
<?php $this->end() ?>
<div class="error-page">
    <p class="error-page__code">403</p>
    <h1>Access denied</h1>
    <p>You do not have permission to view this page.</p>
    <a class="btn btn--primary" href="<?= e(route('home')) ?>">Go to the home page</a>
</div>
