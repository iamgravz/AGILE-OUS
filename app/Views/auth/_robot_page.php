<?php
/**
 * The password pages in the login page's look: the site layout, the navy stage, Iskobot standing on the card.
 * Each page passes its heading, lead, Iskobot's line and the form (built by the page).
 *
 * @var \App\Core\View $this
 * @var string $heading
 * @var string $lead
 * @var string $bubble
 * @var string $form trusted HTML: the page's <form class="card glass robot-card"> ... </form>
 */
?>
<?php $this->start('head') ?>
<link rel="stylesheet" href="<?= e(asset('css/robot.css')) ?>">
<script src="<?= e(asset('js/login.js')) ?>" defer></script>
<?php $this->end() ?>
<?php $this->start('no_flash') ?>1<?php $this->end() ?>
<?php $this->start('hero') ?>
<section class="glass-stage" data-icons="<?= e(asset('img/icons.svg')) ?>">
    <div class="container">
        <?= $this->partial('partials/flash') ?>
        <div class="form-layout">
            <div class="form-layout__main login-page robot-stage">
                <h1><?= e($heading) ?></h1>
                <p class="portal-lead"><?= e($lead) ?></p>
                <?= $this->partial('partials/robot', ['bubble' => $bubble]) ?>
                <?= $form ?>
            </div>
        </div>
    </div>
</section>
<?php $this->end() ?>
