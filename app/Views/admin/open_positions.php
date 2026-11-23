<?php
/**
 * Open for application: which positions applicants can choose on the apply page, with AGILE's dashboard numbers.
 *
 * @var \App\Core\View $this
 * @var list<array{position_id: int, title: string, role_type: string, committee_id: int, committee_name: string, is_open: bool, target_slots: ?int, filled: int, remaining: ?int, label: string}> $positions
 * @var array{target: int, filled: int, remaining: int, open: int} $totals
 */
// [label, badge tone] as on AGILE's dashboard: full positions are Filled, whether or not they are ticked open.
$status = static function (array $p): array {
    if ($p['remaining'] === 0) {
        return ['Filled', 'success'];
    }

    return $p['is_open'] ? ['Open', 'info'] : ['Closed', 'neutral'];
};
$byCommittee = [];

foreach ($positions as $position) {
    $byCommittee[$position['committee_name']][] = $position;
}
?>
<?php $this->start('title') ?>Open for application<?php $this->end() ?>
<?php $this->start('page_title') ?>Open for application<?php $this->end() ?>

<div class="stat-grid open-positions__totals">
    <div class="stat-card"><p class="stat-card__label">Target</p><p class="stat-card__value"><?= e($totals['target']) ?></p></div>
    <div class="stat-card"><p class="stat-card__label">Filled</p><p class="stat-card__value"><?= e($totals['filled']) ?></p></div>
    <div class="stat-card"><p class="stat-card__label">Remaining</p><p class="stat-card__value"><?= e($totals['remaining']) ?></p></div>
    <div class="stat-card"><p class="stat-card__label">Open now</p><p class="stat-card__value"><?= e($totals['open']) ?></p></div>
</div>

<form method="post" action="<?= e(route('admin.open_positions')) ?>">
    <?= csrf_field() ?>
    <?php foreach ($byCommittee as $committee => $rows) : ?>
        <h2 class="open-positions__committee"><?= e($committee) ?></h2>
        <div class="table-wrap">
            <table class="table table--stack open-positions__table">
                <caption class="sr-only"><?= e($committee) ?> positions</caption>
                <thead>
                    <tr>
                        <th scope="col">Position</th>
                        <th scope="col" class="num">Target</th>
                        <th scope="col" class="num">Filled</th>
                        <th scope="col" class="num">Remaining</th>
                        <th scope="col">Status</th>
                        <th scope="col">Open for application</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $p) : ?>
                        <?php [$label, $tone] = $status($p); $id = $p['position_id']; ?>
                        <tr>
                            <td data-label="Position"><?= e($p['title']) ?></td>
                            <td class="num" data-label="Target">
                                <label class="sr-only" for="target-<?= e($id) ?>">Target for <?= e($p['label']) ?></label>
                                <input class="input open-positions__target" id="target-<?= e($id) ?>" name="target[<?= e($id) ?>]" type="number" min="0" max="<?= e(\App\Services\OpenPositionsService::MAX_TARGET) ?>" inputmode="numeric" value="<?= e($p['target_slots'] ?? '') ?>" placeholder="No limit">
                            </td>
                            <td class="num" data-label="Filled"><?= e($p['filled']) ?></td>
                            <td class="num" data-label="Remaining"><?= e($p['remaining'] ?? '—') ?></td>
                            <td data-label="Status"><span class="badge badge--<?= e($tone) ?>"><?= e($label) ?></span></td>
                            <td data-label="Open for application">
                                <label class="check">
                                    <input type="checkbox" name="open[]" value="<?= e($id) ?>"<?= $p['is_open'] ? ' checked' : '' ?>>
                                    <span>Open<span class="sr-only"> <?= e($p['label']) ?></span></span>
                                </label>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
    <div class="open-positions__actions">
        <button type="submit" class="btn btn--primary">Save</button>
    </div>
</form>
