<?php
/**
 * Documents beside the verification form.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $screening
 * @var list<array<string, mixed>> $documents
 * @var array<string, mixed>|null $review
 * @var bool $aiEnabled
 * @var array<string, mixed>|null $suggestion
 */
use App\Services\EligibilityService;
use App\Services\MembershipService;

$id = (int) $screening['screening_id'];
$status = $screening['result'];
$failedError = field_error('has_failed_subject');
$enrolledError = field_error('is_officially_enrolled');
$remarksError = field_error('remarks');
$questions = [
    'has_failed_subject' => ['Has a failed subject?', $failedError],
    'is_officially_enrolled' => ['Officially enrolled for the whole semester?', $enrolledError],
];
$answer = static fn (mixed $value): string => $value === null ? 'Not answered' : ((int) $value === 1 ? 'Yes' : 'No');
?>
<?php $this->start('title') ?><?= e($screening['officer_name']) ?><?php $this->end() ?>
<?php $this->start('page_title') ?><?= e($screening['officer_name']) ?><?php $this->end() ?>
<?php $this->start('page_actions') ?><?= status_badge($status) ?><?php $this->end() ?>
<?php if ($suggestion !== null && $status === 'submitted') : ?>
    <?php $this->start('head') ?>
    <script src="<?= e(asset('js/eligibility-review.js')) ?>" defer></script>
    <?php $this->end() ?>
<?php endif; ?>

