<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $member
 * @var string $roleLabel
 * @var list<array<string, mixed>> $history
 * @var list<array<string, mixed>> $applications
 * @var list<array<string, mixed>> $credentials
 * @var list<array<string, mixed>> $activeCredentials
 * @var array{tone: string, message: string}|null $notice
 * @var list<array{committee_id: int, name: string, joined_at: string}> $extraCommittees
 * @var list<array{committee_id: int, name: string}> $committeeOptions
 */
$name = $member['first_name'] . ' ' . $member['last_name'];
$reasonError = field_error('reason');
?>
<?php $this->start('title') ?><?= e($name) ?><?php $this->end() ?>
<?php $this->start('page_title') ?>Member record<?php $this->end() ?>

<?php
$approvedCredentials = array_values(array_filter($activeCredentials, static fn (array $c): bool => $c['status'] === 'approved'));
$facts = [
    ['Email', e($member['email'])],
    ['Student number', e($member['student_no'] ?? 'Not set')],
    ['Member number', '<code>' . e($member['member_no']) . '</code>'],
    ['Membership type', e(ucfirst($member['membership_type']))],
    ['Joined', e(date_ph($member['joined_at']))],
    ['Account', $member['user_id'] === null ? 'Not registered yet' : 'Registered'],
];
$resend = '';
if ($member['user_id'] === null && can('admin.users')) {
    $resend = '<form method="post" action="' . e(route('members.resend_registration', ['id' => (int) $member['member_id']])) . '">'
        . csrf_field()
        . '<button type="submit" class="btn btn--secondary btn--sm">Resend registration instructions</button></form>';
}
?>
<div class="account-page">
    <?= $this->partial('partials/profile_hero', [
        'name' => $name,
        'photoUrl' => $member['photo_path'] !== null ? route('files.show', ['type' => 'avatar', 'id' => (int) $member['user_id']]) : null,
        'headingId' => 'member-record',
        'lines' => [e($roleLabel), status_badge($member['status'])],
        'facts' => $facts,
        'extra' => $resend,
    ]) ?>

    <?php if ($extraCommittees !== [] || $committeeOptions !== []) : ?>
        <section class="plain-section" aria-labelledby="member-second-committee">
            <div class="card__header"><h2 class="card__title" id="member-second-committee">Second committee</h2></div>
            <div class="card__body">
                <p class="text-muted">A Committee Member or Year-Level Representative may also be a Committee Member of one other standing committee (Article VI, Section 4-A).</p>
                <?php foreach ($extraCommittees as $extra) : ?>
                    <form class="cluster" method="post" action="<?= e(route('members.committee', ['id' => (int) $member['member_id']])) ?>" data-confirm="They stop being a member of this committee. Their main position stays." data-confirm-title="Remove the second committee?" data-confirm-action="Remove">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="committee_id" value="<?= e($extra['committee_id']) ?>">
                        <span>Committee Member, <strong><?= e($extra['name']) ?></strong> since <?= e(date_ph($extra['joined_at'])) ?></span>
                        <?php if (can('admin.settings')) : ?>
                            <label class="sr-only" for="remove-reason-<?= e($extra['committee_id']) ?>">Reason</label>
                            <input class="input" id="remove-reason-<?= e($extra['committee_id']) ?>" name="reason" maxlength="255" required placeholder="Reason">
                            <button type="submit" class="btn btn--ghost btn--sm">Remove</button>
                        <?php endif; ?>
                    </form>
                <?php endforeach; ?>
                <?php if ($committeeOptions !== []) : ?>
                    <form method="post" action="<?= e(route('members.committee', ['id' => (int) $member['member_id']])) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add">
                        <div class="grid grid--2">
                            <div class="field">
                                <label class="field__label" for="committee_id">Add a second committee</label>
                                <select class="select" id="committee_id" name="committee_id" required>
                                    <option value="">Choose a committee</option>
                                    <?php foreach ($committeeOptions as $option) : ?>
                                        <option value="<?= e($option['committee_id']) ?>"><?= e($option['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?= $this->partial('partials/form_field', ['name' => 'reason', 'label' => 'Reason', 'value' => '', 'help' => 'For example, the approved application.']) ?>
                        </div>
                        <button type="submit" class="btn btn--secondary">Add as Committee Member</button>
                    </form>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>


    <?php // Only the MSW Committee Head may open these files (FileAccessPolicy::credential). ?>
    <?php if ($approvedCredentials !== [] && can('credential.approve')) : ?>
        <section class="card" aria-labelledby="member-current-credentials">
            <div class="card__body">
                <h2 class="member-section" id="member-current-credentials">Current ID and certificate</h2>
                <?= $this->partial('members/_credential_cards', ['credentials' => $approvedCredentials, 'forOwner' => false, 'headingLevel' => 3]) ?>
            </div>
        </section>
    <?php endif; ?>

<div class="account-columns">
    <section class="card" aria-labelledby="member-history">
        <div class="card__body">
            <h2 class="sr-only" id="member-history">History</h2>
            <?= $this->partial('members/_profile', [
                'member' => $member,
                'roleLabel' => $roleLabel,
                'history' => $history,
                'applications' => $applications,
                'activeCredentials' => $activeCredentials,
                'withDownloads' => false,
                'notice' => $notice,
                'headingLevel' => 3,
                'parts' => ['history', 'applications'],
            ]) ?>
        </div>
    </section>

    <section class="plain-section" aria-labelledby="member-credentials">
        <div class="card__header"><h2 class="card__title" id="member-credentials">Credentials history</h2></div>
        <div class="card__body">
            <?php if ($credentials === []) : ?>
                <p class="text-muted">No credentials have been issued.</p>
            <?php else : ?>
                <div class="table-wrap">
                    <table class="table table--stack">
                        <caption class="sr-only">Credentials issued to this member</caption>
                        <thead>
                            <tr><th scope="col">Type</th><th scope="col">Serial number</th><th scope="col">Issued</th><th scope="col">Status</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($credentials as $credential) : ?>
                                <tr>
                                    <td data-label="Type"><?= e(\App\Services\CredentialService::TYPES[$credential['type']]) ?></td>
                                    <td data-label="Serial number"><code><?= e($credential['serial_no']) ?></code></td>
                                    <td data-label="Issued"><?= e(datetime_ph($credential['issued_at'])) ?></td>
                                    <td data-label="Status">
                                        <?= status_badge($credential['revoked_at'] !== null ? 'revoked' : ($credential['status'] === 'approved' ? 'valid' : $credential['status'])) ?>
                                        <?php if ($credential['revoked_at'] !== null) : ?>
                                            <span class="text-muted"><?= e(date_ph($credential['revoked_at'])) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (can('credential.reissue') && !\App\Services\CredentialService::manual()) : ?>
                <form class="member-reissue" method="post" action="<?= e(route('members.reissue', ['id' => (int) $member['member_id']])) ?>">
                    <?= csrf_field() ?>
                    <div class="field <?= $reasonError !== null ? 'field--error' : '' ?>">
                        <label class="field__label" for="reason">Reason for issuing new credentials <span class="field__required" aria-hidden="true">*</span></label>
                        <textarea class="textarea applications-actions__remarks" id="reason" name="reason" maxlength="255" required<?= $reasonError !== null ? ' aria-describedby="reason-error"' : '' ?>></textarea>
                        <?php if ($reasonError !== null) : ?>
                            <p class="field__error" id="reason-error"><?= icon('alert') ?> <?= e($reasonError) ?></p>
                        <?php endif; ?>
                    </div>
                    <button
                        type="submit"
                        class="btn btn--primary"
                        data-confirm="Any current ID and certificate stop being valid, and their QR codes will show Revoked. New ones are created right away."
                        data-confirm-title="Issue new credentials?"
                        data-confirm-action="Issue new credentials"
                        data-confirm-tone="primary"
                    >Issue new credentials</button>
                </form>
            <?php endif; ?>
        </div>
    </section>
</div>
</div>
