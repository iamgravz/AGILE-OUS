<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $case
 * @var bool $isClosed
 * @var list<array<string, mixed>> $followups
 * @var list<array<string, mixed>> $handlers
 */
$id = (int) $case['case_id'];
$remarksError = field_error('followup_remarks');
?>
<div class="case-sections">
    <?php if (!$isClosed && can('welfare.handle')) : ?>
        <form class="plain-section" method="post" action="<?= e(route('welfare.followups', ['id' => $id])) ?>">
            <?= csrf_field() ?>
            <div class="card__header"><h2 class="card__title">Add a follow-up</h2></div>
            <div class="card__body">
                <div class="grid grid--2">
                    <?= $this->partial('partials/form_field', ['name' => 'due_date', 'label' => 'Due date', 'type' => 'date']) ?>
                    <div class="field">
                        <label class="field__label" for="followup_handler">Handler <span class="field__required" aria-hidden="true">*</span></label>
                        <select class="select" id="followup_handler" name="handler_id" required>
                            <option value="">Choose a handler</option>
                            <?php foreach ($handlers as $user) : ?>
                                <option value="<?= e($user['user_id']) ?>"><?= e($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['role_name'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn--primary">Add follow-up</button>
            </div>
        </form>
    <?php endif; ?>

    <section class="plain-section" aria-labelledby="case-followups">
        <div class="card__header"><h2 class="card__title" id="case-followups">Follow-ups</h2></div>
        <div class="card__body">
            <?php if ($followups === []) : ?>
                <p class="text-muted">No follow-ups have been added.</p>
            <?php else : ?>
                <?php if ($remarksError !== null) : ?>
                    <p class="field__error"><?= icon('alert') ?> <?= e($remarksError) ?></p>
                <?php endif; ?>
                <ul class="case-list">
                    <?php foreach ($followups as $followup) : ?>
                        <?php $followupId = (int) $followup['followup_id']; ?>
                        <li class="case-entry">
                            <div class="document-card__header">
                                <strong>Due <?= e(date_ph($followup['due_at'])) ?></strong>
                                <?php if ($followup['done_at'] !== null) : ?>
                                    <?= status_badge('completed') ?>
                                <?php elseif (strtotime($followup['due_at']) < time()) : ?>
                                    <?= status_badge('overdue') ?>
                                <?php else : ?>
                                    <?= status_badge('pending') ?>
                                <?php endif; ?>
                            </div>
                            <p class="timeline__meta">Handler: <?= e($followup['handler_name']) ?></p>
                            <?php if ($followup['done_at'] !== null) : ?>
                                <p class="timeline__meta">Completed <?= e(datetime_ph($followup['done_at'])) ?></p>
                                <p class="timeline__note"><?= nl2br(e((string) $followup['remarks'])) ?></p>
                            <?php elseif (!$isClosed && can('welfare.handle')) : ?>
                                <form method="post" action="<?= e(route('welfare.followups.complete', ['id' => $id, 'followupId' => $followupId])) ?>">
                                    <?= csrf_field() ?>
                                    <div class="field">
                                        <label class="field__label" for="followup-remarks-<?= e($followupId) ?>">Remarks <span class="field__required" aria-hidden="true">*</span></label>
                                        <textarea class="textarea applications-actions__remarks" id="followup-remarks-<?= e($followupId) ?>" name="followup_remarks" maxlength="2000" required></textarea>
                                    </div>
                                    <button type="submit" class="btn btn--secondary btn--sm">Mark as completed</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
</div>
