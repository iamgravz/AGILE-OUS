<?php
/**
 * Drag-and-drop file field with a preview. The browser check mirrors the server's rules for faster
 * feedback, but FileStorageService is the authority.
 *
 * @var string $name
 * @var string $label
 * @var list<string> $types allowed extensions
 * @var int $maxMb
 * @var string $help (optional)
 * @var bool $required (optional, default true)
 * @var bool $clientCheck (optional, default true)
 * @var bool $multiple (optional, default false) The field then posts as name[].
 * @var string|null $errorMessage (optional) For a page that shows its errors without flashing them.
 */
$help ??= '';
$required ??= true;
$clientCheck ??= true;
$multiple ??= false;
$error = $errorMessage ?? field_error($name);
$accept = implode(',', array_map(static fn (string $type): string => '.' . $type, $types));
?>
<div class="field <?= $error !== null ? 'field--error' : '' ?>">
    <label class="field__label" for="<?= e($name) ?>">
        <?= e($label) ?>
        <?php if ($required) : ?>
            <span class="field__required" aria-hidden="true">*</span>
        <?php endif; ?>
    </label>
    <div class="dropzone">
        <input
            class="dropzone__input"
            id="<?= e($name) ?>"
            name="<?= e($name) ?><?= $multiple ? '[]' : '' ?>"
            type="file"
            <?= $multiple ? 'multiple' : '' ?>
            <?= $clientCheck ? 'accept="' . e($accept) . '"' : '' ?>
            data-file-preview="<?= e($name) ?>-preview"
            data-file-error="<?= e($name) ?>-error"
            <?= $clientCheck ? 'data-allowed-types="' . e(implode(',', $types)) . '" data-max-mb="' . e($maxMb) . '"' : '' ?>
            <?= $required ? 'required' : '' ?>
            <?= $error !== null ? 'aria-invalid="true"' : '' ?>
            aria-describedby="<?= e($name) ?>-error<?= $help !== '' ? ' ' . e($name) . '-help' : '' ?>"
        >
        <div class="dropzone__body">
            <?= icon('upload') ?>
            <span><strong>Choose <?= $multiple ? 'files' : 'a file' ?></strong> or drop <?= $multiple ? 'them' : 'it' ?> here</span>
        </div>
    </div>
    <?php if ($help !== '') : ?>
        <p class="field__help" id="<?= e($name) ?>-help"><?= e($help) ?></p>
    <?php endif; ?>
    <p class="field__error" id="<?= e($name) ?>-error"<?= $error === null ? ' hidden' : '' ?>><?= icon('alert') ?> <span data-file-error-text><?= e($error ?? '') ?></span></p>
    <ul class="file-preview" id="<?= e($name) ?>-preview"></ul>
</div>
