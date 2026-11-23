<?php
/**
 * @var \App\Core\View $this
 * @var array<string, array<string, array<string, mixed>>> $groups
 * @var array<string, string> $values
 */
use App\Services\LandingService;
use App\Services\SettingsService;
?>
<?php $this->start('title') ?>Settings<?php $this->end() ?>
<?php $this->start('page_title') ?>Settings<?php $this->end() ?>

<form class="admin-form" method="post" action="<?= e(route('admin.settings')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php foreach ($groups as $heading => $fields) : ?>
        <h2><?= e($heading) ?></h2>
        <?php foreach ($fields as $key => $field) : ?>
            <?php
            $name = SettingsService::fieldName($key);
            $value = (string) old($name, $values[$key]);
            $error = field_error($name);
            $help = $field['help'] ?? '';
            ?>
            <?php if ($field['type'] === 'bool') : ?>
                <div class="field">
                    <input type="hidden" name="<?= e($name) ?>" value="0">
                    <label class="check"><input type="checkbox" name="<?= e($name) ?>" value="1"<?= $value === '1' ? ' checked' : '' ?>> <span><?= e($field['label']) ?></span></label>
                    <p class="field__help"><?= e($help) ?></p>
                </div>
            <?php elseif ($field['type'] === 'sections') : ?>
                <?php $shown = json_decode($values[$key], true) ?: []; ?>
                <fieldset class="field">
                    <legend class="field__label"><?= e($field['label']) ?></legend>
                    <?php foreach (LandingService::SECTIONS as $section => $sectionLabel) : ?>
                        <label class="check"><input type="checkbox" name="<?= e($name) ?>[]" value="<?= e($section) ?>"<?= in_array($section, $shown, true) ? ' checked' : '' ?>> <span><?= e($sectionLabel) ?></span></label>
                    <?php endforeach; ?>
                    <p class="field__help"><?= e($help) ?></p>
                </fieldset>
            <?php elseif ($field['type'] === 'image') : ?>
                <div class="field <?= $error !== null ? 'field--error' : '' ?>">
                    <label class="field__label" for="<?= e($name) ?>"><?= e($field['label']) ?></label>
                    <?php if ($values[$key] !== '') : ?>
                        <img class="cms-form__cover" src="<?= e(media_url($values[$key])) ?>" alt="Current hero image" width="240" height="135">
                        <label class="check"><input type="checkbox" name="<?= e($name) ?>_remove" value="1"> <span>Remove this image and show the AGILE logo</span></label>
                    <?php endif; ?>
                    <input class="input" id="<?= e($name) ?>" name="<?= e($name) ?>" type="file" accept=".jpg,.jpeg,.png">
                    <p class="field__help"><?= e($help) ?></p>
                    <?php if ($error !== null) : ?><p class="field__error"><?= icon('alert') ?> <?= e($error) ?></p><?php endif; ?>
                </div>
            <?php elseif (in_array($field['type'], ['textarea', 'join_steps'], true)) : ?>
                <div class="field <?= $error !== null ? 'field--error' : '' ?>">
                    <label class="field__label" for="<?= e($name) ?>"><?= e($field['label']) ?></label>
                    <textarea class="textarea" id="<?= e($name) ?>" name="<?= e($name) ?>" rows="<?= $field['type'] === 'join_steps' ? '7' : '3' ?>"><?= e($value) ?></textarea>
                    <?php if ($help !== '') : ?><p class="field__help"><?= e($help) ?></p><?php endif; ?>
                    <?php if ($error !== null) : ?><p class="field__error"><?= icon('alert') ?> <?= e($error) ?></p><?php endif; ?>
                </div>
            <?php else : ?>
                <?= $this->partial('partials/form_field', [
                    'name' => $name,
                    'label' => $field['label'],
                    'type' => ['int' => 'number', 'email' => 'email', 'url' => 'url'][$field['type']] ?? 'text',
                    'value' => $value,
                    'help' => $help,
                    'required' => $field['type'] !== 'url',
                ]) ?>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <p class="admin-actions"><button type="submit" class="btn btn--primary">Save settings</button></p>
</form>
