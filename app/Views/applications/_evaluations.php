<?php
/**
 * The Evaluations tab: the summary for everyone with evaluation.view, and the evaluator's own form.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $application
 * @var list<array<string, mixed>> $evaluations
 * @var float|null $averageScore
 * @var array<string, int> $recommendationCounts
 * @var array<string, mixed>|null $myEvaluation
 * @var bool $canEvaluate
 * @var array<string, mixed> $rubric
 */
$labels = \App\Services\EvaluationService::RECOMMENDATIONS;
$myScores = [];

if ($myEvaluation !== null) {
    foreach (json_decode((string) $myEvaluation['rubric_json'], true)['criteria'] ?? [] as $criterion) {
        $myScores[$criterion['key']] = (string) $criterion['score'];
    }
}

$oldScores = old('scores', $myScores);
$selectedRecommendation = (string) old('recommendation', $myEvaluation['recommendation'] ?? '');
$remarksError = field_error('evaluation_remarks');
$maxScore = (int) $rubric['scale']['max'];
?>
<div class="account-sections">
    <section class="plain-section" aria-labelledby="evaluation-summary">
        <div class="card__header"><h2 class="card__title" id="evaluation-summary">Summary</h2></div>
        <div class="card__body">
            <?php if ($evaluations === []) : ?>
                <p class="text-muted">No evaluations have been submitted.</p>
            <?php else : ?>
                <dl class="detail-list member-details">
                    <div><dt>Average score</dt><dd><?= e(number_format((float) $averageScore, 2)) ?> out of <?= e($maxScore) ?></dd></div>
                    <div><dt>Evaluators</dt><dd><?= e(count($evaluations)) ?></dd></div>
                    <?php foreach ($labels as $key => $label) : ?>
                        <div><dt><?= e($label) ?></dt><dd><?= e($recommendationCounts[$key]) ?></dd></div>
                    <?php endforeach; ?>
                </dl>
                <h3 class="member-section">Evaluators</h3>
                <ul class="member-applications">
                    <?php foreach ($evaluations as $evaluation) : ?>
                        <?php $criteria = json_decode((string) $evaluation['rubric_json'], true)['criteria'] ?? []; ?>
                        <li class="evaluation-entry">
                            <div class="document-card__header">
                                <strong><?= e($evaluation['evaluator_name']) ?></strong>
                                <?= status_badge($evaluation['recommendation']) ?>
                            </div>
                            <p class="timeline__meta">Score <?= e(number_format((float) $evaluation['score'], 2)) ?>, updated <?= e(datetime_ph($evaluation['updated_at'])) ?></p>
                            <p class="timeline__note"><?= nl2br(e($evaluation['remarks'])) ?></p>
                            <details class="evaluation-entry__scores">
                                <summary>Scores by criterion<span class="sr-only"> from <?= e($evaluation['evaluator_name']) ?></span></summary>
                                <ul>
                                    <?php foreach ($criteria as $criterion) : ?>
                                        <li><?= e($criterion['label']) ?> (weight <?= e($criterion['weight']) ?>): <?= e($criterion['score']) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </details>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($canEvaluate) : ?>
        <form class="plain-section" method="post" action="<?= e(route('applications.evaluate', ['id' => (int) $application['application_id']])) ?>">
            <?= csrf_field() ?>
            <div class="card__header"><h2 class="card__title"><?= $myEvaluation === null ? 'Your evaluation' : 'Update your evaluation' ?></h2></div>
            <div class="card__body">
                <p class="field__help">Score each criterion from <?= e($rubric['scale']['min']) ?> to <?= e($maxScore) ?>. The system calculates the weighted total. You can change this until a decision is made.</p>
                <div class="grid grid--2">
                    <?php foreach ($rubric['criteria'] as $criterion) : ?>
                        <?php $selected = (string) ($oldScores[$criterion['key']] ?? ''); ?>
                        <div class="field">
                            <label class="field__label" for="score-<?= e($criterion['key']) ?>"><?= e($criterion['label']) ?> (weight <?= e($criterion['weight']) ?>) <span class="field__required" aria-hidden="true">*</span></label>
                            <select class="select" id="score-<?= e($criterion['key']) ?>" name="scores[<?= e($criterion['key']) ?>]" required>
                                <option value="">Choose a score</option>
                                <?php for ($score = (int) $rubric['scale']['min']; $score <= $maxScore; $score++) : ?>
                                    <option value="<?= e($score) ?>"<?= $selected === (string) $score ? ' selected' : '' ?>><?= e($score) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="recommendation">Recommendation <span class="field__required" aria-hidden="true">*</span></label>
                    <select class="select" id="recommendation" name="recommendation" required>
                        <option value="">Choose a recommendation</option>
                        <?php foreach ($labels as $key => $label) : ?>
                            <option value="<?= e($key) ?>"<?= $selectedRecommendation === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field <?= $remarksError !== null ? 'field--error' : '' ?>">
                    <label class="field__label" for="evaluation_remarks">Remarks <span class="field__required" aria-hidden="true">*</span></label>
                    <textarea class="textarea applications-actions__remarks" id="evaluation_remarks" name="evaluation_remarks" maxlength="2000" required<?= $remarksError !== null ? ' aria-describedby="evaluation_remarks-error" aria-invalid="true"' : '' ?>><?= e((string) old('evaluation_remarks', $myEvaluation['remarks'] ?? '')) ?></textarea>
                    <?php if ($remarksError !== null) : ?>
                        <p class="field__error" id="evaluation_remarks-error"><?= icon('alert') ?> <?= e($remarksError) ?></p>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn--primary"><?= $myEvaluation === null ? 'Submit evaluation' : 'Update evaluation' ?></button>
            </div>
        </form>
    <?php endif; ?>
</div>
