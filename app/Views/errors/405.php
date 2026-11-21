<?php /** @var \App\Core\View $this */ ?>
<?php $this->start('title') ?>Action not allowed<?php $this->end() ?>
<div class="error-page">
    <p class="error-page__code">405</p>
    <h1>Action not allowed</h1>
    <p>This page cannot be used that way.</p>
    <a class="btn btn--primary" href="<?= e(route('home')) ?>">Go to the home page</a>
</div>
