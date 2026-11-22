<?php
/**
 * A labeled input with its help text and inline error.
 *
 * @var string $name
 * @var string $label
 * @var string $type (optional, default text)
 * @var string $value (optional)
 * @var string $autocomplete (optional)
 * @var string $help (optional)
 * @var bool $required (optional, default true)
 * @var string $class extra class for the wrapper (optional)
 * @var string|null $error (optional) for a page shown again from the same request; otherwise the flashed error
 */
$type ??= 'text';
$value ??= '';
$help ??= '';
$required ??= true;
$error = ($error ?? null) ?? field_error($name);
$describedBy = trim(($help !== '' ? "{$name}-help " : '') . ($error !== null ? "{$name}-error" : ''));
?>
<div class="field <?= $error !== null ? 'field--error' : '' ?> <?= e($class ?? '') ?>">
    <label class="field__label" for="<?= e($name) ?>">
        <?= e($label) ?>
        <?php if ($required) : ?>
            <span class="field__required" aria-hidden="true">*</span>
        <?php endif; ?>
    </label>
    <input
        class="input"
        id="<?= e($name) ?>"
        name="<?= e($name) ?>"
        type="<?= e($type) ?>"
        value="<?= e($value) ?>"
        <?= isset($autocomplete) ? 'autocomplete="' . e($autocomplete) . '"' : '' ?>
        <?= $required ? 'required' : '' ?>
        <?= $error !== null ? 'aria-invalid="true"' : '' ?>
        <?= $describedBy !== '' ? 'aria-describedby="' . e($describedBy) . '"' : '' ?>
    >
    <?php if ($help !== '') : ?>
        <p class="field__help" id="<?= e($name) ?>-help"><?= e($help) ?></p>
    <?php endif; ?>
    <?php if ($error !== null) : ?>
        <p class="field__error" id="<?= e($name) ?>-error"><?= icon('alert') ?> <?= e($error) ?></p>
    <?php endif; ?>
</div>
