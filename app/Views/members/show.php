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
 */
$name = $member['first_name'] . ' ' . $member['last_name'];
$reasonError = field_error('reason');
?>
<?php $this->start('title') ?><?= e($name) ?><?php $this->end() ?>
<?php $this->start('page_title') ?><?= e($name) ?><?php $this->end() ?>

<div class="detail-layout">
    <section class="card detail-layout__side" aria-labelledby="member-record">
        <div class="card__header"><h2 class="card__title" id="member-record">Member record</h2></div>
        <div class="card__body">
            <?php if ($member['photo_path'] !== null) : ?>
                <img class="avatar avatar--large" src="<?= e(route('files.show', ['type' => 'avatar', 'id' => (int) $member['user_id']])) ?>" alt="Photo of <?= e($name) ?>" width="120" height="120">
            <?php endif; ?>
            <dl class="detail-list member-details">
                <div><dt>Email</dt><dd><?= e($member['email']) ?></dd></div>
                <div><dt>Student number</dt><dd><?= e($member['student_no'] ?? 'Not set') ?></dd></div>
                <div><dt>Membership type</dt><dd><?= e(ucfirst($member['membership_type'])) ?></dd></div>
                <div><dt>Account</dt><dd><?= $member['user_id'] === null ? 'Not registered yet' : 'Registered' ?></dd></div>
            </dl>
            <?php if ($member['user_id'] === null && can('admin.users')) : ?>
                <form method="post" action="<?= e(route('members.resend_registration', ['id' => (int) $member['member_id']])) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--secondary btn--sm">Resend registration instructions</button>
                </form>
            <?php endif; ?>
            <?= $this->partial('members/_profile', [
                'member' => $member,
                'roleLabel' => $roleLabel,
                'history' => $history,
                'applications' => $applications,
                'activeCredentials' => $activeCredentials,
                'withDownloads' => false,
                'notice' => $notice,
                'headingLevel' => 3,
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

            <?php if (can('credential.reissue')) : ?>
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
