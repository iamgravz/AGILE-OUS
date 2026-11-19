<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $application
 * @var string $activeTab
 * @var array<string, mixed> $applicant
 * @var list<array<string, mixed>> $academicQuestions
 * @var list<array<string, mixed>> $questions
 * @var array<int, string> $answers
 * @var list<array<string, mixed>> $documents
 * @var list<array<string, mixed>> $timeline
 * @var list<array<string, mixed>> $activity
 * @var list<string> $missingDocuments
 */
$id = (int) $application['application_id'];
$status = $application['status'];
$remarksError = field_error('remarks');
$isOwn = (int) $application['user_id'] === current_user_id();

$canMarkReview = !$isOwn && $status === 'submitted' && can('application.update_status');
$canReturn = !$isOwn && $status === 'under_review' && can('application.return');
$canDecide = !$isOwn && $status === 'under_review' && $mayDecide;
$hasActions = $canMarkReview || $canReturn || $canDecide;
$decisionWord = $decider['committee'] ? 'Accept' : 'Approve';

$tabs = ['overview' => 'Overview'];

if (can('document.verify') || $isOwnCommitteeHead) {
    $tabs['documents'] = 'Documents';
}

$tabs['evaluations'] = 'Evaluations';

if (can('interview.manage') || $isOwnCommitteeHead) {
    $tabs['interviews'] = 'Interviews';
}

if (can('application.update_status')) {
    $tabs['activity'] = 'Activity';
}

$activeTab = isset($tabs[$activeTab]) ? $activeTab : 'overview';
$actionLabels = [
    'application.viewed' => 'Opened the application',
    'application.submitted' => 'Submitted the application',
    'application.resubmitted' => 'Resubmitted the application',
    'application.draft_created' => 'Started the application',
    'application.withdrawn' => 'Withdrew the application',
    'application.under_review' => 'Marked it Under Review',
    'application.returned' => 'Returned it for revision',
    'application.approved' => 'Approved the application',
    'application.rejected' => 'Rejected the application',
    'application.ready_for_recruitment' => 'Marked it ready for the recruitment flow',
    'evaluation.submitted' => 'Submitted an evaluation',
    'evaluation.updated' => 'Updated an evaluation',
    'interview.scheduled' => 'Scheduled an interview',
    'interview.rescheduled' => 'Rescheduled an interview',
    'interview.result_recorded' => 'Recorded an interview result',
    'interview.reminder_sent' => 'Sent an interview reminder',
    'application.document_uploaded' => 'Uploaded a document',
    'application.document_replaced' => 'Replaced a document',
    'application.document_removed' => 'Removed a document',
    'document.verified' => 'Verified a document',
    'document.needs_correction' => 'Marked a document Needs Correction',
    'document.rejected' => 'Rejected a document',
    'file.accessed' => 'Opened a document',
];
?>
<?php $this->start('title') ?>Application <?= e($application['tracking_code']) ?><?php $this->end() ?>
<?php $this->start('page_title') ?>Application <?= e($application['tracking_code']) ?><?php $this->end() ?>
<?php $this->start('page_actions') ?>
<?= status_badge($status) ?>
<?php if ($canPrepareAppointment && !$isOwn) : ?>
    <a class="btn btn--primary btn--sm" href="<?= e(route('appointments.prepare')) ?>?application=<?= e($id) ?>">Prepare appointment</a>
<?php elseif ($appointment !== null) : ?>
    <a class="btn btn--secondary btn--sm" href="<?= e(route('appointments.show', ['id' => (int) $appointment['appointment_id']])) ?>">View appointment</a>
<?php endif; ?>
<?php $this->end() ?>

<?php if (in_array($status, ['submitted', 'under_review', 'for_revision'], true)) : ?>
    <p>Decision by: <strong><?= e($decider['label']) ?></strong></p>
