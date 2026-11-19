<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed>|null $draft
 * @var array<string, string> $targets
 * @var int|null $recipientCount
 */
$id = $draft === null ? null : (int) $draft['draft_id'];
$isSent = ($draft['status'] ?? 'draft') !== 'draft';
$heading = $draft === null ? 'Write an email' : ($isSent ? 'Sent email' : 'Edit draft');
$selected = (string) old('target', $draft['target_group'] ?? '');
$bodyLabel = 'Message';
$bodyValue = (string) old('body', $draft['body_html'] ?? '');
$bodyHelp = 'The email opens with "Dear" and the person\'s first name and the committee greeting. The signature, footer and unsubscribe note are added for you.';
?>
<?php $this->start('title') ?><?= e($heading) ?><?php $this->end() ?>
<?php $this->start('form_side') ?><?= $this->partial('partials/side_panel', ['blocks' => [['title' => 'Before you send', 'items' => ['Save the draft first. The number of recipients comes from the saved draft.', 'Each person gets their own email, with a greeting and signature added.', 'Sending cannot be undone.']]]]) ?><?php $this->end() ?>
<?php $this->start('page_title') ?><?= e($heading) ?><?php $this->end() ?>
<?php if (!$isSent) : ?>
    <?php $this->start('head') ?>
    <link rel="stylesheet" href="<?= e(asset('vendor/quill/quill.snow.css')) ?>">
    <script src="<?= e(asset('vendor/quill/quill.js')) ?>" defer></script>
    <script src="<?= e(asset('js/email-editor.js')) ?>" defer></script>
    <?php $this->end() ?>
<?php endif; ?>

<?php if ($isSent) : ?>
    <dl class="detail-list admin-form">
        <div><dt>Subject</dt><dd><?= e($draft['subject']) ?></dd></div>
        <div><dt>To</dt><dd><?= e($targets[$draft['target_group']] ?? $draft['target_group']) ?></dd></div>
        <div><dt>Queued</dt><dd><?= e(datetime_ph($draft['sent_at'])) ?></dd></div>
    </dl>
    <div class="article__body admin-form"><?= (new \App\Services\HtmlSanitizer())->clean($draft['body_html']) ?></div>
<?php else : ?>
    <form class="admin-form cms-form" method="post" action="<?= e($id === null ? route('emails') : route('emails.show', ['id' => $id])) ?>" data-email-form>
        <?= csrf_field() ?>
        <?= $this->partial('partials/form_field', ['name' => 'subject', 'label' => 'Subject', 'value' => (string) old('subject', $draft['subject'] ?? '')]) ?>
        <div class="field">
            <label class="field__label" for="target">Send to</label>
            <select class="select" id="target" name="target" required>
                <option value="">Choose who to send this to</option>
                <?php foreach ($targets as $value => $label) : ?>
                    <option value="<?= e($value) ?>"<?= $selected === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field__label" for="body"><?= e($bodyLabel) ?></label>
            <div class="cluster admin-editor__tools" data-email-tools hidden>
                <button type="button" class="btn btn--ghost btn--sm" data-email-toggle>Edit HTML</button>
            </div>
            <div data-email-editor hidden></div>
            <textarea class="textarea" id="body" name="body" rows="14" data-email-body><?= e($bodyValue) ?></textarea>
            <?php if ($bodyHelp !== '') : ?><p class="field__help"><?= e($bodyHelp) ?></p><?php endif; ?>
            <p class="field__error" data-email-error hidden role="alert"></p>
        </div>
        <button type="submit" class="btn <?= $id === null ? 'btn--primary' : 'btn--secondary' ?>"><?= $id === null ? 'Save draft' : 'Save changes' ?></button>
    </form>
    <?php if ($id !== null) : ?>
        <form class="admin-form admin-send" method="post" action="<?= e(route('emails.send', ['id' => $id])) ?>" data-confirm="One email is queued for each of the <?= e($recipientCount) ?> people. This cannot be undone. Save any changes first." data-confirm-title="Send this email?" data-confirm-action="Send email">
            <?= csrf_field() ?>
            <p><strong><?= e($recipientCount) ?></strong> <?= $recipientCount === 1 ? 'person' : 'people' ?> will get this, based on the saved draft.</p>
            <button type="submit" class="btn btn--primary"<?= $recipientCount === 0 ? ' disabled' : '' ?>>Send to <?= e($recipientCount) ?> <?= $recipientCount === 1 ? 'person' : 'people' ?></button>
        </form>
    <?php endif; ?>
<?php endif; ?>
