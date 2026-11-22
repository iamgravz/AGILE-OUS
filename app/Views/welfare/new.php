<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $input
 * @var array<string, string> $errors
 * @var list<array<string, mixed>> $categories
 * @var list<array{case_id: int, label: string}> $closedCases
 * @var int $maxMb
 * @var array<string, string> $studentDetails
 */
?>
<?php $this->start('title') ?>Ask for help<?php $this->end() ?>
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
        'studentDetails' => $studentDetails,
    ]) ?>
</div>
