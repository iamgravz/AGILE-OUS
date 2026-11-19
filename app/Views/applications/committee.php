<?php
/**
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $applications
 * @var list<array<string, mixed>> $vacancies
 * @var array{status: string, vacancy: string} $filters
 */
?>
<?php $this->start('title') ?>Applications to my committee<?php $this->end() ?>
<?php $this->start('page_title') ?>Applications to my committee<?php $this->end() ?>
<form class="applications-filters" method="get" action="<?= e(route('applications.committee')) ?>" data-no-guard>
    <div class="field">
        <label class="field__label" for="status">Status</label>
        <select class="select" id="status" name="status">
            <option value="">All statuses</option>
            <?php foreach (\App\Services\ApplicationStatus::ALL as $status) : ?>
                <?php if ($status !== 'draft') : ?>
                    <option value="<?= e($status) ?>"<?= $filters['status'] === $status ? ' selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $status))) ?></option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label class="field__label" for="vacancy">Position</label>
        <select class="select" id="vacancy" name="vacancy">
            <option value="">All positions</option>
            <?php foreach ($vacancies as $vacancy) : ?>
                <option value="<?= e($vacancy['vacancy_id']) ?>"<?= $filters['vacancy'] === (string) $vacancy['vacancy_id'] ? ' selected' : '' ?>><?= e($vacancy['title']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="applications-filters__actions">
        <button type="submit" class="btn btn--secondary">Apply filters</button>
    </div>
</form>
<?php if ($applications === []) : ?>
    <p>No applications to your committee yet. They appear here once a position in your committee receives one.</p>
<?php else : ?>
    <div class="table-wrap">
        <table class="table table--stack">
            <caption class="sr-only">Applications to positions in your committee</caption>
            <thead>
                <tr>
                    <th scope="col">Applicant</th>
                    <th scope="col">Position</th>
                    <th scope="col">Tracking code</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="num">Evaluations</th>
                    <th scope="col" class="num">Average</th>
                    <th scope="col">Submitted</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applications as $application) : ?>
                    <tr>
                        <td data-label="Applicant"><a href="<?= e(route('applications.show', ['id' => (int) $application['application_id']])) ?>"><?= e($application['first_name'] . ' ' . $application['last_name']) ?></a></td>
                        <td data-label="Position"><span class="truncate" title="<?= e($application['vacancy_title']) ?>"><?= e($application['vacancy_title']) ?></span></td>
                        <td data-label="Tracking code"><code><?= e($application['tracking_code']) ?></code></td>
                        <td data-label="Status"><?= status_badge($application['status']) ?></td>
                        <td class="num" data-label="Evaluations"><?= e($application['evaluation_count']) ?></td>
                        <td class="num" data-label="Average"><?= e($application['average_score'] === null ? 'None' : number_format((float) $application['average_score'], 2)) ?></td>
                        <td data-label="Submitted"><?= e(date_ph($application['submitted_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
