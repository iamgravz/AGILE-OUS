<?php
/**
 * One application question, rendered by its input_type.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $question
 * @var array<int, string> $answers
 */
$id = (int) $question['question_id'];
$field = "q_{$id}";
$inputId = "question-{$id}";
$required = (int) $question['is_required'] === 1;
$value = (string) old($field, $answers[$id] ?? '');
$error = field_error($field);
$options = json_decode((string) ($question['options_json'] ?? ''), true) ?: [];
$described = $error !== null ? " aria-invalid=\"true\" aria-describedby=\"{$inputId}-error\"" : '';
?>
<?php if ($question['input_type'] === 'file') : ?>
    <?= $this->partial('partials/upload_field', [
        'name' => $field,
        'label' => $question['question_text'],
        'types' => ['pdf', 'jpg', 'jpeg', 'png'],
        'maxMb' => (new \App\Services\FileStorageService())->maxUploadMb(),
        'required' => $required && $value === '',
        'help' => $value !== '' ? "Current file: {$value}. Choose another to replace it." : 'PDF, JPG or PNG.',
    ]) ?>
<?php else : ?>
    <div class="field <?= $error !== null ? 'field--error' : '' ?>">
        <?php if ($question['input_type'] === 'checkbox') : ?>
            <label class="check">
                <input id="<?= e($inputId) ?>" type="checkbox" name="q[<?= e($id) ?>]" value="1"<?= $value !== '' ? ' checked' : '' ?><?= $described ?>>
                <span><?= e($question['question_text']) ?><?= $required ? ' <span class="field__required" aria-hidden="true">*</span>' : '' ?></span>
            </label>
        <?php else : ?>
            <label class="field__label" for="<?= e($inputId) ?>">
                <?= e($question['question_text']) ?>
                <?php if ($required) : ?>
                    <span class="field__required" aria-hidden="true">*</span>
                <?php endif; ?>
            </label>
            <?php if ($question['input_type'] === 'textarea') : ?>
                <textarea class="textarea" id="<?= e($inputId) ?>" name="q[<?= e($id) ?>]" maxlength="5000"<?= $described ?>><?= e($value) ?></textarea>
            <?php elseif ($question['input_type'] === 'select') : ?>
                <select class="select" id="<?= e($inputId) ?>" name="q[<?= e($id) ?>]"<?= $described ?>>
                    <option value="">Choose one</option>
                    <?php foreach ($options as $option) : ?>
                        <option value="<?= e($option) ?>"<?= $value === $option ? ' selected' : '' ?>><?= e($option) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else : ?>
                <input class="input" id="<?= e($inputId) ?>" type="text" name="q[<?= e($id) ?>]" value="<?= e($value) ?>" maxlength="5000"<?= $described ?>>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($error !== null) : ?>
            <p class="field__error" id="<?= e($inputId) ?>-error"><?= icon('alert') ?> <?= e($error) ?></p>
        <?php endif; ?>
    </div>
<?php endif; ?>
