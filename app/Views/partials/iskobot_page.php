<?php
/**
 * The page around the registration steps: moving watermark, shapes, brand and the flash messages.
 * The step view draws Iskobot (partials/iskobot) and its own card.
 *
 * @var \App\Core\View $this
 * @var string $content the step view's output
 */
$mottoRows = [
    'ACADEMIC GUILD OF IT LEARNERS AND EXPERTS · AGILE-OUS · SINTANG PAARALAN · ',
    'POLYTECHNIC UNIVERSITY OF THE PHILIPPINES · BSIT-OUS · ISKOLAR NG BAYAN · ',
    "MULA SA'YO, PARA SA BAYAN · MEMBERSHIP AND STUDENT WELFARE SYSTEM · ",
];
?>
<div class="auth-page--iskobot" data-icons="<?= e(asset('img/icons.svg')) ?>">
    <div class="iskobot-watermark" aria-hidden="true">
        <?php foreach ([...$mottoRows, ...$mottoRows] as $row) : ?>
            <div class="iskobot-watermark__row"><?= e(str_repeat($row, 3)) ?></div>
        <?php endforeach; ?>
    </div>
    <div class="iskobot-shapes" aria-hidden="true">
        <span class="iskobot-shape iskobot-shape--cube"></span>
        <span class="iskobot-shape iskobot-shape--frame"></span>
        <span class="iskobot-shape iskobot-shape--glow"></span>
        <span class="iskobot-shape iskobot-shape--star iskobot-shape--star1">★</span>
        <span class="iskobot-shape iskobot-shape--star iskobot-shape--star2">★</span>
        <span class="iskobot-shape iskobot-shape--star iskobot-shape--star3">★</span>
    </div>

    <div class="iskobot-wrap">
        <a class="iskobot-brand" href="/">
            <img class="iskobot-brand__logo" src="<?= e(asset('img/logo-small.png')) ?>" alt="" width="48" height="48">
            <span class="iskobot-brand__text">
                <span class="iskobot-brand__title">AGILE</span>
                <span class="iskobot-brand__sub">Academic Guild of IT Learners and Experts · PUP-OUS</span>
            </span>
        </a>

        <main id="main" class="iskobot-main" tabindex="-1">
            <?= $this->partial('partials/flash') ?>
            <?= $content ?>
        </main>

        <p class="iskobot-motto">Academic Guild of IT Learners and Experts · Mula sa'yo, para sa Bayan</p>
        <p class="iskobot-links">
            <a href="/">Back to home</a>
            <a href="/privacy">Privacy Notice</a>
        </p>
    </div>
</div>
