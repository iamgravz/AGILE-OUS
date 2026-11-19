<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $committee
 * @var list<array<string, mixed>> $candidates
 */
$id = (int) $committee['committee_id'];
?>
<?php $this->start('title') ?><?= e($committee['name']) ?><?php $this->end() ?>
<?php $this->start('page_eyebrow') ?>Committee<?php $this->end() ?>
<?php $this->start('page_title') ?><?= e($committee['name']) ?><?php $this->end() ?>

<div class="account-sections">
    <form class="plain-section" method="post" action="<?= e(route('admin.committees.show', ['id' => $id])) ?>">
        <?= csrf_field() ?>
        <div class="card__header"><h2 class="card__title">Details</h2></div>
        <div class="card__body">
            <?= $this->partial('partials/form_field', ['name' => 'name', 'label' => 'Name', 'value' => (string) old('name', $committee['name'])]) ?>
            <?= $this->partial('partials/form_field', ['name' => 'description', 'label' => 'Description', 'value' => (string) old('description', $committee['description'] ?? ''), 'required' => false]) ?>
            <button type="submit" class="btn btn--secondary">Save committee</button>
        </div>
    </form>

    <form class="plain-section" method="post" action="<?= e(route('admin.committees.head', ['id' => $id])) ?>" data-confirm="They get this committee's head position and its role, and a new ID and certificate wait for approval. Any previous head's role ends." data-confirm-title="Set this committee head?" data-confirm-action="Set head">
        <?= csrf_field() ?>
        <div class="card__header"><h2 class="card__title">Committee head</h2></div>
        <div class="card__body">
            <div class="field">
                <label class="field__label" for="user_id">Head</label>
                <select class="select" id="user_id" name="user_id" required>
                    <option value="">Choose an active account</option>
                    <?php foreach ($candidates as $person) : ?>
                        <option value="<?= e($person['user_id']) ?>"<?= (int) ($committee['head_user_id'] ?? 0) === (int) $person['user_id'] ? ' selected' : '' ?>><?= e($person['last_name'] . ', ' . $person['first_name'] . ' (' . $person['role_name'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field__label" for="reason">Reason for the change</label>
                <input class="input" id="reason" name="reason" maxlength="255" required>
                <p class="field__help">Saved in the audit log.</p>
            </div>
            <button type="submit" class="btn btn--primary">Set committee head</button>
        </div>
    </form>

    <form method="post" action="<?= e(route('admin.committees.delete', ['id' => $id])) ?>" data-confirm="This cannot be undone." data-confirm-title="Delete this committee?" data-confirm-action="Delete committee">
        <?= csrf_field() ?>
        <p class="field__help">A committee with a head, positions or HR requests cannot be deleted.</p>
        <button type="submit" class="btn btn--danger btn--sm">Delete committee</button>
    </form>
</div>
