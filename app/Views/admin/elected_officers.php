<?php
/**
 * Elected officers: the Executive Board and the Year-Level Representatives (Article VI), with a form to record a winner.
 *
 * @var \App\Core\View $this
 * @var list<array{position_id: int, title: string, group: string, slots: int, holders: list<string>, open: int}> $seats
 * @var int $chosen
 */
$byGroup = [];

foreach ($seats as $seat) {
    $byGroup[$seat['group']][] = $seat;
}

$selected = (int) old('position_id', (string) $chosen);
$positionError = field_error('position_id');
?>
<?php $this->start('title') ?>Elected officers<?php $this->end() ?>
<?php $this->start('page_title') ?>Elected officers<?php $this->end() ?>

<p class="text-muted">The Executive Board and the Year-Level Representatives are elected (Constitution and By-Laws, Article VI), so they never apply. Record each winner here: they get an account with the right role, or keep the one they have.</p>

<?php foreach ($byGroup as $group => $rows) : ?>
    <h2 class="open-positions__committee"><?= e($group) ?></h2>
    <div class="table-wrap">
        <table class="table table--stack">
            <caption class="sr-only"><?= e($group) ?></caption>
            <thead>
                <tr>
                    <th scope="col">Position</th>
                    <th scope="col">Held by</th>
                    <th scope="col">Status</th>
                    <th scope="col"><span class="sr-only">Action</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $seat) : ?>
                    <tr>
                        <td data-label="Position"><?= e($seat['title']) ?></td>
                        <td data-label="Held by"><?= $seat['holders'] === [] ? '<span class="text-muted">Vacant</span>' : e(implode(', ', $seat['holders'])) ?></td>
                        <td data-label="Status"><span class="badge badge--<?= $seat['open'] === 0 ? 'success' : 'neutral' ?>"><?= $seat['open'] === 0 ? 'Filled' : 'Vacant' ?></span></td>
                        <td data-label="Action">
                            <?php if ($seat['open'] > 0) : ?>
                                <a class="btn btn--secondary btn--sm" href="<?= e(route('admin.elected_officers')) ?>?position=<?= e($seat['position_id']) ?>#record">Record officer</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endforeach; ?>

<h2 class="open-positions__committee" id="record">Record an elected officer</h2>
<form class="admin-form" method="post" action="<?= e(route('admin.elected_officers')) ?>">
    <?= csrf_field() ?>
    <div class="field <?= $positionError !== null ? 'field--error' : '' ?>">
        <label class="field__label" for="position_id">Position <span class="field__required" aria-hidden="true">*</span></label>
        <select class="select" id="position_id" name="position_id" required>
            <option value="">Choose a position</option>
            <?php foreach ($byGroup as $group => $rows) : ?>
                <optgroup label="<?= e($group) ?>">
                    <?php foreach ($rows as $seat) : ?>
                        <option value="<?= e($seat['position_id']) ?>"<?= $seat['open'] === 0 ? ' disabled' : '' ?><?= $selected === $seat['position_id'] ? ' selected' : '' ?>><?= e($seat['title']) ?><?= $seat['open'] === 0 ? ' (filled)' : '' ?></option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
        <?php if ($positionError !== null) : ?><p class="field__error"><?= icon('alert') ?> <?= e($positionError) ?></p><?php endif; ?>
    </div>
    <div class="grid grid--2">
        <?= $this->partial('partials/form_field', ['name' => 'first_name', 'label' => 'First name', 'value' => (string) old('first_name', '')]) ?>
        <?= $this->partial('partials/form_field', ['name' => 'last_name', 'label' => 'Last name', 'value' => (string) old('last_name', '')]) ?>
    </div>
    <?= $this->partial('partials/form_field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => (string) old('email', ''), 'help' => 'If this email already has an account, that account is kept and gains the position.']) ?>
    <div class="grid grid--2">
        <?= $this->partial('partials/form_field', ['name' => 'student_no', 'label' => 'PUP student number', 'value' => (string) old('student_no', ''), 'help' => 'For example, 2024-xxxxx-MN-0.']) ?>
        <?= $this->partial('partials/form_field', ['name' => 'contact_no', 'label' => 'Contact number', 'type' => 'tel', 'value' => (string) old('contact_no', ''), 'required' => false, 'help' => 'Optional.']) ?>
    </div>
    <?= $this->partial('partials/form_field', ['name' => 'reason', 'label' => 'Election record', 'value' => (string) old('reason', 'Elected in the AGILE-OUS general election, AY ' . date('Y') . '-' . (date('Y') + 1)), 'help' => 'Kept in their role history, for example the election date.']) ?>
    <button type="submit" class="btn btn--primary">Record officer and create the account</button>
</form>
