<?php
/**
 * @var \App\Core\View $this
 * @var array{valid: bool, state: string, revoked_at: ?string, name?: string, member_no?: string, role?: string, issued_at?: string}|null $result (optional)
 * @var bool $blocked (optional)
 */
$result ??= null;
$blocked ??= false;
?>
<?php $this->start('title') ?>Verify a credential<?php $this->end() ?>
<?php $this->start('form_side') ?><?= $this->partial('partials/side_panel', ['blocks' => [['title' => 'What this page shows', 'items' => ['Whether a digital ID or certificate is valid', 'The name, member number and role on it', 'When it was issued or revoked']], ['title' => 'Something looks wrong?', 'text' => 'Ask the MSW Committee to confirm it.', 'contact' => true]]]) ?><?php $this->end() ?>
<?php $this->start('description') ?>Check whether an AGILE digital ID or certificate is valid.<?php $this->end() ?>
<div class="track-page">
    <h1>Verify a credential</h1>

    <?php if ($blocked) : ?>
        <div class="alert alert--warning" role="alert">Too many checks. Please wait 15 minutes, then try again.</div>
    <?php elseif ($result === null) : ?>
        <div class="alert alert--danger" role="alert">We could not find this credential. Please scan the QR code again.</div>
    <?php elseif ($result['state'] === \App\Services\CredentialService::STATE_NOT_ISSUED) : ?>
        <p><?= status_badge('not_yet_issued') ?></p>
        <p>This credential has not been issued yet, so it should not be accepted.</p>
    <?php else : ?>
        <?php if ($result['state'] === \App\Services\CredentialService::STATE_REVOKED) : ?>
            <p><strong>Revoked on <?= e(date_ph($result['revoked_at'])) ?></strong></p>
        <?php endif; ?>
        <div class="card track-result">
            <div class="card__body">
                <dl class="detail-list">
                    <div><dt>Credential status</dt><dd><?= status_badge($result['valid'] ? 'valid' : 'revoked') ?></dd></div>
                    <div><dt>Member name</dt><dd><?= e($result['name']) ?></dd></div>
                    <div><dt>Member number</dt><dd><code><?= e($result['member_no']) ?></code></dd></div>
                    <div><dt>Current role</dt><dd><?= e($result['role']) ?></dd></div>
                    <div><dt>Issue date</dt><dd><?= e(date_ph($result['issued_at'])) ?></dd></div>
                </dl>
            </div>
        </div>
        <?php if (!$result['valid']) : ?>
            <p class="text-muted">This copy was replaced and should no longer be accepted. The person named here may hold a newer one.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>
