<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $position
 * @var list<string> $roleTypes
 */
$id = (int) $position['position_id'];
?>
<?php $this->start('title') ?><?= e($position['title']) ?><?php $this->end() ?>
<?php $this->start('form_side') ?><?= $this->partial('partials/side_panel', ['blocks' => [['title' => 'About positions', 'items' => ['The type decides which role a holder gets.', 'A position in use cannot change its type or be deleted.', 'Add a new position instead when the duties really change.']]]]) ?><?php $this->end() ?>
<?php $this->start('page_eyebrow') ?><?= e($position['committee_name'] ?? 'Executive') ?> position<?php $this->end() ?>
<?php $this->start('page_title') ?><?= e($position['title']) ?><?php $this->end() ?>

<form class="admin-form" method="post" action="<?= e(route('admin.positions.show', ['id' => $id])) ?>">
    <?= csrf_field() ?>
    <?= $this->partial('partials/form_field', ['name' => 'title', 'label' => 'Title', 'value' => (string) old('title', $position['title'])]) ?>
    <div class="field">
        <label class="field__label" for="role_type">Type</label>
        <select class="select" id="role_type" name="role_type" required>
            <?php foreach ($roleTypes as $type) : ?>
                <option value="<?= e($type) ?>"<?= $position['role_type'] === $type ? ' selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $type))) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($position['in_use']) : ?><p class="field__help">This position is in use, so its type cannot change. Add a new position instead.</p><?php endif; ?>
    </div>
    <button type="submit" class="btn btn--primary">Save position</button>
</form>
<?php if ($position['in_use']) : ?>
    <p class="field__help admin-actions">This position has members, HR requests, vacancies or appointments, so it cannot be deleted.</p>
<?php else : ?>
    <form class="admin-actions" method="post" action="<?= e(route('admin.positions.delete', ['id' => $id])) ?>" data-confirm="This cannot be undone." data-confirm-title="Delete this position?" data-confirm-action="Delete position">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn--danger btn--sm">Delete position</button>
    </form>
<?php endif; ?>
