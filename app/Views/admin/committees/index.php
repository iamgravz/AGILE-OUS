<?php
/**
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $committees
 * @var list<array<string, mixed>> $positions
 * @var list<array<string, mixed>> $options
 * @var list<string> $roleTypes
 */
?>
<?php $this->start('title') ?>Committees and positions<?php $this->end() ?>
<?php $this->start('page_title') ?>Committees and positions<?php $this->end() ?>

<h2>Committees</h2>
<div class="admin-split admin-section">
    <div class="table-wrap">
        <table class="table table--stack">
            <caption class="sr-only">Committees</caption>
            <thead><tr><th scope="col">Committee</th><th scope="col">Head</th><th scope="col" class="num">Positions</th></tr></thead>
            <tbody>
                <?php foreach ($committees as $committee) : ?>
                    <tr>
                        <td data-label="Committee"><a href="<?= e(route('admin.committees.show', ['id' => (int) $committee['committee_id']])) ?>"><?= e($committee['name']) ?></a></td>
                        <td data-label="Head"><?= e($committee['head_name'] ?? 'No head yet') ?></td>
                        <td class="num" data-label="Positions"><?= e($committee['position_count']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <form class="card" method="post" action="<?= e(route('admin.committees')) ?>">
        <?= csrf_field() ?>
        <div class="card__header"><h3 class="card__title">Add a committee</h3></div>
        <div class="card__body">
            <?= $this->partial('partials/form_field', ['name' => 'name', 'label' => 'Name', 'value' => (string) old('name', '')]) ?>
            <?= $this->partial('partials/form_field', ['name' => 'description', 'label' => 'Description', 'value' => (string) old('description', ''), 'required' => false]) ?>
            <button type="submit" class="btn btn--primary">Add committee</button>
        </div>
    </form>
</div>

<h2>Positions</h2>
<div class="admin-split">
    <div class="table-wrap">
        <table class="table table--stack">
            <caption class="sr-only">Positions</caption>
            <thead><tr><th scope="col">Position</th><th scope="col">Committee</th><th scope="col">Type</th><th scope="col" class="num" title="Members, HR requests, vacancies and appointments that use this position">In use by</th></tr></thead>
            <tbody>
                <?php foreach ($positions as $position) : ?>
                    <tr>
                        <td data-label="Position"><a href="<?= e(route('admin.positions.show', ['id' => (int) $position['position_id']])) ?>"><?= e($position['title']) ?></a></td>
                        <td data-label="Committee"><?= e($position['committee_name'] ?? 'Executive') ?></td>
                        <td data-label="Type"><?= e(ucfirst(str_replace('_', ' ', $position['role_type']))) ?></td>
                        <td class="num" data-label="In use by"><?= e($position['usage_count']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <form class="card" method="post" action="<?= e(route('admin.positions')) ?>">
        <?= csrf_field() ?>
        <div class="card__header"><h3 class="card__title">Add a position</h3></div>
        <div class="card__body">
            <?= $this->partial('partials/form_field', ['name' => 'title', 'label' => 'Title', 'value' => (string) old('title', '')]) ?>
            <div class="field">
                <label class="field__label" for="committee_id">Committee</label>
                <select class="select" id="committee_id" name="committee_id">
                    <option value="">Executive (no committee)</option>
                    <?php foreach ($options as $option) : ?>
                        <option value="<?= e($option['committee_id']) ?>"<?= (string) old('committee_id', '') === (string) $option['committee_id'] ? ' selected' : '' ?>><?= e($option['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field__label" for="role_type">Type</label>
                <select class="select" id="role_type" name="role_type" required>
                    <?php foreach ($roleTypes as $type) : ?>
                        <option value="<?= e($type) ?>"<?= (string) old('role_type', '') === $type ? ' selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $type))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn--secondary">Add position</button>
        </div>
    </form>
</div>
