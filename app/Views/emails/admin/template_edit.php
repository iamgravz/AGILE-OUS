<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $template
 * @var list<string> $placeholders
 */
$id = (int) $template['template_id'];
$bodyLabel = 'Body';
$bodyValue = (string) old('body', $template['body_html']);
$bodyHelp = 'The rich text editor simplifies the details block and the button. To keep them as they are, choose Edit HTML before changing the text.';
?>
<?php $this->start('title') ?><?= e($template['code']) ?><?php $this->end() ?>
<?php $this->start('page_eyebrow') ?><?= e($template['code']) ?><?php $this->end() ?>
<?php $this->start('page_title') ?>Edit this email<?php $this->end() ?>
<?php $this->start('head') ?>
<link rel="stylesheet" href="<?= e(asset('vendor/quill/quill.snow.css')) ?>">
<script src="<?= e(asset('vendor/quill/quill.js')) ?>" defer></script>
<script src="<?= e(asset('js/email-editor.js')) ?>" defer></script>
<?php $this->end() ?>

<div class="detail-layout">
    <form class="cms-form" method="post" action="<?= e(route('email_templates.edit', ['id' => $id])) ?>" data-email-form data-no-guard>
        <?= csrf_field() ?>
        <?= $this->partial('partials/form_field', ['name' => 'subject', 'label' => 'Subject', 'value' => (string) old('subject', $template['subject'])]) ?>
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
        <div class="cluster">
            <button type="submit" class="btn btn--primary">Save template</button>
            <button type="submit" class="btn btn--secondary" formaction="<?= e(route('email_templates.preview', ['id' => $id])) ?>" formtarget="email-preview">Preview below</button>
        </div>
    </form>

    <aside class="card detail-layout__side" aria-labelledby="placeholders">
        <div class="card__header"><h2 class="card__title" id="placeholders">Placeholders</h2></div>
        <div class="card__body">
            <p class="text-muted">Only these can be used here, because the system fills in nothing else for this email.</p>
            <ul class="admin-placeholders">
                <?php foreach ($placeholders as $placeholder) : ?><li><code>{<?= e($placeholder) ?>}</code></li><?php endforeach; ?>
            </ul>
            <form method="post" action="<?= e(route('email_templates.test', ['id' => $id])) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn--secondary btn--sm">Send test to me</button>
            </form>
            <p class="field__help">Uses the saved wording, with sample values.</p>
        </div>
    </aside>
</div>

<h2 class="admin-preview__heading">Preview</h2>
<p class="text-muted">Choose Preview below to see it here with sample values, inside the real header and footer. Nothing is saved or sent.</p>
<iframe class="admin-preview" name="email-preview" title="Email preview" src="about:blank" tabindex="-1" data-email-preview></iframe>