<div class="review-grid">
    <section class="plain-section" aria-labelledby="review-documents">
        <div class="card__header"><h2 class="card__title" id="review-documents">Documents</h2></div>
        <div class="card__body">
            <?php if ($documents === []) : ?>
                <p class="text-muted">No documents were submitted.</p>
            <?php endif; ?>
            <?php foreach ($documents as $index => $document) : ?>
                <?php
                $fileUrl = route('files.show', ['type' => 'eligibility_document', 'id' => (int) $document['document_id']]);
                $isImage = str_starts_with($document['mime_type'], 'image/');
                ?>
                <details class="document-card__preview"<?= $index === 0 ? ' open' : '' ?>>
                    <summary><?= e($document['original_name']) ?></summary>
                    <?php if ($isImage) : ?>
                        <img src="<?= e($fileUrl) ?>?inline=1" alt="Preview of <?= e($document['original_name']) ?>" loading="lazy">
                    <?php else : ?>
                        <iframe src="<?= e($fileUrl) ?>?inline=1" title="Preview of <?= e($document['original_name']) ?>" loading="lazy"></iframe>
                    <?php endif; ?>
                </details>
                <p><a href="<?= e($fileUrl) ?>">Download<span class="sr-only"> <?= e($document['original_name']) ?></span></a></p>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="case-sections">
        <section class="card" aria-labelledby="review-officer">
            <div class="card__header"><h2 class="card__title" id="review-officer">Officer</h2></div>
            <div class="card__body">
                <dl class="detail-list member-details">
                    <div><dt>Term</dt><dd><?= e(EligibilityService::termLabel($screening)) ?></dd></div>
                    <div><dt>Member number</dt><dd><code><?= e($screening['member_no']) ?></code></dd></div>
                    <div><dt>Student number</dt><dd><?= e($screening['student_no'] ?? 'Not set') ?></dd></div>
                    <div><dt>Position</dt><dd><?= e(MembershipService::roleLabel($screening)) ?></dd></div>
                    <?php if ($screening['verified_at'] !== null) : ?>
                        <div><dt>Has a failed subject?</dt><dd><?= e($answer($screening['has_failed_subject'])) ?></dd></div>
                        <div><dt>Officially enrolled for the whole semester?</dt><dd><?= e($answer($screening['is_officially_enrolled'])) ?></dd></div>
                    <?php endif; ?>
                </dl>
            </div>
        </section>

        <?php if ($status === 'submitted') : ?>
            <?php if (can('eligibility.manage')) : ?>
                <?= $this->partial('eligibility/_ai_panel', ['id' => $id, 'aiEnabled' => $aiEnabled, 'suggestion' => $suggestion]) ?>
            <?php endif; ?>

            <form class="plain-section" method="post" action="<?= e(route('eligibility.review.verify', ['id' => $id])) ?>">
                <?= csrf_field() ?>
                <div class="card__header"><h2 class="card__title">Verification</h2></div>
                <div class="card__body">
                    <input type="hidden" name="used_suggestion" value="0" data-ai-used>
                    <?php foreach ($questions as $name => [$question, $error]) : ?>
                        <fieldset class="field-group <?= $error !== null ? 'field--error' : '' ?>"<?= $error !== null ? ' aria-describedby="' . e($name) . '-error"' : '' ?>>
                            <legend class="field-group__legend"><?= e($question) ?> <span class="field__required" aria-hidden="true">*</span></legend>
                            <div class="cluster">
                                <label class="check"><input type="radio" name="<?= e($name) ?>" value="yes" required> <span>Yes</span></label>
                                <label class="check"><input type="radio" name="<?= e($name) ?>" value="no" required> <span>No</span></label>
                            </div>
                            <?php if ($suggestion !== null) : ?>
                                <?php $suggested = $suggestion['values'][$name]; ?>
                                <p class="ai-suggestion">
                                    <?php if ($suggested === null) : ?>
                                        <span class="text-muted">AI could not tell from the documents.</span>
                                    <?php else : ?>
                                        <span>AI suggests: <strong><?= e(ucfirst($suggested)) ?></strong></span>
                                        <button type="button" class="btn btn--ghost btn--sm" data-ai-use="<?= e($name) ?>" data-ai-value="<?= e($suggested) ?>">Use this value<span class="sr-only"> for <?= e($question) ?></span></button>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($error !== null) : ?>
                                <p class="field__error" id="<?= e($name) ?>-error"><?= icon('alert') ?> <?= e($error) ?></p>
                            <?php endif; ?>
                        </fieldset>
                    <?php endforeach; ?>
                    <p class="field__help">No failed subject and officially enrolled makes the officer Eligible. Anything else flags them.</p>
                    <button type="submit" class="btn btn--primary">Save verification</button>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($status === 'flagged') : ?>
            <form class="plain-section" method="post" action="<?= e(route('eligibility.review.decide', ['id' => $id])) ?>">
                <?= csrf_field() ?>
                <div class="card__header"><h2 class="card__title">Decision</h2></div>
                <div class="card__body">
                    <div class="field <?= $remarksError !== null ? 'field--error' : '' ?>">
                        <label class="field__label" for="remarks">Remarks <span class="field__required" aria-hidden="true">*</span></label>
                        <textarea class="textarea applications-actions__remarks" id="remarks" name="remarks" maxlength="2000" required aria-describedby="remarks-help<?= $remarksError !== null ? ' remarks-error' : '' ?>"<?= $remarksError !== null ? ' aria-invalid="true"' : '' ?>></textarea>
                        <p class="field__help" id="remarks-help">The officer sees these remarks.</p>
                        <?php if ($remarksError !== null) : ?>
                            <p class="field__error" id="remarks-error"><?= icon('alert') ?> <?= e($remarksError) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="cluster">
                        <button type="submit" class="btn btn--secondary" name="decision" value="clear" data-confirm="The officer keeps their role and is notified." data-confirm-title="Clear this officer?" data-confirm-action="Clear this officer" data-confirm-tone="primary">Clear this officer</button>
                        <button type="submit" class="btn btn--danger" name="decision" value="confirm_ineligible" data-confirm="Their officer role ends now, and they become a General Member. They stay an Active member, get a new ID and certificate, and are notified." data-confirm-title="Confirm ineligible?" data-confirm-action="Confirm ineligible">Confirm ineligible</button>
                    </div>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($review !== null) : ?>
            <section class="plain-section" aria-labelledby="review-decision">
                <div class="card__header"><h2 class="card__title" id="review-decision">Decision</h2></div>
                <div class="card__body">
                    <dl class="detail-list member-details">
                        <div><dt>Decision</dt><dd><?= $review['decision'] === 'clear' ? 'Cleared' : 'Confirmed ineligible' ?></dd></div>
                        <div><dt>Decided</dt><dd><?= e(datetime_ph($review['decided_at'])) ?> by <?= e($review['decided_by_name']) ?></dd></div>
                        <div><dt>Remarks</dt><dd><?= nl2br(e($review['remarks'])) ?></dd></div>
                    </dl>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>
