<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $application
 * @var list<array<string, mixed>> $questions
 * @var array<int, string> $answers
 * @var int $step
 * @var int $reached
 */
$uploadError = field_error('upload');
?>
<?php $this->start('title') ?>Apply: Questions<?php $this->end() ?>
<?php $this->start('page_title') ?>Membership application<?php $this->end() ?>
<?php $this->start('form_side') ?><?= $this->partial('membership/apply/_side', compact('application', 'step', 'reached')) ?><?php $this->end() ?>
<?= $this->partial('membership/apply/_stepper', compact('application', 'step', 'reached')) ?>
<form class="card wizard-form" method="post" enctype="multipart/form-data" action="<?= e(route('apply.step', ['id' => $application['application_id'], 'step' => 4])) ?>">
    <?= csrf_field() ?>
    <div class="card__body">
        <h2>Questions</h2>
        <p class="text-muted"><span class="field__required" aria-hidden="true">*</span> marks a required question.</p>
        <?php if ($uploadError !== null) : ?>
            <p class="field__error"><?= icon('alert') ?> <?= e($uploadError) ?></p>
        <?php endif; ?>
        <?php foreach ($questions as $question) : ?>
            <?= $this->partial('membership/apply/_question', compact('question', 'answers')) ?>
        <?php endforeach; ?>
    </div>
    <?= $this->partial('membership/apply/_actions', compact('application', 'step')) ?>
</form>
