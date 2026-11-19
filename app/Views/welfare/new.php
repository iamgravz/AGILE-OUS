<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $input
 * @var array<string, string> $errors
 * @var list<array<string, mixed>> $categories
 * @var list<array{case_id: int, label: string}> $closedCases
 * @var int $maxMb
 */
?>
<?php $this->start('title') ?>Ask for help<?php $this->end() ?>
<?php $this->start('form_side') ?><?= $this->partial('partials/side_panel', ['blocks' => [['title' => 'Your concern is confidential', 'text' => 'Only the MSW Committee Head and the President can read it. It is stored encrypted, and every view is logged.'], ['title' => 'What happens next', 'items' => ['You get a case code by email.', 'A handler reviews your concern. Urgent and harassment cases are acknowledged within 24 hours.', 'You can follow its status with your case code.']], ['title' => 'In immediate danger?', 'text' => 'Please contact emergency services or your local authorities first. This form is not monitored around the clock.']]]) ?><?php $this->end() ?>
<?php $this->start('page_title') ?>Ask for help<?php $this->end() ?>
<?php $this->start('head') ?>
<script src="<?= e(asset('js/welfare-form.js')) ?>" defer></script>
<?php $this->end() ?>

<div class="welfare-page">
    <?= $this->partial('welfare/_concern_form', [
        'action' => route('welfare.new'),
        'input' => $input,
        'errors' => $errors,
        'categories' => $categories,
        'maxMb' => $maxMb,
        'closedCases' => $closedCases,
        'withConsent' => true,
    ]) ?>
</div>
