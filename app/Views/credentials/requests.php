<?php
/**
 * ID and certificate requests: upload the file made from AGILE's template, or decline with the reason.
 *
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $rows
 * @var string $status
 * @var int $maxMb
 */
use App\Services\CredentialRequestService;
use App\Services\CredentialService;

$tones = ['requested' => 'info', 'issued' => 'success', 'declined' => 'danger', 'cancelled' => 'neutral'];
$tabs = ['requested' => 'Waiting', 'issued' => 'Ready', 'declined' => 'Declined', 'all' => 'All'];
$accept = implode(',', array_map(static fn (string $type): string => '.' . $type, config('credentials.upload_types')));
?>
<?php $this->start('title') ?>ID and certificate requests<?php $this->end() ?>
<?php $this->start('page_title') ?>ID and certificate requests<?php $this->end() ?>

<p class="text-muted">Members ask for their digital ID or certificate of membership from My Account. Make it from the AGILE template, then upload it here as PDF, JPG or PNG (up to <?= e($maxMb) ?> MB). It replaces any earlier one of the same kind, and the member is told it is ready.</p>
<p class="text-muted">For a digital ID, first download the member's QR code and put it on the template. Each member keeps the same QR code: scanning it always shows their current ID and that they are an active member, so a new ID never needs a new code.</p>

<nav class="cluster" aria-label="Filter requests">
    <?php foreach ($tabs as $key => $label) : ?>
        <a class="btn btn--<?= $status === $key ? 'primary' : 'ghost' ?> btn--sm" href="<?= e(route('credentials.requests')) ?>?status=<?= e($key) ?>"<?= $status === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($rows === []) : ?>
    <p class="text-muted">No requests here.</p>
<?php else : ?>
    <div class="table-wrap">
        <table class="table table--stack">
            <caption class="sr-only">ID and certificate requests</caption>
            <thead>
                <tr><th scope="col">Member</th><th scope="col">Asked for</th><th scope="col">Requested</th><th scope="col">Status</th><th scope="col">Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) : ?>
                    <?php $id = (int) $row['request_id']; ?>
                    <tr>
                        <td data-label="Member">
                            <a href="<?= e(route('members.show', ['id' => (int) $row['member_id']])) ?>"><strong><?= e($row['first_name'] . ' ' . $row['last_name']) ?></strong></a><br>
                            <span class="text-muted"><code><?= e($row['member_no']) ?></code>, <?= e($row['position_title'] !== null ? $row['position_title'] . ($row['committee_name'] !== null ? ', ' . $row['committee_name'] : '') : 'General Member') ?></span>
                        </td>
                        <td data-label="Asked for">
                            <?= e(CredentialService::TYPES[$row['type']]) ?>
                            <?php if ($row['note'] !== null) : ?><br><span class="text-muted">"<?= e($row['note']) ?>"</span><?php endif; ?>
                            <?php if ($row['photo_path'] !== null) : ?>
                                <a class="credential-request__photo" href="<?= e(route('credentials.requests.photo', ['id' => $id])) ?>?inline=1" target="_blank" rel="noopener">
                                    <img src="<?= e(route('credentials.requests.photo', ['id' => $id])) ?>?inline=1" alt="Picture sent by <?= e($row['first_name'] . ' ' . $row['last_name']) ?>" loading="lazy">
                                </a>
                                <a class="btn btn--ghost btn--sm" href="<?= e(route('credentials.requests.photo', ['id' => $id])) ?>">Download picture</a>
                            <?php endif; ?>
                        </td>
                        <td data-label="Requested"><?= e(datetime_ph($row['created_at'])) ?></td>
                        <td data-label="Status">
                            <span class="badge badge--<?= e($tones[$row['status']]) ?>"><?= e(CredentialRequestService::STATUSES[$row['status']]) ?></span>
                            <?php if ($row['remarks'] !== null) : ?><br><span class="text-muted"><?= e($row['remarks']) ?></span><?php endif; ?>
                        </td>
                        <td data-label="Action">
                            <?php if ($row['status'] === 'requested') : ?>
                                <?php if ($row['type'] === 'digital_id') : ?>
                                    <p><a class="btn btn--secondary btn--sm" href="<?= e(route('credentials.qr', ['memberId' => (int) $row['member_id']])) ?>">Download their QR code</a></p>
                                <?php endif; ?>
                                <form method="post" action="<?= e(route('credentials.requests.upload', ['id' => $id])) ?>" enctype="multipart/form-data">
                                    <?= csrf_field() ?>
                                    <label class="field__label" for="file-<?= e($id) ?>">File from the template</label>
                                    <input class="input" id="file-<?= e($id) ?>" type="file" name="file" accept="<?= e($accept) ?>" required>
                                    <button type="submit" class="btn btn--primary btn--sm">Upload</button>
                                </form>
                                <form class="cluster" method="post" action="<?= e(route('credentials.requests.decline', ['id' => $id])) ?>">
                                    <?= csrf_field() ?>
                                    <label class="sr-only" for="remarks-<?= e($id) ?>">Reason for declining</label>
                                    <input class="input" id="remarks-<?= e($id) ?>" name="remarks" maxlength="255" required placeholder="Reason, to decline">
                                    <button type="submit" class="btn btn--ghost btn--sm">Decline</button>
                                </form>
                            <?php elseif ($row['credential_id'] !== null) : ?>
                                <a class="btn btn--secondary btn--sm" href="<?= e(route('files.show', ['type' => 'credential', 'id' => (int) $row['credential_id']])) ?>?inline=1" target="_blank" rel="noopener">View upload</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
