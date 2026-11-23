<?php
/**
 * Stat cards. Counts come straight from DashboardService. The bar is drawn only when the card's status has a real total to compare with.
 *
 * @var list<array{label: string, value: int, url?: string}> $cards
 * @var array<string, int> $applicationStatuses (optional)
 */
$statuses = $applicationStatuses ?? [];
$totalApplications = array_sum($statuses);
$shares = ['New applications', 'Under review'];
?>
<?php if ($cards !== []) : ?>
    <div class="stat-grid">
        <?php foreach ($cards as $card) : ?>
            <?php
            // The accent says something: a count with somewhere to go is waiting on someone; a zero is clear.
            $state = (int) $card['value'] === 0 ? 'clear' : (isset($card['url']) ? 'waiting' : 'count');
            ?>
            <div class="stat-card stat-card--<?= e($state) ?>">
                <p class="stat-card__label"><?= e($card['label']) ?></p>
                <?php if ($state === 'waiting') : ?><span class="stat-card__state">Waiting</span><?php endif; ?>
                <p class="stat-card__value"><?= e($card['value']) ?></p>
                <?php if ($state === 'clear') : ?><p class="stat-card__sub stat-card__sub--clear"><?= icon('check') ?> Nothing waiting</p><?php endif; ?>
                <?php if (in_array($card['label'], $shares, true) && $totalApplications > 0) : ?>
                    <p class="stat-card__sub"><?= e($card['value']) ?> of <?= e($totalApplications) ?> applications</p>
                    <progress class="progress" value="<?= e($card['value']) ?>" max="<?= e($totalApplications) ?>" aria-hidden="true"></progress>
                <?php endif; ?>
                <?php if (isset($card['url'])) : ?>
                    <a class="card__link" href="<?= e($card['url']) ?>">View<span class="sr-only"> <?= e($card['label']) ?></span> <?= icon('arrow-right') ?></a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
