<?php
/**
 * @var \App\Core\View $this
 * @var bool $robotPage (optional) the page draws its own card: the robot on the login page
 */
$robotPage ??= false;
// Registration pages ask for the glass card by setting this section.
$glassPage = $this->section('auth_glass') !== '';
?>
<!doctype html>
<html lang="en">
<head>
    <?= $this->partial('partials/head') ?>
</head>
<body>
    <a class="skip-link" href="#main">Skip to main content</a>

    <div class="auth-page<?= $robotPage ? ' auth-page--robot' : '' ?><?= $glassPage ? ' auth-page--glass' : '' ?>">
        <div class="auth-page__grid">
        <main id="main" class="auth-card" tabindex="-1">
            <div class="auth-card__brand">
                <a class="brand" href="/">
                    <img class="brand__logo" src="<?= e(asset('img/logo.png')) ?>" alt="" width="72" height="72">
                    <span class="brand__name">AGILE-OUS</span>
                </a>
            </div>
            <?= $this->partial('partials/flash') ?>
            <?php if ($robotPage) : ?>
                <?= $this->section('content') ?>
            <?php else : ?>
                <div class="card<?= $glassPage ? ' glass' : '' ?>">
                    <div class="card__body">
                        <?= $this->section('content') ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
        <aside class="form-layout__side" aria-label="About logging in"><?= $this->partial('partials/side_panel', ['blocks' => [
            ['title' => 'Who can log in', 'text' => 'AGILE members and officers. You do not need an account to apply for membership, ask for help or track a status.'],
            ['title' => 'How registration works', 'items' => ['Your membership application is approved.', 'Enter your PUP student number on the registration page.', 'We send a code to the email on your application.', 'Choose a password. The MSW Committee Head then approves your account.']],
            ['title' => 'Without an account', 'links' => [['Apply for membership', route('apply')], ['Track Status', route('track')], ['Ask for Help', route('ask_for_help')]]],
        ]]) ?></aside>
        </div>
        <p class="auth-page__links">
            <a href="/">Back to home</a>
            <a href="/privacy">Privacy Notice</a>
        </p>
    </div>

    <?= $this->partial('partials/overlays') ?>
</body>
</html>
