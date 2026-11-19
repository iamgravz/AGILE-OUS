<?php
/**
 * @var \App\Core\View $this
 * @var array{data: list<array<string, mixed>>, page: int, lastPage: int, total: int} $result
 * @var list<array<string, mixed>> $ready
 * @var string $filter
 */
?>
<?php $this->start('title') ?>Appointments<?php $this->end() ?>
<?php $this->start('page_title') ?>Appointments<?php $this->end() ?>

<?php if ($ready !== []) : ?>
    <section class="card applications-actions" aria-labelledby="ready-title">
        <div class="card__header"><h2 class="card__title" id="ready-title">Ready to prepare</h2></div>
        <div class="card__body">
            <div class="table-wrap">
                <table class="table table--stack">
                    <caption class="sr-only">Approved position applications without an appointment</caption>
                    <thead>
                        <tr><th scope="col">Applicant</th><th scope="col">Vacancy</th><th scope="col" class="num">Slots filled</th><th scope="col">Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ready as $row) : ?>
                            <tr>
                                <td data-label="Applicant"><a href="<?= e(route('applications.show', ['id' => (int) $row['application_id']])) ?>"><?= e($row['applicant_name']) ?></a></td>
                                <td data-label="Vacancy"><span class="truncate" title="<?= e($row['vacancy_title']) ?>"><?= e($row['vacancy_title']) ?></span></td>
                                <td class="num" data-label="Slots filled"><?= e($row['slots_filled']) ?> of <?= e($row['slots']) ?></td>
                                <td data-label="Action"><a class="btn btn--ghost btn--sm" href="<?= e(route('appointments.prepare')) ?>?application=<?= e($row['application_id']) ?>">Prepare<span class="sr-only"> an appointment for <?= e($row['applicant_name']) ?></span></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
<?php endif; ?>

<form class="applications-filters" method="get" action="<?= e(route('appointments')) ?>" data-no-guard>
    <div class="field">
        <label class="field__label" for="status">Status</label>
        <select class="select" id="status" name="status">
            <option value="">All statuses</option>
            <?php foreach (\App\Services\AppointmentService::STATUSES as $status) : ?>
                <option value="<?= e($status) ?>"<?= $filter === $status ? ' selected' : '' ?>><?= e(ucfirst($status)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="applications-filters__actions">
        <button type="submit" class="btn btn--secondary">Apply filters</button>
        <a class="btn btn--ghost" href="<?= e(route('appointments')) ?>">Clear</a>
    </div>
</form>

<?php if ($result['data'] === []) : ?>
    <div class="empty-state">
        <p class="empty-state__text">Appointments appear here after one is prepared from an approved application.</p>
        <?php if (can('evaluation.view')) : ?><a class="btn btn--secondary btn--sm" href="<?= e(route('applications')) ?>">Go to applications</a><?php endif; ?>
    </div>
<?php else : ?>
    <div class="table-wrap">
        <table class="table table--stack">
            <caption class="sr-only">Appointments</caption>
            <thead>
                <tr>
                    <th scope="col">Applicant</th>
                    <th scope="col">Position</th>
                    <th scope="col">Effective</th>
                    <th scope="col">Prepared by</th>
                    <th scope="col">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['data'] as $appointment) : ?>
                    <tr>
                        <td data-label="Applicant"><a href="<?= e(route('appointments.show', ['id' => (int) $appointment['appointment_id']])) ?>"><?= e($appointment['applicant_name']) ?></a></td>
                        <td data-label="Position"><span class="truncate" title="<?= e(\App\Services\MembershipService::roleLabel($appointment)) ?>"><?= e(\App\Services\MembershipService::roleLabel($appointment)) ?></span></td>
                        <td data-label="Effective"><?= e(date_ph($appointment['effective_date'])) ?></td>
                        <td data-label="Prepared by"><?= e($appointment['preparer_name']) ?></td>
                        <td data-label="Status"><?= status_badge($appointment['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="portal-pagination">
        <?= $this->partial('partials/pagination', ['page' => $result['page'], 'lastPage' => $result['lastPage'], 'url' => route('appointments'), 'query' => $filter === '' ? [] : ['status' => $filter]]) ?>
    </div>
<?php endif; ?>
