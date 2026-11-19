<?php
/**
 * The newest applications staff can see.
 *
 * @var list<array<string, mixed>> $applications
 */
?>
<section class="dashboard-section" aria-labelledby="dashboard-latest">
    <div class="portal-section__header">
        <h2 id="dashboard-latest">Latest applications</h2>
        <a class="card__link" href="<?= e(route('applications')) ?>">View all <?= icon('arrow-right') ?></a>
    </div>
    <?php if ($applications === []) : ?>
        <p class="text-muted">Applications appear here after applicants submit them.</p>
    <?php else : ?>
        <div class="table-wrap">
            <table class="table table--stack">
                <caption class="sr-only">Latest applications</caption>
                <thead>
                    <tr>
                        <th scope="col">Applicant</th>
                        <th scope="col">Applying for</th>
                        <th scope="col">Tracking code</th>
                        <th scope="col">Status</th>
                        <th scope="col">Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $application) : ?>
                        <tr>
                            <td data-label="Applicant"><a href="<?= e(route('applications.show', ['id' => (int) $application['application_id']])) ?>"><?= e($application['first_name'] . ' ' . $application['last_name']) ?></a></td>
                            <td data-label="Applying for"><?= e($application['vacancy_title'] ?? 'General Membership') ?></td>
                            <td data-label="Tracking code"><code><?= e($application['tracking_code']) ?></code></td>
                            <td data-label="Status"><?= status_badge($application['status']) ?></td>
                            <td data-label="Submitted"><?= $application['submitted_at'] === null ? '' : e(date_ph($application['submitted_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
