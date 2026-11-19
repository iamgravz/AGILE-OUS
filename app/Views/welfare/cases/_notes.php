<?php
/**
 * @var array<string, mixed> $case
 * @var bool $isClosed
 * @var list<array<string, mixed>> $notes
 */
$noteError = field_error('note');
?>
<div class="case-sections">
    <?php if (!$isClosed && can('welfare.handle')) : ?>
        <form class="plain-section" method="post" action="<?= e(route('welfare.notes', ['id' => (int) $case['case_id']])) ?>">
            <?= csrf_field() ?>
            <div class="card__header"><h2 class="card__title">Add a note</h2></div>
            <div class="card__body">
                <div class="field <?= $noteError !== null ? 'field--error' : '' ?>">
                    <label class="field__label" for="note">Note <span class="field__required" aria-hidden="true">*</span></label>
                    <textarea class="textarea applications-actions__remarks" id="note" name="note" maxlength="5000" required<?= $noteError !== null ? ' aria-invalid="true" aria-describedby="note-error"' : '' ?>></textarea>
                    <?php if ($noteError !== null) : ?>
                        <p class="field__error" id="note-error"><?= icon('alert') ?> <?= e($noteError) ?></p>
                    <?php endif; ?>
                </div>
                <div class="field">
                    <label class="check">
                        <input type="checkbox" name="visible_to_student" value="1" aria-describedby="visible-help">
                        <span>Visible to the submitter</span>
                    </label>
                    <p class="field__help" id="visible-help">They can read it when they follow the case, and they get an email saying there is an update. The email does not include the note.</p>
                </div>
                <button type="submit" class="btn btn--primary">Add note</button>
            </div>
        </form>
    <?php endif; ?>

    <section class="plain-section" aria-labelledby="case-notes">
        <div class="card__header"><h2 class="card__title" id="case-notes">Notes</h2></div>
        <div class="card__body">
            <?php if ($notes === []) : ?>
                <p class="text-muted">No notes have been added.</p>
            <?php else : ?>
                <ul class="case-list">
                    <?php foreach ($notes as $note) : ?>
                        <li class="case-entry">
                            <div class="document-card__header">
                                <strong><?= e($note['author_name']) ?></strong>
                                <span class="badge badge--<?= (int) $note['visible_to_student'] === 1 ? 'info' : 'neutral' ?>"><?= (int) $note['visible_to_student'] === 1 ? 'Visible to submitter' : 'Internal' ?></span>
                            </div>
                            <p class="timeline__meta"><?= e(datetime_ph($note['created_at'])) ?></p>
                            <p class="timeline__note"><?= nl2br(e($note['note'])) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
</div>
