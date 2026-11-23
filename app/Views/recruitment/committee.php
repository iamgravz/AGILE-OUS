<?php
/**
 * The committee head's read-only view.
 *
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $vacancies
 */
?>
<?php $this->start('title') ?>My Committee<?php $this->end() ?>
<?php $this->start('page_title') ?>My committee's open positions<?php $this->end() ?>

<?php if ($vacancies === []) : ?>
    <div class="empty-state">
        <p class="empty-state__text">Vacancies appear here once the MSW Committee Head publishes them from your HR requests.</p>
        <?php if (can('hr_request.submit')) : ?><a class="btn btn--secondary btn--sm" href="<?= e(route('hr_requests')) ?>">Go to HR requests</a><?php endif; ?>
    </div>
<?php endif; ?>

<div class="account-sections">
    <?php foreach ($vacancies as $vacancy) : ?>
        <section class="card" aria-labelledby="vacancy-<?= e($vacancy['vacancy_id']) ?>">
            <div class="card__header document-card__header">
                <h2 class="card__title" id="vacancy-<?= e($vacancy['vacancy_id']) ?>"><?= e($vacancy['title']) ?></h2>
                <?= status_badge($vacancy['status']) ?>
            </div>
            <div class="card__body">
                <p class="text-muted"><?= e($vacancy['committee_name']) ?>. <?= e($vacancy['slots_filled']) ?> of <?= e($vacancy['slots']) ?> slots filled. Closes <?= e(datetime_ph($vacancy['closes_at'])) ?>.</p>
                <?php if ($vacancy['applications'] === []) : ?>
                    <p class="text-muted">No applications to show.</p>
                <?php else : ?>
                    <div class="table-wrap">
                        <table class="table table--stack">
                            <caption class="sr-only">Applications for <?= e($vacancy['title']) ?></caption>
                            <thead>
                                <tr><th scope="col">Applicant</th><th scope="col">Status</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vacancy['applications'] as $application) : ?>
                                    <tr>
                                        <td data-label="Applicant"><a href="<?= e(route('applications.show', ['id' => (int) $application['application_id']])) ?>"><?= e($application['first_name'] . ' ' . $application['last_name']) ?></a></td>
                                        <td data-label="Status"><?= status_badge($application['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>
