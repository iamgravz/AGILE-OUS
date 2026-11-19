<?php
/**
 * @var \App\Core\View $this
 * @var bool $guestEnabled
 */
?>
<?php $this->start('title') ?>Ask for help<?php $this->end() ?>
<?php $this->start('description') ?>Ask AGILE for help with an academic, financial or personal concern. Your concern is handled confidentially.<?php $this->end() ?>
<h1>Ask for help</h1>
<p class="portal-lead">Tell us about an academic, financial or personal concern. It is handled confidentially by the MSW Committee Head and the President.</p>
<div class="grid grid--2">
    <div class="card">
        <div class="card__body">
            <h2>I am an AGILE member</h2>
            <p>Log in to submit your concern and follow it from your account.</p>
            <a class="btn btn--primary" href="/welfare/new">Log in to submit</a>
        </div>
    </div>
    <?php if ($guestEnabled) : ?>
        <div class="card">
            <div class="card__body">
                <h2>I am not a member</h2>
                <p>Continue as a guest. We will send a code to your email to confirm it is yours, and you can track your concern with its case code and that email.</p>
                <a class="btn btn--secondary" href="<?= e(route('welfare.guest')) ?>">Continue as guest</a>
            </div>
        </div>
    <?php endif; ?>
</div>