<?php endif; ?>
<div class="detail-layout">
<div class="detail-layout__side detail-stack">
<?php if ($hasActions) : ?>
    <section class="card applications-actions" aria-labelledby="actions-title">
        <div class="card__body">
            <h2 id="actions-title">Review actions</h2>
            <form method="post" action="<?= e(route($canMarkReview ? 'applications.under_review' : 'applications.return', ['id' => $id])) ?>">
                <?= csrf_field() ?>
                <div class="field <?= $remarksError !== null ? 'field--error' : '' ?>">
                    <label class="field__label" for="remarks">Remarks <span class="field__required" aria-hidden="true">*</span></label>
                    <textarea class="textarea applications-actions__remarks" id="remarks" name="remarks" maxlength="2000" required aria-describedby="remarks-help<?= $remarksError !== null ? ' remarks-error' : '' ?>"></textarea>
                    <p class="field__help" id="remarks-help">Required for every action. Applicants see these remarks when an application is returned, approved or rejected.</p>
                    <?php if ($remarksError !== null) : ?>
                        <p class="field__error" id="remarks-error"><?= icon('alert') ?> <?= e($remarksError) ?></p>
                    <?php endif; ?>
                </div>

                <?php if ($canDecide && $missingDocuments !== []) : ?>
                    <p class="applications-actions__blocked" id="approve-blocked">
                        <?= icon('info') ?>
                        <span><?= e($decisionWord) ?> is unavailable until every required document is verified. Still needed: <?= e(implode(', ', $missingDocuments)) ?>.</span>
                    </p>
                <?php endif; ?>

                <div class="cluster">
                    <?php if ($canMarkReview) : ?>
                        <button type="submit" class="btn btn--primary">Mark as under review</button>
                    <?php endif; ?>
                    <?php if ($canDecide) : ?>
                        <button
                            type="submit"
                            class="btn btn--primary"
                            formaction="<?= e(route('applications.approve', ['id' => $id])) ?>"
                            data-confirm="The applicant becomes an AGILE member and is notified. This cannot be undone."
                            data-confirm-title="<?= e($decisionWord) ?> this application?"
                            data-confirm-action="<?= e($decisionWord) ?>"
                            data-confirm-tone="primary"
                            <?= $missingDocuments !== [] ? 'disabled aria-describedby="approve-blocked"' : '' ?>
                        ><?= e($decisionWord) ?></button>
                    <?php endif; ?>
                    <?php if ($canReturn) : ?>
                        <button
                            type="submit"
                            class="btn btn--secondary"
                            data-confirm="The applicant is notified with your remarks and can edit and resubmit."
                            data-confirm-title="Return for revision?"
                            data-confirm-action="Return"
                            data-confirm-tone="primary"
                        >Return for revision</button>
                    <?php endif; ?>
                    <?php if ($canDecide) : ?>
                        <button
                            type="submit"
                            class="btn btn--danger"
                            formaction="<?= e(route('applications.reject', ['id' => $id])) ?>"
                            data-confirm="The applicant is notified with your remarks. This cannot be undone."
                            data-confirm-title="Reject this application?"
                            data-confirm-action="Reject"
                        >Reject</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </section>
<?php endif; ?>

<section class="card" aria-labelledby="timeline-title">
                <div class="card__body">
                    <h2 id="timeline-title">Status history</h2>
                    <ol class="timeline">
                        <?php foreach (array_reverse($timeline) as $index => $entry) : ?>
                            <li class="timeline__item <?= $index === 0 ? 'timeline__item--current' : '' ?>">
                                <?= status_badge($entry['new_status']) ?>
                                <p class="timeline__meta"><?= e(datetime_ph($entry['changed_at'])) ?><?= $entry['actor_name'] !== null ? ' by ' . e($entry['actor_name']) : '' ?></p>
                                <?php if ($entry['remarks'] !== null) : ?>
                                    <p class="timeline__note"><?= nl2br(e($entry['remarks'])) ?></p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </section>
