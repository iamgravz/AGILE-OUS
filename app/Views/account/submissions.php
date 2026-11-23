<?php
/**
 * My Submissions: applications, welfare concerns, candidacies, and ID or certificate requests, each with its status.
 *
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $applications
 * @var list<array<string, mixed>> $cases
 * @var list<array<string, mixed>> $candidacies
 * @var list<array<string, mixed>> $requests
 * @var list<array<string, mixed>> $events registrations, newest event first
 */
use App\Services\CandidacyService;
use App\Services\CredentialRequestService;
use App\Services\CredentialService;

$total = count($applications) + count($cases) + count($candidacies) + count($requests) + count($events);
$candidacyTones = ['filed' => 'info', 'qualified' => 'success', 'disqualified' => 'danger', 'withdrawn' => 'neutral', 'elected' => 'success'];
$requestTones = ['requested' => 'info', 'issued' => 'success', 'declined' => 'danger', 'cancelled' => 'neutral'];
?>
<?php $this->start('title') ?>My Submissions<?php $this->end() ?>
<?php $this->start('page_eyebrow') ?>My account<?php $this->end() ?>
<?php $this->start('page_title') ?>My Submissions<?php $this->end() ?>

<div class="stat-grid">
    <div class="stat-card"><p class="stat-card__label">Applications</p><p class="stat-card__value"><?= e(count($applications)) ?></p></div>
    <div class="stat-card"><p class="stat-card__label">Welfare concerns</p><p class="stat-card__value"><?= e(count($cases)) ?></p></div>
    <div class="stat-card"><p class="stat-card__label">Candidacies</p><p class="stat-card__value"><?= e(count($candidacies)) ?></p></div>
    <div class="stat-card"><p class="stat-card__label">ID and certificate requests</p><p class="stat-card__value"><?= e(count($requests)) ?></p></div>
    <div class="stat-card"><p class="stat-card__label">Events</p><p class="stat-card__value"><?= e(count($events)) ?></p></div>
</div>

<?php if ($total === 0) : ?>
    <div class="empty-state">
        <p class="empty-state__text">Nothing sent yet. What you submit (applications, concerns, candidacies and requests) shows here with its status.</p>
        <a class="btn btn--secondary btn--sm" href="<?= e(route('welfare.new')) ?>">Ask for Help</a>
    </div>
<?php endif; ?>

<?php if ($applications !== []) : ?>
    <section class="plain-section" aria-labelledby="sub-applications">
        <div class="card__header"><h2 class="card__title" id="sub-applications">Applications</h2></div>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead><tr><th scope="col">Tracking code</th><th scope="col">For</th><th scope="col">Status</th><th scope="col">Submitted</th></tr></thead>
                <tbody>
                    <?php foreach ($applications as $row) : ?>
                        <tr>
                            <td data-label="Tracking code"><a href="<?= e(route('my_applications.show', ['id' => (int) $row['application_id']])) ?>"><code><?= e($row['tracking_code']) ?></code></a></td>
                            <td data-label="For"><?= e($row['vacancy_title'] ?? 'General Membership') ?></td>
                            <td data-label="Status"><?= status_badge((string) $row['status']) ?></td>
                            <td data-label="Submitted"><?= $row['submitted_at'] !== null ? e(date_ph((string) $row['submitted_at'])) : '<span class="text-muted">Not yet</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php if ($cases !== []) : ?>
    <section class="plain-section" aria-labelledby="sub-cases">
        <div class="card__header"><h2 class="card__title" id="sub-cases">Welfare concerns</h2></div>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead><tr><th scope="col">Case code</th><th scope="col">Subject</th><th scope="col">Status</th><th scope="col">Submitted</th></tr></thead>
                <tbody>
                    <?php foreach ($cases as $row) : ?>
                        <tr>
                            <td data-label="Case code"><a href="<?= e(route('my_welfare_cases.show', ['id' => (int) $row['case_id']])) ?>"><code><?= e($row['case_code']) ?></code></a></td>
                            <td data-label="Subject"><?= e($row['subject']) ?></td>
                            <td data-label="Status"><?= status_badge((string) $row['status']) ?></td>
                            <td data-label="Submitted"><?= e(date_ph((string) $row['submitted_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php if ($candidacies !== []) : ?>
    <section class="plain-section" aria-labelledby="sub-candidacies">
        <div class="card__header"><h2 class="card__title" id="sub-candidacies">Candidacies</h2></div>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead><tr><th scope="col">Running for</th><th scope="col">Academic year</th><th scope="col">Status</th><th scope="col">Remarks</th></tr></thead>
                <tbody>
                    <?php foreach ($candidacies as $row) : ?>
                        <tr>
                            <td data-label="Running for"><a href="<?= e(route('candidacy')) ?>"><?= e($row['position_title']) ?></a></td>
                            <td data-label="Academic year"><?= e($row['academic_year']) ?></td>
                            <td data-label="Status"><span class="badge badge--<?= e($candidacyTones[$row['status']]) ?>"><?= e(CandidacyService::STATUSES[$row['status']]) ?></span></td>
                            <td data-label="Remarks"><?= e($row['remarks'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php if ($requests !== []) : ?>
    <section class="plain-section" aria-labelledby="sub-requests">
        <div class="card__header"><h2 class="card__title" id="sub-requests">ID and certificate requests</h2></div>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead><tr><th scope="col">Asked for</th><th scope="col">Requested</th><th scope="col">Status</th><th scope="col">Remarks</th></tr></thead>
                <tbody>
                    <?php foreach ($requests as $row) : ?>
                        <tr>
                            <td data-label="Asked for"><a href="<?= e(route('account')) ?>#credential-requests"><?= e(CredentialService::TYPES[$row['type']]) ?></a></td>
                            <td data-label="Requested"><?= e(date_ph((string) $row['created_at'])) ?></td>
                            <td data-label="Status"><span class="badge badge--<?= e($requestTones[$row['status']]) ?>"><?= e(CredentialRequestService::STATUSES[$row['status']]) ?></span></td>
                            <td data-label="Remarks"><?= e($row['remarks'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php if ($events !== []) : ?>
    <section class="plain-section" aria-labelledby="sub-events">
        <div class="card__header"><h2 class="card__title" id="sub-events">Events</h2></div>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead><tr><th scope="col">Event</th><th scope="col">Date</th><th scope="col">Attendance</th></tr></thead>
                <tbody>
                    <?php foreach ($events as $row) : ?>
                        <tr>
                            <td data-label="Event"><a href="<?= e(route('posts.show', ['slug' => $row['slug']])) ?>"><?= e($row['title']) ?></a></td>
                            <td data-label="Date"><?= $row['event_start'] !== null ? e(datetime_ph((string) $row['event_start'])) : '<span class="text-muted">Not set</span>' ?></td>
                            <td data-label="Attendance">
                                <?php if ($row['attended_at'] !== null) : ?>
                                    <span class="badge badge--success">Attended</span>
                                <?php elseif ($row['event_start'] !== null && $row['event_start'] < date('Y-m-d H:i:s')) : ?>
                                    <span class="badge badge--neutral">Not checked in</span>
                                <?php else : ?>
                                    <span class="badge badge--info">Registered</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
