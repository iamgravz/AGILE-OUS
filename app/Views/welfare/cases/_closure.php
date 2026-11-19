<?php
/**
 * Resolution, closure and attachments. The President reads these but has no controls here (TC-16).
 *
 * @var array<string, mixed> $case
 * @var bool $isClosed
 * @var array<string, mixed>|null $resolution
 * @var list<array<string, mixed>> $attachments
 */
use App\Services\WelfareService;

$id = (int) $case['case_id'];
$canManage = can('welfare.assign_close');
$actionsError = field_error('actions_taken');
$summaryError = field_error('summary');
$closingError = field_error('closing_remarks');
?>
<div class="case-sections">
    <section class="plain-section" aria-labelledby="case-resolution">
        <div class="card__header"><h2 class="card__title" id="case-resolution">Resolution</h2></div>
        <div class="card__body">
            <?php if ($resolution !== null) : ?>
                <dl class="detail-list member-details">
                    <div><dt>Outcome</dt><dd><?= e(WelfareService::OUTCOMES[$resolution['outcome']]) ?></dd></div>
                    <div><dt>Resolved</dt><dd><?= e(datetime_ph($resolution['resolved_at'])) ?> by <?= e($resolution['resolved_by_name']) ?></dd></div>
                    <div><dt>Actions taken</dt><dd><?= nl2br(e($resolution['actions_taken'])) ?></dd></div>
                    <div><dt>Summary</dt><dd><?= nl2br(e($resolution['summary'])) ?></dd></div>
                </dl>
            <?php elseif ($canManage && !$isClosed) : ?>
                <form method="post" action="<?= e(route('welfare.resolve', ['id' => $id])) ?>">
                    <?= csrf_field() ?>
                    <div class="field <?= $actionsError !== null ? 'field--error' : '' ?>">
                        <label class="field__label" for="actions_taken">Actions taken <span class="field__required" aria-hidden="true">*</span></label>
                        <textarea class="textarea applications-actions__remarks" id="actions_taken" name="actions_taken" maxlength="5000" required<?= $actionsError !== null ? ' aria-invalid="true" aria-describedby="actions_taken-error"' : '' ?>></textarea>
                        <?php if ($actionsError !== null) : ?>
                            <p class="field__error" id="actions_taken-error"><?= icon('alert') ?> <?= e($actionsError) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="field">
                        <label class="field__label" for="outcome">Outcome <span class="field__required" aria-hidden="true">*</span></label>
                        <select class="select" id="outcome" name="outcome" required>
                            <option value="">Choose an outcome</option>
                            <?php foreach (WelfareService::OUTCOMES as $key => $label) : ?>
                                <option value="<?= e($key) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field <?= $summaryError !== null ? 'field--error' : '' ?>">
                        <label class="field__label" for="summary">Summary <span class="field__required" aria-hidden="true">*</span></label>
                        <textarea class="textarea applications-actions__remarks" id="summary" name="summary" maxlength="5000" required<?= $summaryError !== null ? ' aria-invalid="true" aria-describedby="summary-error"' : '' ?>></textarea>
                        <?php if ($summaryError !== null) : ?>
                            <p class="field__error" id="summary-error"><?= icon('alert') ?> <?= e($summaryError) ?></p>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn--primary">Mark as resolved</button>
                </form>
            <?php else : ?>
                <p class="text-muted">This case has not been resolved.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="plain-section" aria-labelledby="case-closure">
        <div class="card__header"><h2 class="card__title" id="case-closure">Closure</h2></div>
        <div class="card__body">
            <?php if ($isClosed) : ?>
                <dl class="detail-list member-details">
                    <div><dt>Closed</dt><dd><?= e(datetime_ph($case['closed_at'])) ?></dd></div>
                    <div><dt>Closing remarks</dt><dd><?= nl2br(e((string) $case['closing_remarks'])) ?></dd></div>
                </dl>
                <p class="text-muted">A closed case cannot be reopened. The submitter can send a new concern that refers to this one.</p>
            <?php elseif ($case['status'] === 'resolved' && $canManage) : ?>
                <form method="post" action="<?= e(route('welfare.close', ['id' => $id])) ?>">
                    <?= csrf_field() ?>
                    <div class="field <?= $closingError !== null ? 'field--error' : '' ?>">
                        <label class="field__label" for="closing_remarks">Closing remarks <span class="field__required" aria-hidden="true">*</span></label>
                        <textarea class="textarea applications-actions__remarks" id="closing_remarks" name="closing_remarks" maxlength="2000" required<?= $closingError !== null ? ' aria-invalid="true" aria-describedby="closing_remarks-error"' : '' ?>></textarea>
                        <?php if ($closingError !== null) : ?>
                            <p class="field__error" id="closing_remarks-error"><?= icon('alert') ?> <?= e($closingError) ?></p>
                        <?php endif; ?>
                    </div>
                    <button
                        type="submit"
                        class="btn btn--danger"
                        data-confirm="The submitter is notified, attachments become read-only, and a closed case can never be reopened."
                        data-confirm-title="Close this case?"
                        data-confirm-action="Close case"
                    >Close case</button>
                </form>
            <?php else : ?>
                <p class="text-muted">A case can be closed by the MSW Committee Head after it is resolved.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="plain-section" aria-labelledby="case-attachments">
        <div class="card__header"><h2 class="card__title" id="case-attachments">Attachments</h2></div>
        <div class="card__body">
            <?php if ($attachments === []) : ?>
                <p class="text-muted">No files were attached.</p>
            <?php else : ?>
                <?php if ($isClosed) : ?>
                    <p class="text-muted">This case is closed, so its attachments are read-only.</p>
                <?php endif; ?>
                <ul class="case-list">
                    <?php foreach ($attachments as $attachment) : ?>
                        <?php $attachmentId = (int) $attachment['attachment_id']; ?>
                        <li class="case-entry">
                            <div class="document-card__header">
                                <?php if ($attachment['disposition'] === 'deleted') : ?>
                                    <strong><?= e($attachment['original_name']) ?></strong>
                                <?php else : ?>
                                    <a href="<?= e(route('files.show', ['type' => 'welfare_attachment', 'id' => $attachmentId])) ?>"><?= e($attachment['original_name']) ?></a>
                                <?php endif; ?>
                                <?php if ($attachment['disposition'] !== 'open') : ?>
                                    <?= status_badge($attachment['disposition']) ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($attachment['disposition'] !== 'open') : ?>
                                <p class="timeline__meta">Recorded <?= e(datetime_ph($attachment['disposition_at'])) ?> by <?= e($attachment['disposition_by_name']) ?></p>
                            <?php elseif ($isClosed && $canManage) : ?>
                                <form method="post" action="<?= e(route('welfare.disposition', ['id' => $id, 'attachmentId' => $attachmentId])) ?>">
                                    <?= csrf_field() ?>
                                    <div class="cluster">
                                        <button type="submit" class="btn btn--secondary btn--sm" name="disposition" value="kept">Mark as kept<span class="sr-only"> <?= e($attachment['original_name']) ?></span></button>
                                        <button type="submit" class="btn btn--secondary btn--sm" name="disposition" value="returned">Mark as returned<span class="sr-only"> <?= e($attachment['original_name']) ?></span></button>
                                        <button
                                            type="submit"
                                            class="btn btn--danger btn--sm"
                                            name="disposition"
                                            value="deleted"
                                            data-confirm="The file is permanently removed. Its record stays, with who deleted it and when."
                                            data-confirm-title="Delete this file?"
                                            data-confirm-action="Delete file"
                                        >Delete file<span class="sr-only"> <?= e($attachment['original_name']) ?></span></button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
</div>
