<?php /** @var \App\Core\View $this */ ?>
<!doctype html>
<html lang="en">
<head>
    <?= $this->partial('partials/head') ?>
</head>
<body class="site-body">
    <a class="skip-link" href="#main">Skip to main content</a>

    <?= $this->partial('partials/site_header') ?>

    <main id="main" class="site-main" tabindex="-1">
        <?= $this->section('hero') ?>
        <div class="container">
            <?php if ($this->section('no_flash') === '') : ?>
                <?= $this->partial('partials/flash') ?>
            <?php endif; ?>
            <?php if ($this->section('page_title') !== '') : ?>
                <h1><?= $this->section('page_title') ?></h1>
            <?php endif; ?>
            <?= $this->partial('partials/page_content') ?>
        </div>
    </main>

    <?= $this->partial('partials/site_footer') ?>

    <?= $this->partial('partials/overlays') ?>
</body>
</html>
