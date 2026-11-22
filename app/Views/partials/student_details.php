<?php
/**
 * Year level and section side by side, then the contact number: all three required (StudentDetails).
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $values year_level, section, contact_no (optional; falls back to the flashed old input)
 * @var array<string, string> $errors (optional) for a page shown again from the same request
 * @var string $contactHelp (optional)
 * @var bool $withContact (optional, default true)
 */
$values ??= [];
$errors ??= [];
$withContact ??= true;
$value = static fn (string $key): string => (string) old($key, (string) ($values[$key] ?? ''));
$yearError = $errors['year_level'] ?? field_error('year_level');
$yearLevel = $value('year_level');
?>
<div class="student-details">
    <div class="field <?= $yearError !== null ? 'field--error' : '' ?>">
        <label class="field__label" for="year_level">Year level <span class="field__required" aria-hidden="true">*</span></label>
        <select class="select" id="year_level" name="year_level" required data-year-select<?= $yearError !== null ? ' aria-invalid="true" aria-describedby="year-level-error"' : '' ?>>
            <option value="">Choose one</option>
            <?php foreach (\App\Services\StudentDetails::YEAR_LEVELS as $level) : ?>
                <option value="<?= e($level) ?>"<?= $yearLevel === $level ? ' selected' : '' ?>><?= e($level) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($yearError !== null) : ?>
            <p class="field__error" id="year-level-error"><?= icon('alert') ?> <?= e($yearError) ?></p>
        <?php endif; ?>
    </div>
    <?= $this->partial('partials/form_field', ['name' => 'section', 'label' => 'Section', 'value' => $value('section'), 'autocomplete' => 'off', 'help' => 'For example, BSITOUMN 3-2.', 'error' => $errors['section'] ?? null]) ?>
</div>
<?php if ($withContact) : ?>
    <?= $this->partial('partials/form_field', ['name' => 'contact_no', 'label' => 'Contact number', 'type' => 'tel', 'value' => $value('contact_no'), 'autocomplete' => 'tel', 'help' => $contactHelp ?? 'For example, 09171234567.', 'error' => $errors['contact_no'] ?? null]) ?>
<?php endif; ?>
