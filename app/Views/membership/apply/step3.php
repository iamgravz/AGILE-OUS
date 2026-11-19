<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $application
 * @var list<array<string, mixed>> $vacancies open vacancies
 * @var int|null $preselected from ?vacancy=
 * @var bool $mayChooseGeneral true for someone without an account, who can only apply for general membership
 * @var int $step
 * @var int $reached
 */
$error = field_error('membership_type');
$isRevision = $application['status'] !== 'draft';
$hasChosen = (int) $application['wizard_step'] > 3;
$selected = $preselected !== null ? (string) $preselected : ($application['vacancy_id'] !== null ? (string) $application['vacancy_id'] : ($hasChosen ? 'general' : ''));
?>
<?php $this->start('title') ?>Apply: Membership type<?php $this->end() ?>
<?php $this->start('page_title') ?>Membership application<?php $this->end() ?>
<?php $this->start('form_side') ?><?= $this->partial('membership/apply/_side', compact('application', 'step', 'reached')) ?><?php $this->end() ?>
<?= $this->partial('membership/apply/_stepper', compact('application', 'step', 'reached')) ?>
<form class="card wizard-form" method="post" action="<?= e(route('apply.step', ['id' => $application['application_id'], 'step' => 3])) ?>">
    <?= csrf_field() ?>
    <div class="card__body">
        <h2>Membership type</h2>
        <?php if ($isRevision) : ?>
            <p>You applied for: <strong><?= e($application['vacancy_title'] ?? 'General Membership') ?></strong>. This cannot be changed during a revision.</p>
        <?php else : ?>
            <?php if (current_user_id() === null) : ?>
                <input type="hidden" name="membership_type" value="general">
                <p>You are applying for <strong>General Membership</strong>. Positions in the committees and the Execomm are open to AGILE members, so you can apply for one after you are approved.</p>
            <?php elseif ($vacancies === [] && !$mayChooseGeneral) : ?>
                <p>No open positions this semester. Watch the <a href="<?= e(route('announcements')) ?>">Announcements</a> page for the next recruitment.</p>
            <?php else : ?>
            <?php if ($error !== null) : ?>
                <p class="field__error" id="membership-type-error"><?= icon('alert') ?> <?= e($error) ?></p>
            <?php endif; ?>
            <fieldset class="field-group"<?= $error !== null ? ' aria-describedby="membership-type-error"' : '' ?>>
                <legend class="field-group__legend">What are you applying for? <span class="field__required" aria-hidden="true">*</span></legend>
                <?php if ($mayChooseGeneral) : ?>
                    <label class="check wizard-choice">
                        <input type="radio" name="membership_type" value="general"<?= $selected === 'general' ? ' checked' : '' ?>>
                        <span><strong>General Membership</strong><br><span class="text-muted">Join AGILE as an active member.</span></span>
                    </label>
                <?php endif; ?>
                <?php foreach ($vacancies as $vacancy) : ?>
                    <label class="check wizard-choice">
                        <input type="radio" name="membership_type" value="<?= e($vacancy['vacancy_id']) ?>"<?= $selected === (string) $vacancy['vacancy_id'] ? ' checked' : '' ?>>
                        <span>
                            <strong><?= e($vacancy['title']) ?></strong><br>
                            <span class="text-muted"><?= e($vacancy['committee_name'] ?? 'Executive position') ?>. Apply by <?= e(date_ph($vacancy['closes_at'])) ?>.</span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?= $this->partial('membership/apply/_actions', compact('application', 'step')) ?>
</form>
