<?php
/**
 * Applicant profile: what the Committee Head reads before deciding, laid out like AGILE's Applicant Profile sheet.
 * The answers to AGILE's registration form, the interview, and the MSW interviewer's assessment. The decision
 * buttons stay in the side panel (BR-20).
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $application
 * @var array<string, mixed> $applicant
 * @var list<array<string, mixed>> $questions
 * @var array<int, string> $answers
 * @var list<array<string, mixed>> $interviews
 * @var array<string, mixed>|null $assessment
 */
use App\Services\AssessmentService;
use App\Services\InterviewService;

$done = array_values(array_filter($interviews, static fn (array $i): bool => in_array($i['result'], ['passed', 'failed'], true)));
$interview = $done[0] ?? null;
$year = (string) ($applicant['year_level'] ?? '');
$section = (string) ($applicant['section'] ?? '');
?>
<div class="account-sections applicant-profile">
    <section class="plain-section">
        <div class="card__body applicant-profile__head">
            <div>
                <h2 class="card__title"><?= e($applicant['first_name'] . ' ' . $applicant['last_name']) ?></h2>
                <p class="timeline__meta">
                    <?= e($application['vacancy_title'] ?? 'General Membership') ?>
                    <?= $year !== '' ? ' · ' . e($year) : '' ?><?= $section !== '' ? ' · ' . e($section) : '' ?>
                    · <code><?= e($application['tracking_code']) ?></code>
                </p>
            </div>
            <?php if ($assessment !== null) : ?>
                <div class="applicant-profile__score">
                    <strong><?= (int) $assessment['total_score'] ?><span>/<?= AssessmentService::maxScore() ?></span></strong>
                    <span class="badge applicant-profile__rec applicant-profile__rec--<?= e($assessment['recommendation']) ?>"><?= e(AssessmentService::RECOMMENDATIONS[$assessment['recommendation']]) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="plain-section" aria-labelledby="profile-answers">
        <div class="card__header"><h2 class="card__title" id="profile-answers">Answers to the registration form</h2></div>
        <div class="card__body">
            <?php if ($questions === []) : ?>
                <p class="text-muted">No questions for this application.</p>
            <?php else : ?>
                <dl class="detail-list detail-list--stacked">
                    <?php foreach ($questions as $question) : ?>
                        <?php $answer = $answers[(int) $question['question_id']] ?? ''; ?>
                        <div>
                            <dt><?= e($question['question_text']) ?></dt>
                            <dd class="review__answer"><?= $answer !== '' ? nl2br(e($answer)) : '<span class="text-muted">Not answered</span>' ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </div>
    </section>

    <section class="plain-section" aria-labelledby="profile-interview">
        <div class="card__header"><h2 class="card__title" id="profile-interview">Interview</h2></div>
        <div class="card__body">
            <?php if ($interview === null) : ?>
                <p class="text-muted">No interview has been completed yet.</p>
            <?php else : ?>
                <dl class="detail-list">
                    <div><dt>Date</dt><dd><?= e(datetime_ph($interview['scheduled_at'])) ?></dd></div>
                    <div><dt>Mode</dt><dd><?= e(InterviewService::MODES[$interview['mode']] ?? $interview['mode']) ?></dd></div>
                    <div><dt>Interviewer</dt><dd><?= e($interview['interviewer_name']) ?></dd></div>
                    <div><dt>Result</dt><dd><?= status_badge($interview['result']) ?></dd></div>
                    <?php if ($interview['notes'] !== null) : ?>
                        <div><dt>Notes</dt><dd><?= nl2br(e($interview['notes'])) ?></dd></div>
                    <?php endif; ?>
                </dl>
            <?php endif; ?>
        </div>
    </section>

    <section class="plain-section" aria-labelledby="profile-assessment">
        <div class="card__header"><h2 class="card__title" id="profile-assessment">Assessment</h2></div>
        <div class="card__body">
            <?php if ($assessment === null) : ?>
                <p class="text-muted">The MSW interviewer has not assessed this applicant yet. It is done on the Interviews tab after the interview.</p>
            <?php else : ?>
                <p class="timeline__meta">By <?= e($assessment['evaluator_name']) ?>, <?= e(datetime_ph($assessment['updated_at'])) ?>.</p>
                <?php foreach (AssessmentService::SECTIONS as $key => [$label]) : ?>
                    <?php $score = (int) $assessment[$key === 'role' ? 'role_score' : "{$key}_score"]; ?>
                    <div class="table-wrap">
                        <table class="table table--stack applicant-profile__table">
                            <caption><?= e($label) ?>: <?= $score ?> / <?= AssessmentService::maxScore($key) ?></caption>
                            <thead><tr><th scope="col">Criterion</th><th scope="col">Rating</th><th scope="col">Remark</th></tr></thead>
                            <tbody>
                                <?php foreach ($assessment['ratings'][$key] ?? [] as $row) : ?>
                                    <tr>
                                        <td data-label="Criterion"><?= e($row['criterion']) ?></td>
                                        <td data-label="Rating"><strong><?= (int) $row['rating'] ?></strong> · <?= e(AssessmentService::RATINGS[(int) $row['rating']] ?? '') ?></td>
                                        <td data-label="Remark"><?= $row['remark'] !== '' ? e($row['remark']) : '<span class="text-muted">None</span>' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
                <dl class="detail-list detail-list--stacked">
                    <?php foreach (['strengths' => 'Strengths', 'development' => 'Areas for development', 'remarks' => 'Interviewer remarks'] as $field => $label) : ?>
                        <div><dt><?= e($label) ?></dt><dd><?= $assessment[$field] !== null ? nl2br(e($assessment[$field])) : '<span class="text-muted">None</span>' ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </div>
    </section>
</div>
