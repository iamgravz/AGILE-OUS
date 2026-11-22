<?php
/**
 * Step 3: what they apply for. General Membership, a position the MSW Committee Head opened for application (Open for
 * application), or (for members with an account) an open vacancy. What they chose on the first page is selected.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $application
 * @var list<array<string, mixed>> $vacancies open vacancies
 * @var list<array{position_id: int, label: string, committee_name: string, remaining: ?int}> $positions open for application
 * @var int|null $preselected from ?vacancy=
 * @var bool $mayChooseGeneral false for someone who is already a member
 * @var bool $mayChoosePosition false for someone who already holds a position
 * @var int $step
 * @var int $reached
 */
$error = field_error('membership_type');
$isRevision = $application['status'] !== 'draft';
$hasChosen = (int) $application['wizard_step'] > 3;
// What they chose on the first page ("general" or "position:<id>"), until this step is saved.
$preselectedChoice ??= null;
$firstPagePosition = is_string($preselectedChoice) && str_starts_with($preselectedChoice, 'position:') ? substr($preselectedChoice, 9) : '';
$chosenPosition = (string) old('position_id', $application['position_id'] !== null ? (string) $application['position_id'] : $firstPagePosition);
$selected = (string) old('membership_type', match (true) {
    $preselected !== null => (string) $preselected,
    $application['vacancy_id'] !== null => (string) $application['vacancy_id'],
    $application['position_id'] !== null => 'position',
    $hasChosen => 'general',
    $firstPagePosition !== '' => 'position',
    $preselectedChoice === 'general' => 'general',
    default => '',
});
// Vacancies are for members with an account; someone without one chooses General Membership or an open position.
$showVacancies = current_user_id() !== null ? $vacancies : [];
$hasChoices = $mayChooseGeneral || ($mayChoosePosition && $positions !== []) || $showVacancies !== [];
$byCommittee = [];

foreach ($positions as $position) {
    $byCommittee[$position['committee_name']][] = $position;
}
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

                <?php if ($mayChoosePosition && $positions !== []) : ?>
                    <div class="wizard-choice wizard-choice--with-field">
                        <label class="check">
                            <input type="radio" name="membership_type" value="position"<?= $selected === 'position' ? ' checked' : '' ?>>
                            <span>
                                <strong>A position</strong><br>
                                <span class="text-muted">One of the positions open for application. Needs your current COR and your previous semester copy of grades (1st years in their 1st semester: COR only).</span>
                            </span>
                        </label>
                        <div class="field wizard-choice__field">
                            <label class="field__label" for="position_id">Position</label>
                            <select class="select" id="position_id" name="position_id">
                                <option value="">Choose a position</option>
                                <?php foreach ($byCommittee as $committee => $rows) : ?>
                                    <optgroup label="<?= e($committee) ?>">
                                        <?php foreach ($rows as $position) : ?>
                                            <option value="<?= e($position['position_id']) ?>"<?= $chosenPosition === (string) $position['position_id'] ? ' selected' : '' ?>><?= e($position['label']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
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
                <p class="field__help">The Executive Board and Year-Level Representatives are chosen by election, not by application (Constitution, Article VI). To run, <a href="<?= e(route('candidacy')) ?>">file a candidacy</a>.</p>
            <?php endif; ?>

            <details class="wizard-requirements">
                <summary>Which documents do I need?</summary>
                <?= $this->partial('membership/_requirements') ?>
            </details>
        <?php endif; ?>
    </div>
    <?= $this->partial('membership/apply/_actions', compact('application', 'step')) ?>
</form>
