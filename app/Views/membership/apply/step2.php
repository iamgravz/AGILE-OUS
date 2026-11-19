<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $application
 * @var array<string, mixed> $user
 * @var list<array<string, mixed>> $academicQuestions
 * @var array<int, string> $answers
 * @var int $step
 * @var int $reached
 */
$enrolledError = field_error('enrolled');
$studentError = field_error('student_no');
?>
<?php $this->start('title') ?>Apply: Academic info<?php $this->end() ?>
<?php $this->start('page_title') ?>Membership application<?php $this->end() ?>
<?php $this->start('form_side') ?><?= $this->partial('membership/apply/_side', compact('application', 'step', 'reached')) ?><?php $this->end() ?>
<?= $this->partial('membership/apply/_stepper', compact('application', 'step', 'reached')) ?>
<form class="card wizard-form" method="post" action="<?= e(route('apply.step', ['id' => $application['application_id'], 'step' => 2])) ?>">
    <?= csrf_field() ?>
    <div class="card__body">
        <h2>Academic info</h2>
        <dl class="detail-list wizard-details">
            <div><dt>Student number</dt><dd><?= e($user['student_no'] ?? 'Not set') ?></dd></div>
            <div><dt>Program</dt><dd>BSIT-OU</dd></div>
        </dl>
        <?php if ($studentError !== null) : ?>
            <p class="field__error"><?= icon('alert') ?> <?= e($studentError) ?></p>
        <?php endif; ?>
        <?php foreach ($academicQuestions as $question) : ?>
            <?= $this->partial('membership/apply/_question', compact('question', 'answers')) ?>
        <?php endforeach; ?>
        <div class="field <?= $enrolledError !== null ? 'field--error' : '' ?>">
            <label class="check">
                <input type="checkbox" name="enrolled" value="1"<?= $application['enrollment_confirmed_at'] !== null ? ' checked' : '' ?><?= $enrolledError !== null ? ' aria-invalid="true" aria-describedby="enrolled-error"' : '' ?>>
                <span>I am currently enrolled in the BSIT program of the PUP Open University System. <span class="field__required" aria-hidden="true">*</span></span>
            </label>
            <?php if ($enrolledError !== null) : ?>
                <p class="field__error" id="enrolled-error"><?= icon('alert') ?> <?= e($enrolledError) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?= $this->partial('membership/apply/_actions', compact('application', 'step')) ?>
</form>
