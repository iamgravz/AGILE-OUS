<?php
/**
 * @var \App\Core\View $this
 * @var array{data: list<array<string, mixed>>, page: int, lastPage: int, total: int} $result
 * @var string $q
 */
?>
<?php $this->start('title') ?>Evaluations<?php $this->end() ?>
<?php $this->start('page_title') ?>Applications to evaluate<?php $this->end() ?>

<form class="applications-filters" method="get" action="<?= e(route('evaluations')) ?>" data-no-guard>
    <div class="field applications-filters__search">
        <label class="field__label" for="q">Search by applicant or tracking code</label>
        <input class="input" id="q" name="q" type="search" value="<?= e($q) ?>">
    </div>
    <div class="applications-filters__actions">
        <button type="submit" class="btn btn--secondary">Search</button>
        <a class="btn btn--ghost" href="<?= e(route('evaluations')) ?>">Clear</a>
    </div>
</form>

<?php if ($result['data'] === []) : ?>
    <div class="empty-state">
        <p class="empty-state__text">Applications waiting for an evaluation appear here.</p>
    </div>
<?php else : ?>
    <p class="text-muted applications-count"><?= e($result['total']) ?> <?= $result['total'] === 1 ? 'application' : 'applications' ?> waiting for a decision</p>
    <div class="table-wrap">
        <table class="table table--stack">
            <caption class="sr-only">Applications waiting for a decision</caption>
            <thead>
                <tr>
                    <th scope="col">Applicant</th>
                    <th scope="col">Applying for</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="num">Your score</th>
                    <th scope="col" class="num">Evaluations</th>
                    <th scope="col" class="num">Average</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['data'] as $row) : ?>
                    <tr>
                        <td data-label="Applicant"><a href="<?= e(route('applications.show', ['id' => (int) $row['application_id']])) ?>?tab=evaluations"><?= e($row['first_name'] . ' ' . $row['last_name']) ?></a></td>
                        <td data-label="Applying for"><span class="truncate" title="<?= e($row['vacancy_title'] ?? 'General Membership') ?>"><?= e($row['vacancy_title'] ?? 'General Membership') ?></span></td>
                        <td data-label="Status"><?= status_badge($row['status']) ?></td>
                        <td class="num" data-label="Your score"><?= e($row['my_score'] === null ? 'Not yet' : number_format((float) $row['my_score'], 2)) ?></td>
                        <td class="num" data-label="Evaluations"><?= e($row['evaluation_count']) ?></td>
                        <td class="num" data-label="Average"><?= e($row['average_score'] === null ? 'None' : number_format((float) $row['average_score'], 2)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="portal-pagination">
        <?= $this->partial('partials/pagination', ['page' => $result['page'], 'lastPage' => $result['lastPage'], 'url' => route('evaluations'), 'query' => $q === '' ? [] : ['q' => $q]]) ?>
    </div>
<?php endif; ?>
