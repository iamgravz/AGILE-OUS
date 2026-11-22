<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed>|null $application null until the first save
 * @var array<string, mixed> $user
 * @var int $step
 * @var int $reached
 */
$action = $application === null ? route('apply.new') : route('apply.step', ['id' => $application['application_id'], 'step' => 1]);
?>
<?php $this->start('title') ?>Apply: Personal info<?php $this->end() ?>
<?php $this->start('page_title') ?>Membership application<?php $this->end() ?>
<?php $this->start('form_side') ?><?= $this->partial('membership/apply/_side', compact('application', 'step', 'reached')) ?><?php $this->end() ?>
<?= $this->partial('membership/apply/_stepper', compact('application', 'step', 'reached')) ?>
<form class="card wizard-form" method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>
    <div class="card__body">
        <h2>Personal info</h2>
        <?php if (isset($user['applicant_id'])) : ?>
            <dl class="detail-list wizard-details">
                <div><dt>Email</dt><dd><?= e($user['email']) ?></dd></div>
            </dl>
            <?= $this->partial('partials/form_field', ['name' => 'first_name', 'label' => 'First name', 'value' => old('first_name', $user['first_name']), 'autocomplete' => 'given-name']) ?>
            <?= $this->partial('partials/form_field', ['name' => 'last_name', 'label' => 'Last name', 'value' => old('last_name', $user['last_name']), 'autocomplete' => 'family-name']) ?>
            <?= $this->partial('partials/form_field', ['name' => 'student_no', 'label' => 'PUP student number', 'value' => old('student_no', $user['student_no']), 'autocomplete' => 'off', 'help' => 'For example, 2024-xxxxx-MN-0.']) ?>
        <?php else : ?>
            <p class="text-muted">These details come from your account. To correct your name or email, contact the MSW Committee.</p>
            <dl class="detail-list wizard-details">
                <div><dt>Name</dt><dd><?= e($user['first_name'] . ' ' . $user['last_name']) ?></dd></div>
                <div><dt>Email</dt><dd><?= e($user['email']) ?></dd></div>
            </dl>
        <?php endif; ?>
        <?= $this->partial('partials/student_details', ['values' => ['year_level' => $user['year_level'] ?? '', 'section' => $user['section'] ?? '', 'contact_no' => $user['contact_no'] ?? '']]) ?>
    </div>
    <?= $this->partial('membership/apply/_actions', compact('application', 'step')) ?>
</form>
