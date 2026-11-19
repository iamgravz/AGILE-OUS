<?php
/**
 * The Interviews tab, for interview.manage.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $application
 * @var list<array<string, mixed>> $interviews
 * @var array<string, mixed>|null $pendingInterview
 * @var list<array<string, mixed>> $interviewers
 * @var bool $canSchedule
 */
$id = (int) $application['application_id'];
$pending = $pendingInterview;
$showForm = $canSchedule;
$action = $pending === null
    ? route('applications.interviews', ['id' => $id])
    : route('applications.interviews.reschedule', ['id' => $id, 'interviewId' => (int) $pending['interview_id']]);
$scheduledValue = (string) old('scheduled_at', $pending === null ? '' : date('Y-m-d\TH:i', strtotime($pending['scheduled_at'])));
$modeValue = (string) old('mode', $pending['mode'] ?? '');
$interviewerValue = (string) old('interviewer_id', $pending['interviewer_id'] ?? '');
$modeError = field_error('mode');
$interviewerError = field_error('interviewer_id');
?>
<div class="account-sections">
    <?php $checklistDone = $pending !== null && $pending['checklist_at'] !== null; ?>
    <?php $hasConflict = $checklistDone && (int) $pending['conflict_of_interest'] === 1; ?>
    <?php if ($pending !== null && can('interview.manage') && !$checklistDone) : ?>
        <form class="plain-section" method="post" action="<?= e(route('applications.interviews.checklist', ['id' => $id, 'interviewId' => (int) $pending['interview_id']])) ?>">
            <?= csrf_field() ?>
            <div class="card__header"><h2 class="card__title">Interview checklist</h2></div>
            <div class="card__body">
                <p class="field__help">Before scoring (BR-25). <?= e($pending['interviewer_name']) ?> is the interviewer.</p>
                <fieldset class="field-group">
                    <legend class="field-group__legend">Done with the applicant <span class="field__required" aria-hidden="true">*</span></legend>
                    <label class="check"><input type="checkbox" name="identity_verified" value="1" required> <span>Identity and AGILE record verified</span></label>
                    <label class="check"><input type="checkbox" name="role_confirmed" value="1" required> <span>Role or position applied for confirmed</span></label>
                    <label class="check"><input type="checkbox" name="privacy_reminder_given" value="1" required> <span>Privacy reminder given</span></label>
                </fieldset>
                <fieldset class="field-group">
                    <legend class="field-group__legend">Does the interviewer have a conflict of interest with this applicant? <span class="field__required" aria-hidden="true">*</span></legend>
                    <label class="check"><input type="radio" name="conflict_of_interest" value="no" required> <span>No</span></label>
                    <label class="check"><input type="radio" name="conflict_of_interest" value="yes"> <span>Yes: they cannot score this applicant, and the interview is rescheduled with someone else</span></label>
                </fieldset>
                <button type="submit" class="btn btn--primary">Confirm checklist</button>
            </div>
        </form>
    <?php elseif ($hasConflict) : ?>
        <div class="alert alert--warning" role="status">
            <?= icon('alert') ?>
            <p class="alert__text"><?= e($pending['interviewer_name']) ?> declared a conflict of interest. Reschedule this interview below with another interviewer.</p>
        </div>
    <?php endif; ?>
    <?php if ($pending !== null && can('interview.manage') && $checklistDone && !$hasConflict) : ?>
        <form class="plain-section" method="post" action="<?= e(route('applications.interviews.result', ['id' => $id, 'interviewId' => (int) $pending['interview_id']])) ?>">
            <?= csrf_field() ?>
            <div class="card__header"><h2 class="card__title">Record the result</h2></div>
            <div class="card__body">
                <div class="field">
                    <label class="field__label" for="result">Result <span class="field__required" aria-hidden="true">*</span></label>
                    <select class="select" id="result" name="result" required>
                        <option value="">Choose a result</option>
                        <?php foreach (\App\Services\InterviewService::RESULTS as $key => $label) : ?>
                            <option value="<?= e($key) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="notes">Notes</label>
                    <textarea class="textarea applications-actions__remarks" id="notes" name="notes" maxlength="2000"></textarea>
                </div>
                <button type="submit" class="btn btn--primary">Save result</button>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($showForm) : ?>
        <form class="plain-section" method="post" action="<?= e($action) ?>">
            <?= csrf_field() ?>
            <div class="card__header"><h2 class="card__title"><?= $pending === null ? 'Schedule an interview' : 'Reschedule this interview' ?></h2></div>
            <div class="card__body">
                <?= $this->partial('partials/form_field', ['name' => 'scheduled_at', 'label' => 'Date and time', 'type' => 'datetime-local', 'value' => $scheduledValue]) ?>
                <div class="field <?= $modeError !== null ? 'field--error' : '' ?>">
                    <label class="field__label" for="mode">Mode <span class="field__required" aria-hidden="true">*</span></label>
                    <select class="select" id="mode" name="mode" required<?= $modeError !== null ? ' aria-describedby="mode-error" aria-invalid="true"' : '' ?>>
                        <option value="">Choose a mode</option>
                        <?php foreach (\App\Services\InterviewService::MODES as $key => $label) : ?>
                            <option value="<?= e($key) ?>"<?= $modeValue === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($modeError !== null) : ?>
                        <p class="field__error" id="mode-error"><?= icon('alert') ?> <?= e($modeError) ?></p>
                    <?php endif; ?>
                </div>
                <?= $this->partial('partials/form_field', ['name' => 'location', 'label' => 'Link or venue', 'value' => (string) old('location', $pending['location'] ?? ''), 'autocomplete' => 'off', 'help' => 'A meeting link for online interviews, or the venue for on-site ones.']) ?>
                <div class="field <?= $interviewerError !== null ? 'field--error' : '' ?>">
                    <label class="field__label" for="interviewer_id">Interviewer <span class="field__required" aria-hidden="true">*</span></label>
                    <select class="select" id="interviewer_id" name="interviewer_id" required<?= $interviewerError !== null ? ' aria-describedby="interviewer_id-error" aria-invalid="true"' : '' ?>>
                        <option value="">Choose an interviewer</option>
                        <?php foreach ($interviewers as $interviewer) : ?>
                            <option value="<?= e($interviewer['user_id']) ?>"<?= $interviewerValue === (string) $interviewer['user_id'] ? ' selected' : '' ?>><?= e($interviewer['first_name'] . ' ' . $interviewer['last_name'] . ' (' . $interviewer['role_name'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($interviewerError !== null) : ?>
                        <p class="field__error" id="interviewer_id-error"><?= icon('alert') ?> <?= e($interviewerError) ?></p>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn--primary"><?= $pending === null ? 'Schedule and send invite' : 'Reschedule and send new invite' ?></button>
            </div>
        </form>
    <?php endif; ?>

    <section class="plain-section" aria-labelledby="interview-list">
        <div class="card__header"><h2 class="card__title" id="interview-list">Interviews</h2></div>
        <div class="card__body">
            <?php if ($interviews === []) : ?>
                <p class="text-muted">No interviews have been scheduled.</p>
            <?php else : ?>
                <ul class="member-applications">
                    <?php foreach ($interviews as $interview) : ?>
                        <li class="evaluation-entry">
                            <div class="document-card__header">
                                <strong><?= e(datetime_ph($interview['scheduled_at'])) ?></strong>
                                <?= status_badge($interview['result']) ?>
                            </div>
                            <p class="timeline__meta"><?= e(\App\Services\InterviewService::MODES[$interview['mode']]) ?> with <?= e($interview['interviewer_name']) ?></p>
                            <p class="timeline__meta interview-location"><?= e($interview['location']) ?></p>
                            <?php if ($interview['checklist_at'] !== null) : ?>
                                <p class="timeline__meta">Checklist confirmed by <?= e((string) $interview['checklist_by_name']) ?>, <?= e(datetime_ph($interview['checklist_at'])) ?><?= (int) $interview['conflict_of_interest'] === 1 ? ' · conflict of interest declared' : '' ?></p>
                            <?php endif; ?>
                            <?php if ($interview['reminder_sent_at'] !== null) : ?>
                                <p class="timeline__meta">Reminder sent <?= e(datetime_ph($interview['reminder_sent_at'])) ?></p>
                            <?php endif; ?>
                            <?php if ($interview['notes'] !== null) : ?>
                                <p class="timeline__note"><?= nl2br(e($interview['notes'])) ?></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
</div>
