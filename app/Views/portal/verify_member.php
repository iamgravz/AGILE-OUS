<?php
/**
 * What a member's QR code opens: their current ID and whether they are an active AGILE member.
 *
 * @var \App\Core\View $this
 * @var array{name: string, member_no: string, active: bool, role: string, committees: list<string>, joined_at: string, id: array{credential_id: int, serial_no: string, issued_at: string, is_image: bool}|null}|null $result (optional)
 * @var string $token (optional)
 * @var bool $blocked (optional)
 */
$result ??= null;
$blocked ??= false;
$token ??= '';
$idUrl = route('verify.member_id', ['token' => $token]);
?>
<?php $this->start('title') ?>Verify an AGILE member<?php $this->end() ?>
<?php $this->start('description') ?>Check an AGILE member's current ID and membership status.<?php $this->end() ?>
<?php $this->start('head') ?>
<link rel="stylesheet" href="<?= e(asset('css/robot.css')) ?>">
<?php $this->end() ?>
<div class="form-layout">
<div class="track-page form-layout__main robot-stage">
    <h1>Verify an AGILE member</h1>

    <?php if ($blocked) : ?>
        <div class="alert alert--warning" role="alert">Too many checks. Please wait 15 minutes, then try again.</div>
    <?php elseif ($result === null) : ?>
        <div class="alert alert--danger" role="alert">We could not find this member. The QR code may be wrong or no longer in use.</div>
    <?php else : ?>
        <div class="card track-result">
            <div class="card__body">
                <p><?= status_badge($result['active'] ? 'active' : 'inactive') ?> <strong><?= $result['active'] ? 'Active AGILE member' : 'Not an active member' ?></strong></p>
                <dl class="detail-list">
                    <div><dt>Name</dt><dd><?= e($result['name']) ?></dd></div>
                    <div><dt>Member number</dt><dd><code><?= e($result['member_no']) ?></code></dd></div>
                    <div><dt>Current role</dt><dd><?= e($result['role']) ?></dd></div>
                    <?php if ($result['committees'] !== []) : ?>
                        <div><dt>Also in</dt><dd><?= e(implode(', ', array_map(static fn (string $name): string => $name . ' Committee', $result['committees']))) ?></dd></div>
                    <?php endif; ?>
                    <div><dt>Member since</dt><dd><?= e(date_ph($result['joined_at'])) ?></dd></div>
                    <?php if ($result['id'] !== null) : ?>
                        <div><dt>Current ID</dt><dd><code><?= e($result['id']['serial_no']) ?></code>, issued <?= e(date_ph($result['id']['issued_at'])) ?></dd></div>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <?php if ($result['id'] === null) : ?>
            <p class="text-muted">No current digital ID is on file. An ID shown with this QR code should not be accepted until the MSW Committee issues a new one.</p>
        <?php elseif ($result['id']['is_image']) : ?>
            <figure class="verify-id">
                <img src="<?= e($idUrl) ?>" alt="Current AGILE digital ID of <?= e($result['name']) ?>" loading="lazy">
                <figcaption class="text-muted">The current ID. Compare it with the one shown to you.</figcaption>
            </figure>
        <?php else : ?>
            <p><a class="btn btn--secondary" href="<?= e($idUrl) ?>" target="_blank" rel="noopener">Open the current ID</a></p>
            <p class="text-muted">Compare it with the one shown to you.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>
</div>
