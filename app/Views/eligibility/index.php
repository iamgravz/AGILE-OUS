<?php
/**
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $terms
 * @var array<string, mixed>|null $current
 * @var int $defaultGrace
 */
use App\Services\EligibilityService;

$semesterError = field_error('semester');
?>
<?php $this->start('title') ?>Eligibility screening<?php $this->end() ?>
<?php $this->start('page_title') ?>Eligibility screening<?php $this->end() ?>
<?php $this->start('page_actions') ?>
<a class="btn btn--secondary btn--sm" href="<?= e(route('eligibility.review')) ?>">Review screenings</a>
<?php if ($current !== null) : ?>
    <a class="btn btn--primary btn--sm" href="<?= e(route('eligibility.start')) ?>">Start a screening</a>
<?php endif; ?>
<?php $this->end() ?>

<div class="case-sections">
    <section class="plain-section" aria-labelledby="terms-title">
        <div class="card__header"><h2 class="card__title" id="terms-title">Academic terms</h2></div>
        <div class="card__body">
            <?php if ($terms === []) : ?>
                <p class="text-muted">No terms yet. Create one below.</p>
            <?php else : ?>
                <div class="table-wrap">
                    <table class="table table--stack">
                        <caption class="sr-only">Academic terms</caption>
                        <thead>
                            <tr><th scope="col">Term</th><th scope="col">Deadline</th><th scope="col" class="num">Grace days</th><th scope="col" class="num">Screenings</th><th scope="col">Current</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($terms as $term) : ?>
                                <tr>
                                    <td data-label="Term"><?= e(EligibilityService::termLabel($term)) ?></td>
                                    <td data-label="Deadline"><?= e(date_ph($term['screening_deadline'])) ?></td>
                                    <td class="num" data-label="Grace days"><?= e($term['grace_days']) ?></td>
                                    <td class="num" data-label="Screenings"><?= e($term['screening_count']) ?></td>
                                    <td data-label="Current">
                                        <?php if ((int) $term['is_current'] === 1) : ?>
                                            <span class="badge badge--success">Current</span>
                                        <?php else : ?>
                                            <form method="post" action="<?= e(route('eligibility.terms.current', ['id' => (int) $term['term_id']])) ?>" data-confirm="Reminders and the officer's page then follow this term, and anything still pending in the current term stops moving." data-confirm-title="Make this the current term?" data-confirm-action="Make current" data-confirm-tone="primary">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn--ghost btn--sm">Make current<span class="sr-only"> <?= e(EligibilityService::termLabel($term)) ?></span></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <form class="plain-section" method="post" action="<?= e(route('eligibility.terms')) ?>">
        <?= csrf_field() ?>
        <div class="card__header"><h2 class="card__title">New term</h2></div>
        <div class="card__body">
            <div class="grid grid--2">
                <?= $this->partial('partials/form_field', ['name' => 'academic_year', 'label' => 'Academic year', 'value' => (string) old('academic_year'), 'autocomplete' => 'off', 'help' => 'For example, 2026-2027.']) ?>
                <div class="field <?= $semesterError !== null ? 'field--error' : '' ?>">
                    <label class="field__label" for="semester">Semester <span class="field__required" aria-hidden="true">*</span></label>
                    <select class="select" id="semester" name="semester" required<?= $semesterError !== null ? ' aria-invalid="true" aria-describedby="semester-error"' : '' ?>>
                        <option value="">Choose a semester</option>
                        <?php foreach (EligibilityService::SEMESTERS as $key => $label) : ?>
                            <option value="<?= e($key) ?>"<?= old('semester') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($semesterError !== null) : ?>
                        <p class="field__error" id="semester-error"><?= icon('alert') ?> <?= e($semesterError) ?></p>
                    <?php endif; ?>
                </div>
                <?= $this->partial('partials/form_field', ['name' => 'screening_deadline', 'label' => 'Screening deadline', 'type' => 'date', 'value' => (string) old('screening_deadline')]) ?>
                <?= $this->partial('partials/form_field', ['name' => 'grace_days', 'label' => 'Grace days', 'type' => 'number', 'value' => (string) old('grace_days', (string) $defaultGrace), 'help' => 'Extra days after the deadline before pending officers are flagged.']) ?>
            </div>
            <div class="field">
                <label class="check">
                    <input type="checkbox" name="make_current" value="1"<?= $current === null ? ' checked' : '' ?>>
                    <span>Make this the current term</span>
                </label>
            </div>
            <button type="submit" class="btn btn--primary">Create term</button>
        </div>
    </form>
</div>
