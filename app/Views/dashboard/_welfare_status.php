<?php
/**
 * The user's own welfare cases, status only.
 *
 * @var list<array<string, mixed>> $cases
 */
?>
<section class="card" aria-labelledby="dashboard-my-welfare">
    <div class="card__header"><h2 class="card__title" id="dashboard-my-welfare">My welfare cases</h2></div>
    <div class="card__body">
        <?php if ($cases === []) : ?>
            <p class="text-muted">You have not submitted a concern.<?php if (can('welfare.submit')) : ?> If you need help, <a href="<?= e(route('welfare.new')) ?>">tell the MSW Committee</a>.<?php endif; ?></p>
        <?php else : ?>
            <ul class="case-list">
                <?php foreach ($cases as $case) : ?>
                    <li class="document-card__header">
                        <a href="<?= e(route('my_welfare_cases.show', ['id' => (int) $case['case_id']])) ?>"><code><?= e($case['case_code']) ?></code></a>
                        <?= status_badge($case['status']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
