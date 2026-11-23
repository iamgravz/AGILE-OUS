<?php /** @var \App\Core\View $this */ ?>
<?php $this->start('title') ?>Page not found<?php $this->end() ?>
<div class="error-page">
    <p class="error-page__code">404</p>
    <h1>Page not found</h1>
    <p>The page you are looking for does not exist or has been moved.</p>
    <a class="btn btn--primary" href="<?= e(route('home')) ?>">Go to the home page</a>
</div>
