<?php
/**
 * In the Interviews tab: AGILE's interview question bank for this applicant, and the MSW interviewer's assessment
 * (AGILE's Applicant Evaluation sheet). The Committee Head reads the saved assessment on the Applicant profile tab.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $application
 * @var array<string, mixed>|null $assessment
 * @var list<array<string, mixed>> $questionBank
 * @var bool $canAssess
 */
use App\Services\AssessmentService;

$id = (int) $application['application_id'];
$saved = [];

foreach ($assessment['ratings'] ?? [] as $section => $rows) {
    foreach ($rows as $index => $row) {
        $saved[$section][$index] = $row;
    }
}

$oldRatings = old('rating', []);
$oldRemarks = old('remark', []);
$recommendation = (string) old('recommendation', $assessment['recommendation'] ?? '');
?>
<div class="account-sections">
    <details class="plain-section question-bank"<?= $canAssess ? '' : ' open' ?>>
        <summary class="card__header"><h2 class="card__title">Interview questions (<?= count($questionBank) ?>)</h2></summary>
        <div class="card__body">
            <p class="field__help">From AGILE's question bank: the core questions, then those for <?= e($application['vacancy_title'] ?? 'General Membership') ?>.</p>
            <?php if ($questionBank === []) : ?>
                <p class="text-muted">No questions in the bank for this application.</p>
            <?php else : ?>
                <ol class="question-bank__list">
                    <?php foreach ($questionBank as $question) : ?>
                        <li>
                            <span class="question-bank__code"><?= e($question['code']) ?></span>
                            <span class="question-bank__text"><?= e($question['question']) ?></span>
                            <span class="chip"><?= e($question['competency']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>
    </details>

    <?php if ($canAssess) : ?>
        <form class="plain-section" method="post" action="<?= e(route('applications.assessment', ['id' => $id])) ?>">
            <?= csrf_field() ?>
            <div class="card__header"><h2 class="card__title"><?= $assessment === null ? 'Assess the applicant' : 'Update the assessment' ?></h2></div>
            <div class="card__body">
                <p class="field__help">Rate each criterion from 1 (poor) to 5 (excellent). The Committee Head reads this before deciding.</p>
                <?php foreach (AssessmentService::SECTIONS as $section => [$label, $criteria]) : ?>
                    <fieldset class="field-group assessment-section">
                        <legend class="field-group__legend"><?= e($label) ?> <span class="text-muted">(out of <?= AssessmentService::maxScore($section) ?>)</span></legend>
                        <?php foreach ($criteria as $index => $criterion) : ?>
                            <?php
                            $fieldId = "rating-{$section}-{$index}";
                            $rating = (string) ($oldRatings[$section][$index] ?? ($saved[$section][$index]['rating'] ?? ''));
                            $remark = (string) ($oldRemarks[$section][$index] ?? ($saved[$section][$index]['remark'] ?? ''));
                            ?>
                            <div class="assessment-row">
                                <label class="field__label" for="<?= e($fieldId) ?>"><?= e($criterion) ?> <span class="field__required" aria-hidden="true">*</span></label>
                                <select class="select" id="<?= e($fieldId) ?>" name="rating[<?= e($section) ?>][<?= $index ?>]" required>
                                    <option value="">Rate</option>
                                    <?php foreach (AssessmentService::RATINGS as $value => $word) : ?>
                                        <option value="<?= $value ?>"<?= $rating === (string) $value ? ' selected' : '' ?>><?= $value ?> · <?= e($word) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input class="input" type="text" name="remark[<?= e($section) ?>][<?= $index ?>]" maxlength="500" value="<?= e($remark) ?>" placeholder="Remark (optional)" aria-label="Remark for <?= e($criterion) ?>">
                            </div>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endforeach; ?>

                <div class="field">
                    <label class="field__label" for="recommendation">Recommendation <span class="field__required" aria-hidden="true">*</span></label>
                    <select class="select" id="recommendation" name="recommendation" required>
                        <option value="">Choose a recommendation</option>
                        <?php foreach (AssessmentService::RECOMMENDATIONS as $key => $label) : ?>
                            <option value="<?= e($key) ?>"<?= $recommendation === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php foreach (['strengths' => ['strengths', 'Strengths'], 'development' => ['development', 'Areas for development'], 'assessment_remarks' => ['remarks', 'Interviewer remarks']] as $name => [$column, $label]) : ?>
                    <div class="field">
                        <label class="field__label" for="<?= e($name) ?>"><?= e($label) ?></label>
                        <textarea class="textarea" id="<?= e($name) ?>" name="<?= e($name) ?>" maxlength="2000" rows="3"><?= e((string) old($name, $assessment[$column] ?? '')) ?></textarea>
                    </div>
                <?php endforeach; ?>
                <button type="submit" class="btn btn--primary"><?= $assessment === null ? 'Save assessment' : 'Update assessment' ?></button>
            </div>
        </form>
    <?php elseif ($assessment !== null) : ?>
        <section class="plain-section">
            <div class="card__header"><h2 class="card__title">Assessment</h2></div>
            <div class="card__body">
                <p><strong><?= (int) $assessment['total_score'] ?> / <?= AssessmentService::maxScore() ?></strong> · <?= e(AssessmentService::RECOMMENDATIONS[$assessment['recommendation']]) ?></p>
                <p class="timeline__meta">By <?= e($assessment['evaluator_name']) ?>, <?= e(datetime_ph($assessment['updated_at'])) ?>. The full assessment is on the Applicant profile tab.</p>
            </div>
        </section>
    <?php elseif (can('interview.manage')) : ?>
        <p class="text-muted">The assessment opens once the interview result (passed or failed) is recorded.</p>
    <?php endif; ?>
</div>