</div>
<div class="detail-layout__primary">
<div data-tabs>
    <div class="tabs__list" role="tablist" aria-label="Application sections">
        <?php foreach ($tabs as $key => $label) : ?>
            <button type="button" class="tabs__tab" role="tab" id="tab-<?= e($key) ?>" aria-selected="<?= $key === $activeTab ? 'true' : 'false' ?>" aria-controls="panel-<?= e($key) ?>"<?= $key === $activeTab ? '' : ' tabindex="-1"' ?>><?= e($label) ?></button>
        <?php endforeach; ?>
    </div>

    <div class="tabs__panel" role="tabpanel" id="panel-overview" aria-labelledby="tab-overview"<?= $activeTab === 'overview' ? '' : ' hidden' ?>>
        <div>
            <div class="plain-section">
                <div class="card__body">
                    <?= $this->partial('membership/_summary', [
                        'application' => $application,
                        'user' => $applicant,
                        'academicQuestions' => $academicQuestions,
                        'questions' => $questions,
                        'answers' => $answers,
                        'documents' => $documents,
                        'withEditLinks' => false,
                        'hideDocuments' => !can('document.verify') && !$isOwnCommitteeHead,
                    ]) ?>
                </div>
            </div>
            
        </div>
    </div>

    <?php if (isset($tabs['documents'])) : ?>
        <div class="tabs__panel" role="tabpanel" id="panel-documents" aria-labelledby="tab-documents"<?= $activeTab === 'documents' ? '' : ' hidden' ?>>
            <?php if ($documents === []) : ?>
                <div class="empty-state"><p class="empty-state__text">No documents were uploaded.</p></div>
            <?php endif; ?>
            <?php foreach ($documents as $document) : ?>
                <?php
                $documentId = (int) $document['document_id'];
                $fileUrl = route('files.show', ['type' => 'application_document', 'id' => $documentId]);
                $isImage = str_starts_with($document['mime_type'], 'image/');
                $canVerify = can('document.verify') && !$isOwn && $document['verification_status'] === 'pending' && in_array($status, ['submitted', 'under_review'], true);
                ?>
                <article class="card document-card">
                    <div class="card__body">
                        <div class="document-card__header">
                            <div>
                                <h3 class="document-card__title"><?= e(\App\Services\MembershipService::documentLabel($document['doc_type'])) ?></h3>
                                <p class="text-muted document-card__name"><?= e($document['original_name']) ?></p>
                            </div>
                            <?= status_badge($document['verification_status']) ?>
                        </div>
                        <?php if ($document['verification_remarks'] !== null) : ?>
                            <p><strong>Remarks:</strong> <?= e($document['verification_remarks']) ?></p>
                        <?php endif; ?>
                        <details class="document-card__preview">
                            <summary>Preview</summary>
                            <?php if ($isImage) : ?>
                                <img src="<?= e($fileUrl) ?>?inline=1" alt="Preview of <?= e($document['original_name']) ?>" loading="lazy">
                            <?php else : ?>
                                <iframe src="<?= e($fileUrl) ?>?inline=1" title="Preview of <?= e($document['original_name']) ?>" loading="lazy"></iframe>
                            <?php endif; ?>
                        </details>
                        <p><a href="<?= e($fileUrl) ?>">Download<span class="sr-only"> <?= e($document['original_name']) ?></span></a></p>
                        <?php if ($canVerify) : ?>
                            <form class="document-card__form" method="post" action="<?= e(route('applications.verify_document', ['id' => $id, 'documentId' => $documentId])) ?>">
                                <?= csrf_field() ?>
                                <div class="field">
                                    <label class="field__label" for="doc-remarks-<?= e($documentId) ?>">Remarks</label>
                                    <textarea class="textarea applications-actions__remarks" id="doc-remarks-<?= e($documentId) ?>" name="remarks" maxlength="2000" aria-describedby="doc-remarks-help-<?= e($documentId) ?>"></textarea>
                                    <p class="field__help" id="doc-remarks-help-<?= e($documentId) ?>">Required for Needs correction and Reject document. The applicant sees these.</p>
                                </div>
                                <div class="cluster">
                                    <button type="submit" class="btn btn--primary btn--sm" name="decision" value="verified">Mark as verified</button>
                                    <button type="submit" class="btn btn--secondary btn--sm" name="decision" value="needs_correction">Needs correction</button>
                                    <button type="submit" class="btn btn--danger btn--sm" name="decision" value="rejected" data-confirm="A rejected document cannot be reviewed again. The applicant would need to upload a new one." data-confirm-title="Reject this document?" data-confirm-action="Reject">Reject document</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="tabs__panel" role="tabpanel" id="panel-evaluations" aria-labelledby="tab-evaluations"<?= $activeTab === 'evaluations' ? '' : ' hidden' ?>>
        <?= $this->partial('applications/_evaluations', [
            'application' => $application,
            'evaluations' => $evaluations,
            'averageScore' => $averageScore,
            'recommendationCounts' => $recommendationCounts,
            'myEvaluation' => $myEvaluation,
            'canEvaluate' => $canEvaluate,
            'rubric' => $rubric,
        ]) ?>
    </div>
    <?php if (isset($tabs['interviews'])) : ?>
        <div class="tabs__panel" role="tabpanel" id="panel-interviews" aria-labelledby="tab-interviews"<?= $activeTab === 'interviews' ? '' : ' hidden' ?>>
            <?= $this->partial('applications/_interviews', [
                'application' => $application,
                'interviews' => $interviews,
                'pendingInterview' => $pendingInterview,
                'interviewers' => $interviewers,
                'canSchedule' => $canSchedule,
            ]) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($tabs['activity'])) : ?>
        <div class="tabs__panel" role="tabpanel" id="panel-activity" aria-labelledby="tab-activity"<?= $activeTab === 'activity' ? '' : ' hidden' ?>>
            <div class="table-wrap">
                <table class="table table--stack">
                    <caption class="sr-only">Activity on this application</caption>
                    <thead>
                        <tr><th scope="col">When</th><th scope="col">Who</th><th scope="col">What</th><th scope="col">Details</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activity as $row) : ?>
                            <?php
                            $details = json_decode((string) $row['details_json'], true) ?: [];
                            $change = isset($details['from'], $details['to']) ? ucwords(str_replace('_', ' ', $details['from'])) . ' to ' . ucwords(str_replace('_', ' ', $details['to'])) : '';

                            if (isset($details['doc_type'])) {
                                $change = trim(\App\Services\MembershipService::documentLabel($details['doc_type']) . ($change === '' ? '' : ': ' . $change));
                            }
                            ?>
                            <tr>
                                <td data-label="When"><?= e(datetime_ph($row['created_at'])) ?></td>
                                <td data-label="Who"><?= e($row['actor_name'] ?? 'System') ?></td>
                                <td data-label="What"><?= e($actionLabels[$row['action']] ?? $row['action']) ?></td>
                                <td data-label="Details"><?= e($change) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
</div>
</div>
