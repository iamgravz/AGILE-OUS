<?php if (can('report.export')) : ?><a class="btn btn--secondary btn--sm" href="<?= e(route('reports')) ?>">Reports</a><?php endif; ?>
<?php if (can('vacancy.manage')) : ?><a class="btn btn--primary btn--sm" href="<?= e(route('vacancies.create')) ?>"><?= icon('plus') ?> Create vacancy</a><?php endif; ?>
