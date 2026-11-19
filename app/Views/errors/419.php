<?php /** @var \App\Core\View $this */ ?>
<?php $this->start('title') ?>Page expired<?php $this->end() ?>
<?php $this->start('form_side') ?>
<?= $this->partial('errors/_next') ?>
<?php $this->end() ?>
<div class="error-page">
    <p class="error-page__code">419</p>
    <h1>Page expired</h1>
    <p>Your session or form has expired. Go back, refresh the page, and try again.</p>
    <a class="btn btn--primary" href="<?= e(route('home')) ?>">Go to the home page</a>
</div>
