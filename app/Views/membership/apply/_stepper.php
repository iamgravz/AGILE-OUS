<?php
/**
 * @var array<string, mixed>|null $application
 * @var int $step the current step
 * @var int $reached the furthest step saved
 */
$labels = [1 => 'Personal info', 2 => 'Academic info', 3 => 'Membership type', 4 => 'Questions', 5 => 'Documents', 6 => 'Review'];
?>
<?php $inPanel ??= false; ?>
<?php if (!$inPanel && $application !== null && $application['status'] === 'for_revision') : ?>
    <div class="alert alert--warning" role="status">
        <?= icon('alert') ?>
        <p class="alert__text">You are revising this application. Your changes are saved as you go, and reviewers see them when you resubmit.</p>
    </div>
<?php endif; ?>
<ol class="stepper wizard-stepper <?= $inPanel ? 'wizard-stepper--panel' : 'wizard-stepper--top' ?>">
    <?php foreach ($labels as $number => $label) : ?>
        <?php $isDone = $number < $step; ?>
        <li class="stepper__step <?= $isDone ? 'stepper__step--done' : '' ?> <?= $number === $step ? 'stepper__step--current' : '' ?>"<?= $number === $step ? ' aria-current="step"' : '' ?>>
            <span class="stepper__marker"><?= $isDone ? icon('check') : e($number) ?></span>
            <?php if ($application !== null && $number <= $reached && $number !== $step) : ?>
                <a href="<?= e(route('apply.step', ['id' => $application['application_id'], 'step' => $number])) ?>"><?= $isDone ? '<span class="sr-only">Completed: </span>' : '' ?><?= e($label) ?></a>
            <?php else : ?>
                <span><?= e($label) ?></span>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ol>
