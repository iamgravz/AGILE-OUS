<?php /** @var \App\Core\View $this */ ?>
<?php $this->start('title') ?>Something went wrong<?php $this->end() ?>
<?php $this->start('form_side') ?>
<?= $this->partial('errors/_next') ?>
<?php $this->end() ?>
<div class="error-page">
    <p class="error-page__code">500</p>
    <h1>Something went wrong</h1>
    <p>We could not complete your request. Please try again later.</p>
    <a class="btn btn--primary" href="/">Go to the home page</a>
</div>
