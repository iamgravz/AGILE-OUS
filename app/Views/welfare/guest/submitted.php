<?php
/**
 * @var \App\Core\View $this
 * @var string $code
 * @var string $email
 */
?>
<?php $this->start('title') ?>Concern received<?php $this->end() ?>
<?php $this->start('form_side') ?><?= $this->partial('partials/side_panel', ['blocks' => [['title' => 'Your concern is confidential', 'text' => 'Only the MSW Committee Head and the President can read it. It is stored encrypted, and every view is logged.'], ['title' => 'What happens next', 'items' => ['You get a case code by email.', 'A handler reviews your concern. Urgent and harassment cases are acknowledged within 24 hours.', 'You can follow its status with your case code.']], ['title' => 'In immediate danger?', 'text' => 'Please contact emergency services or your local authorities first. This form is not monitored around the clock.']]]) ?><?php $this->end() ?>
<div class="welfare-page">
    <h1>Concern received</h1>
    <div class="card">
        <div class="card__body">
            <p>We received your concern, and a confirmation is on its way to your email.</p>
            <p class="code-box__label" id="case-label">Your case code</p>
            <div class="code-box">
                <code class="code-box__code" id="case-code" aria-labelledby="case-label"><?= e($code) ?></code>
                <button type="button" class="btn btn--secondary btn--sm" data-copy="case-code">Copy code</button>
            </div>
            <p>Track this case at <a href="<?= e(route('track')) ?>">/track</a> with your case code and this email (<?= e($email) ?>).</p>
            <p class="text-muted">Please keep this code. Attachments cannot be downloaded again after submitting, because you have no account.</p>
            <a class="btn btn--primary" href="<?= e(route('track')) ?>">Track this case</a>
        </div>
    </div>
</div>
