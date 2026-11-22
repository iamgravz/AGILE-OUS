<?php
/**
 * Step 3: what they apply for. General Membership, joining a committee as a Committee Member, or (for members with an
 * account) an open position. Each choice says which documents it needs (membership/_requirements).
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $application
 * @var list<array<string, mixed>> $vacancies open vacancies
 * @var list<array{committee_id: int, name: string, position_id: int}> $committees committees that take Committee Members
 * @var int|null $preselected from ?vacancy=
 * @var bool $mayChooseGeneral false for someone who is already a member
 * @var bool $mayChooseCommittee false for someone who already holds a position
 * @var int $step
 * @var int $reached
 */
$error = field_error('membership_type');
$isRevision = $application['status'] !== 'draft';
$hasChosen = (int) $application['wizard_step'] > 3;
// What they chose on the first page ("general" or "committee:<id>"), until this step is saved.
$preselectedChoice ??= null;
$firstPageCommittee = is_string($preselectedChoice) && str_starts_with($preselectedChoice, 'committee:') ? substr($preselectedChoice, 10) : '';
$chosenCommittee = (string) old('committee_id', $application['committee_id'] !== null ? (string) $application['committee_id'] : $firstPageCommittee);
$selected = (string) old('membership_type', match (true) {
    $preselected !== null => (string) $preselected,
    $application['vacancy_id'] !== null => (string) $application['vacancy_id'],
    $application['committee_id'] !== null => 'committee',
    $hasChosen => 'general',
    $firstPageCommittee !== '' => 'committee',
    $preselectedChoice === 'general' => 'general',
    default => '',
});
// Open positions are for members with an account; someone without one applies for membership or a committee first.
$showVacancies = current_user_id() !== null ? $vacancies : [];
$hasChoices = $mayChooseGeneral || ($mayChooseCommittee && $committees !== []) || $showVacancies !== [];
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
        <?php elseif (!$hasChoices) : ?>
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
                        <span>
                            <strong>General Membership</strong><br>
                            <span class="text-muted">Join AGILE as an active member. Needs your current COR.</span>
                        </span>
                    </label>
                <?php endif; ?>

                <?php if ($mayChooseCommittee && $committees !== []) : ?>
                    <div class="wizard-choice wizard-choice--with-field">
                        <label class="check">
                            <input type="radio" name="membership_type" value="committee"<?= $selected === 'committee' ? ' checked' : '' ?>>
                            <span>
                                <strong>Committee Member</strong><br>
                                <span class="text-muted">Join a committee and help run its work. Needs your current COR and your previous semester copy of grades (1st years in their 1st semester: COR only). The committee's head decides.</span>
                            </span>
                        </label>
                        <div class="field wizard-choice__field">
                            <label class="field__label" for="committee_id">Committee</label>
                            <select class="select" id="committee_id" name="committee_id">
                                <option value="">Choose a committee</option>
                                <?php foreach ($committees as $committee) : ?>
                                    <option value="<?= e($committee['committee_id']) ?>"<?= $chosenCommittee === (string) $committee['committee_id'] ? ' selected' : '' ?>><?= e($committee['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="field__help">Only needed if you choose Committee Member.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php foreach ($showVacancies as $vacancy) : ?>
                    <label class="check wizard-choice">
                        <input type="radio" name="membership_type" value="<?= e($vacancy['vacancy_id']) ?>"<?= $selected === (string) $vacancy['vacancy_id'] ? ' checked' : '' ?>>
                        <span>
                            <strong><?= e($vacancy['title']) ?></strong><br>
                            <span class="text-muted"><?= e($vacancy['committee_name'] ?? 'Executive position') ?>. Apply by <?= e(date_ph($vacancy['closes_at'])) ?>. Needs your current COR and previous semester copy of grades.</span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <?php if (current_user_id() === null) : ?>
                <p class="field__help">Officer positions (Executive Committee, Committee Head and others) open to AGILE members. You can apply for one after you are approved.</p>
            <?php endif; ?>

            <details class="wizard-requirements">
                <summary>Which documents do I need?</summary>
                <?= $this->partial('membership/_requirements') ?>
            </details>
        <?php endif; ?>
    </div>
    <?= $this->partial('membership/apply/_actions', compact('application', 'step')) ?>
</form>
