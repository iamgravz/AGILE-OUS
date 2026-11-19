<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $member
 * @var string $roleLabel
 * @var list<array<string, mixed>> $history
 * @var list<array<string, mixed>> $applications
 * @var list<array<string, mixed>> $activeCredentials
 * @var bool $withDownloads Only the owner may download, so the staff page leaves this off.
 * @var int $headingLevel
 * @var array{tone: string, message: string}|null $notice
 */
$notice ??= null;
$heading = 'h' . $headingLevel;
$isOwn = $member['user_id'] !== null && (int) $member['user_id'] === current_user_id();
// My Account shows these in different places. Staff see them all at once.
$parts ??= ['summary', 'credentials', 'history', 'applications'];
?>
<?php if (in_array('summary', $parts, true)) : ?>
<dl class="detail-list member-details">
    <div><dt>Member number</dt><dd><code><?= e($member['member_no']) ?></code></dd></div>
    <div><dt>Status</dt><dd><?= status_badge($member['status']) ?></dd></div>
    <div><dt>Current role and position</dt><dd><?= e($roleLabel) ?></dd></div>
    <div><dt>Joined</dt><dd><?= e(date_ph($member['joined_at'])) ?></dd></div>
</dl>

<?php endif; ?>
<?php if ($withDownloads && in_array('credentials', $parts, true)) : ?>
    <<?= $heading ?> class="member-section">Digital ID and certificate</<?= $heading ?>>
    <?php
    $approved = array_filter($activeCredentials, static fn (array $c): bool => $c['status'] === 'approved');
    $inReview = count($approved) < count($activeCredentials);
    ?>
    <?php if ($inReview) : ?>
        <p>Your digital ID and certificate are being reviewed by the MSW Committee Head. We'll email you when they're ready.</p>
    <?php elseif ($activeCredentials === []) : ?>
        <p class="text-muted">Your credentials have not been issued yet. Please contact the MSW Committee.</p>
    <?php endif; ?>
    <?php if ($approved !== []) : ?>
        <?= $this->partial('members/_credential_cards', ['credentials' => array_values($approved), 'forOwner' => true, 'headingLevel' => $headingLevel + 1]) ?>
    <?php endif; ?>
<?php endif; ?>

<?php if (in_array('history', $parts, true)) : ?>
<<?= $heading ?> class="member-section">Notices</<?= $heading ?>>
<?php if ($notice !== null) : ?>
    <div class="alert alert--<?= e($notice['tone']) ?>" role="status">
        <?= icon($notice['tone'] === 'success' ? 'check' : 'alert') ?>
        <p class="alert__text"><?= e($notice['message']) ?><?php if ($isOwn) : ?> <a href="<?= e(route('my_eligibility')) ?>">My Eligibility</a><?php endif; ?></p>
    </div>
<?php elseif ($member['role_status'] === 'expired') : ?>
    <p>The officer role ended on <?= e(date_ph($member['role_expired_at'])) ?>. Membership stays Active.</p>
<?php else : ?>
    <p class="text-muted">No notices right now.</p>
<?php endif; ?>

<<?= $heading ?> class="member-section">Role history</<?= $heading ?>>
<?php if ($history === []) : ?>
    <p class="text-muted">No role changes have been recorded.</p>
<?php else : ?>
    <ol class="timeline">
        <?php foreach (array_reverse($history) as $index => $entry) : ?>
            <li class="timeline__item <?= $index === 0 ? 'timeline__item--current' : '' ?>">
                <strong><?= e($entry['new_role']) ?></strong>
                <p class="timeline__meta">
                    <?= e(datetime_ph($entry['changed_at'])) ?> by <?= e($entry['actor_name']) ?><?= $entry['old_role'] !== null ? ', from ' . e($entry['old_role']) : '' ?>
                </p>
                <p class="timeline__note"><?= e($entry['reason']) ?></p>
            </li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>

<?php endif; ?>
<?php if (in_array('applications', $parts, true)) : ?>
<<?= $heading ?> class="member-section">Application history</<?= $heading ?>>
<?php if ($applications === []) : ?>
    <p class="text-muted">No applications on record.</p>
<?php else : ?>
    <ul class="member-applications">
        <?php foreach ($applications as $application) : ?>
            <?php
            $applicationId = (int) $application['application_id'];
            $url = $isOwn ? route('my_applications.show', ['id' => $applicationId]) : route('applications.show', ['id' => $applicationId]);
            ?>
            <li>
                <a href="<?= e($url) ?>"><?= e($application['vacancy_title'] ?? 'General Membership') ?></a>
                <?= status_badge($application['status']) ?>
                <?php if ($application['submitted_at'] !== null) : ?>
                    <span class="text-muted"><?= e(date_ph($application['submitted_at'])) ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php endif; ?>
