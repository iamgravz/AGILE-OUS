<?php
/**
 * @var \App\Core\View $this
 * @var array{data: list<array<string, mixed>>, page: int, lastPage: int, total: int} $result
 * @var string $filter
 */
$choices = ['pending' => 'Pending'] + \App\Services\InterviewService::RESULTS;
?>
<?php $this->start('title') ?>Interviews<?php $this->end() ?>
<?php $this->start('page_title') ?>Interviews<?php $this->end() ?>

<form class="applications-filters" method="get" action="<?= e(route('interviews')) ?>" data-no-guard>
    <div class="field">
        <label class="field__label" for="result">Result</label>
        <select class="select" id="result" name="result">
            <option value="">All results</option>
            <?php foreach ($choices as $key => $label) : ?>
                <option value="<?= e($key) ?>"<?= $filter === $key ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="applications-filters__actions">
        <button type="submit" class="btn btn--secondary">Apply filters</button>
        <a class="btn btn--ghost" href="<?= e(route('interviews')) ?>">Clear</a>
    </div>
</form>

<?php if ($result['data'] === []) : ?>
    <div class="empty-state">
        <p class="empty-state__text">Interviews appear here after one is scheduled from an application.</p>
        <?php if (can('evaluation.view')) : ?><a class="btn btn--secondary btn--sm" href="<?= e(route('applications')) ?>">Go to applications</a><?php endif; ?>
    </div>
<?php else : ?>
    <div class="table-wrap">
        <table class="table table--stack">
            <caption class="sr-only">Interviews</caption>
            <thead>
                <tr>
                    <th scope="col">Applicant</th>
                    <th scope="col">Date and time</th>
                    <th scope="col">Mode</th>
                    <th scope="col">Interviewer</th>
                    <th scope="col">Result</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['data'] as $interview) : ?>
                    <tr>
                        <td data-label="Applicant"><a href="<?= e(route('applications.show', ['id' => (int) $interview['application_id']])) ?>?tab=interviews"><?= e($interview['applicant_name']) ?></a></td>
                        <td data-label="Date and time"><?= e(datetime_ph($interview['scheduled_at'])) ?></td>
                        <td data-label="Mode"><?= e(\App\Services\InterviewService::MODES[$interview['mode']]) ?></td>
                        <td data-label="Interviewer"><?= e($interview['interviewer_name']) ?></td>
                        <td data-label="Result"><?= status_badge($interview['result']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="portal-pagination">
        <?= $this->partial('partials/pagination', ['page' => $result['page'], 'lastPage' => $result['lastPage'], 'url' => route('interviews'), 'query' => $filter === '' ? [] : ['result' => $filter]]) ?>
    </div>
<?php endif; ?>
