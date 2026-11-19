<?php
/**
 * @var int $page
 * @var int $lastPage
 * @var string $url list URL without a query string
 * @var array<string, string> $query filters to keep on each link (optional)
 */
$query ??= [];
$pageUrl = static fn (int $number): string => $url . '?' . http_build_query($query + ['page' => $number]);
?>
<?php if ($lastPage > 1) : ?>
    <nav class="pagination" aria-label="Pagination">
        <ul class="pagination__list">
            <?php if ($page > 1) : ?>
                <li><a class="pagination__link" href="<?= e($pageUrl($page - 1)) ?>" aria-label="Previous page"><?= icon('chevron-left') ?></a></li>
            <?php endif; ?>
            <?php for ($number = 1; $number <= $lastPage; $number++) : ?>
                <?php if ($number === 1 || $number === $lastPage || abs($number - $page) <= 1) : ?>
                    <li><a class="pagination__link" href="<?= e($pageUrl($number)) ?>"<?= $number === $page ? ' aria-current="page"' : '' ?>><?= e($number) ?></a></li>
                <?php elseif (abs($number - $page) === 2) : ?>
                    <li><span class="pagination__gap">&hellip;</span></li>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $lastPage) : ?>
                <li><a class="pagination__link" href="<?= e($pageUrl($page + 1)) ?>" aria-label="Next page"><?= icon('chevron-right') ?></a></li>
            <?php endif; ?>
        </ul>
    </nav>
<?php endif; ?>
