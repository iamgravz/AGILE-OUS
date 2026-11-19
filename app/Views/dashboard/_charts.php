<?php
/**
 * The first chart with months is the area chart, with this month's count and a trend computed against the month before.
 * Each chart has the same numbers in a table, for screen readers and for reading exact values.
 *
 * @var list<array{title: string, unit: string, labels: list<string>, values: list<int>}> $charts
 */
$lead = null;
foreach ($charts as $index => $chart) {
    if ($chart['title'] === 'Applications per month') {
        $lead = $chart;
        unset($charts[$index]);
    }
}
$table = static function (array $chart): void { ?>
    <details class="dashboard-chart__table">
        <summary>View as table<span class="sr-only"> for <?= e($chart['title']) ?></span></summary>
        <table class="table">
            <caption class="sr-only"><?= e($chart['title']) ?></caption>
            <tbody>
                <?php foreach ($chart['labels'] as $i => $label) : ?>
                    <tr><th scope="row"><?= e($label) ?></th><td class="num"><?= e($chart['values'][$i]) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </details>
<?php };
?>
<?php if ($lead !== null) : ?>
    <?php
    $current = (int) end($lead['values']);
    $previous = (int) ($lead['values'][count($lead['values']) - 2] ?? 0);
    $change = $current - $previous;
    $previousLabel = (string) ($lead['labels'][count($lead['labels']) - 2] ?? '');
    [$firstMonth, $firstYear] = explode(' ', $lead['labels'][0]) + ['', ''];
    [$lastMonth, $lastYear] = explode(' ', (string) end($lead['labels'])) + ['', ''];
    $range = $firstYear === $lastYear ? "{$firstMonth}–{$lastMonth} {$lastYear}" : "{$firstMonth} {$firstYear}–{$lastMonth} {$lastYear}";
    ?>
    <section class="card dashboard-section" aria-labelledby="chart-title-lead">
        <div class="card__header">
            <h2 class="card__title" id="chart-title-lead" data-chart-heading><?= e($lead['title'] . ', ' . $range) ?></h2>
            <div class="segmented" role="group" aria-label="Months shown">
                <button type="button" class="segmented__item" data-chart-range="3" aria-pressed="false">3 months</button>
                <button type="button" class="segmented__item" data-chart-range="6" aria-pressed="true">6 months</button>
            </div>
        </div>
        <div class="card__body">
            <p class="chart-card__figure">
                <?= e($current) ?>
                <?php if ($change !== 0) : ?>
                    <span class="chip <?= $change > 0 ? 'chip--up' : 'chip--down' ?>"><?= icon($change > 0 ? 'arrow-up' : 'arrow-down') ?><?= e(abs($change)) ?><span class="sr-only"> <?= $change > 0 ? 'more' : 'fewer' ?></span></span>
                <?php else : ?>
                    <span class="chip chip--flat">No change</span>
                <?php endif; ?>
            </p>
            <p class="chart-card__note">This month, compared with <?= e($previous) ?> in <?= e($previousLabel) ?></p>
            <div class="dashboard-chart">
                <canvas data-chart="<?= e(json_encode($lead, JSON_THROW_ON_ERROR)) ?>" data-chart-type="area" role="img" aria-label="<?= e($lead['title']) ?>. The numbers are in the table below."></canvas>
            </div>
            <p class="chart-legend"><?= e(ucfirst($lead['unit'])) ?> received</p>
            <?php $table($lead); ?>
        </div>
    </section>
<?php endif; ?>
<div class="chart-grid">
    <?php foreach (array_values($charts) as $index => $chart) : ?>
        <section class="card" aria-labelledby="chart-title-<?= e($index) ?>">
            <div class="card__header"><h2 class="card__title" id="chart-title-<?= e($index) ?>"><?= e($chart['title']) ?></h2></div>
            <div class="card__body">
                <?php if (array_sum($chart['values']) === 0) : ?>
                    <p class="text-muted">No <?= e($chart['unit']) ?> to show yet.</p>
                <?php else : ?>
                    <div class="dashboard-chart">
                        <canvas data-chart="<?= e(json_encode($chart, JSON_THROW_ON_ERROR)) ?>" role="img" aria-label="<?= e($chart['title']) ?>. The numbers are in the table below."></canvas>
                    </div>
                    <?php $table($chart); ?>
                <?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>
