<?php
/**
 * The list's side panel: a quick look, with the actions on the full page.
 *
 * @var array<string, mixed> $application
 * @var array<string, mixed> $applicant
 * @var list<array<string, mixed>> $documents
 * @var list<string> $missingDocuments
 */
?>
<dl class="detail-list">
    <div><dt>Applicant</dt><dd><?= e($applicant['first_name'] . ' ' . $applicant['last_name']) ?></dd></div>
    <div><dt>Email</dt><dd><?= e($applicant['email']) ?></dd></div>
    <div><dt>Applying for</dt><dd><?= e($application['vacancy_title'] ?? 'General Membership') ?></dd></div>
    <div><dt>Tracking code</dt><dd><code><?= e($application['tracking_code']) ?></code></dd></div>
    <div><dt>Status</dt><dd><?= status_badge($application['status']) ?></dd></div>
    <?php if ($application['submitted_at'] !== null) : ?>
        <div><dt>Submitted</dt><dd><?= e(datetime_ph($application['submitted_at'])) ?></dd></div>
    <?php endif; ?>
    <?php if (can('document.verify')) : ?>
        <div>
            <dt>Documents</dt>
            <dd><?= e(count($documents)) ?> uploaded<?= $missingDocuments === [] ? ', all required verified' : ', ' . e(count($missingDocuments)) . ' required not verified' ?></dd>
        </div>
    <?php endif; ?>
</dl>
<a class="btn btn--primary btn--block drawer__open" href="<?= e(route('applications.show', ['id' => $application['application_id']])) ?>">Open full application</a>
