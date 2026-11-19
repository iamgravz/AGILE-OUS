<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $input
 * @var array<string, string> $errors
 * @var string $email
 * @var list<array<string, mixed>> $categories
 * @var int $maxMb
 * @var bool $hasAccount
 */
?>
<?php $this->start('title') ?>Tell us about your concern<?php $this->end() ?>
<?php $this->start('form_side') ?><?= $this->partial('partials/side_panel', ['blocks' => [['title' => 'Your concern is confidential', 'text' => 'Only the MSW Committee Head and the President can read it. It is stored encrypted, and every view is logged.'], ['title' => 'What happens next', 'items' => ['You get a case code by email.', 'A handler reviews your concern. Urgent and harassment cases are acknowledged within 24 hours.', 'You can follow its status with your case code.']], ['title' => 'In immediate danger?', 'text' => 'Please contact emergency services or your local authorities first. This form is not monitored around the clock.']]]) ?><?php $this->end() ?>
<?php $this->start('head') ?>
<script src="<?= e(asset('js/welfare-form.js')) ?>" defer></script>
<?php $this->end() ?>
<div class="welfare-page">
    <h1>Tell us about your concern</h1>
    <p class="text-muted">Step 3 of 3: You are submitting as <strong><?= e($email) ?></strong>.</p>
    <?php if ($hasAccount) : ?>
        <div class="alert alert--warning welfare-notice" role="status">
            <?= icon('info') ?>
            <p class="alert__text">This email already has an account. <a href="<?= e(route('login')) ?>">Log in</a> to follow your concern from My Welfare Cases, or continue here as a guest.</p>
        </div>
    <?php endif; ?>
    <?= $this->partial('welfare/_concern_form', [
        'action' => route('welfare.guest.submit'),
        'input' => $input,
        'errors' => $errors,
        'categories' => $categories,
        'maxMb' => $maxMb,
        'closedCases' => null,
        'withConsent' => false,
    ]) ?>
</div>
