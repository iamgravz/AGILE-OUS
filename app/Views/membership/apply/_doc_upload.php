<?php
/**
 * An upload field whose input is always named "document", with ids unique per document type.
 *
 * @var string $type
 * @var string $label
 * @var int $maxMb
 */
$inputId = "document-{$type}";
$error = field_error($type);
?>
<div class="field <?= $error !== null ? 'field--error' : '' ?>">
    <label class="field__label" for="<?= e($inputId) ?>"><?= e($label) ?></label>
    <div class="dropzone">
        <input
            class="dropzone__input"
            id="<?= e($inputId) ?>"
            name="document"
            type="file"
            accept=".pdf,.jpg,.jpeg,.png"
            data-file-preview="<?= e($inputId) ?>-preview"
            data-file-error="<?= e($inputId) ?>-error"
            data-allowed-types="pdf,jpg,jpeg,png"
            data-max-mb="<?= e($maxMb) ?>"
            required
            aria-describedby="<?= e($inputId) ?>-error"
            <?= $error !== null ? 'aria-invalid="true"' : '' ?>
        >
        <div class="dropzone__body">
            <?= icon('upload') ?>
            <span><strong>Choose a file</strong> or drop it here</span>
        </div>
    </div>
    <p class="field__error" id="<?= e($inputId) ?>-error"<?= $error === null ? ' hidden' : '' ?>><?= icon('alert') ?> <span data-file-error-text><?= e($error ?? '') ?></span></p>
    <ul class="file-preview" id="<?= e($inputId) ?>-preview"></ul>
</div>
