<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $account
 * @var array<string, mixed>|null $membership
 */
$tabs = $membership === null ? ['details' => 'Details'] : ['details' => 'Details', 'history' => 'Membership history', 'applications' => 'Applications'];
$memberPart = static fn (array $parts): array => $membership + ['withDownloads' => true, 'headingLevel' => 2, 'parts' => $parts];
?>
<?php $this->start('title') ?>My account<?php $this->end() ?>
<?php $this->start('page_title') ?>My account<?php $this->end() ?>
<?php
$fullName = trim($account['first_name'] . ' ' . $account['last_name']);
$member = $membership['member'] ?? null;
?>
<div class="account-page">
    <?php
    $facts = [
        ['Email', e($account['email'])],
        ['Student number', e($account['student_no'] ?? 'Not set')],
        ['Role', e($account['role_name'])],
    ];
    if ($member !== null) {
        $facts[] = ['Member number', '<code>' . e($member['member_no']) . '</code>'];
        $facts[] = ['Joined', e(date_ph($member['joined_at']))];
    }
    if (!empty($account['last_login_at'])) {
        $facts[] = ['Last login', e(datetime_ph($account['last_login_at']))];
    }
    ?>
    <?= $this->partial('partials/profile_hero', [
        'name' => $fullName,
        'photoUrl' => $account['photo_path'] !== null ? route('files.show', ['type' => 'avatar', 'id' => $account['user_id']]) : null,
        'headingId' => 'account-details',
        'lines' => array_filter([e($membership['roleLabel'] ?? $account['role_name']), $member !== null ? status_badge($member['status']) : null]),
        'facts' => $facts,
        'note' => 'Contact the MSW Committee to correct your name, email or student number.',
    ]) ?>

    <?php if ($membership !== null) : ?>
        <section class="card" id="membership" aria-label="Digital ID and certificate">
            <div class="card__body">
                <?= $this->partial('members/_profile', $memberPart(['credentials'])) ?>
            </div>
        </section>
        <?php if (\App\Services\CredentialService::manual()) : ?>
            <?= $this->partial('account/_credential_requests', ['requests' => $credentialRequests]) ?>
        <?php endif; ?>
    <?php endif; ?>

    <div data-tabs>
        <div class="tabs__list" role="tablist" aria-label="Account sections">
            <?php foreach ($tabs as $key => $label) : ?>
                <button type="button" class="tabs__tab" role="tab" id="tab-<?= e($key) ?>" aria-selected="<?= $key === 'details' ? 'true' : 'false' ?>" aria-controls="panel-<?= e($key) ?>"<?= $key === 'details' ? '' : ' tabindex="-1"' ?>><?= e($label) ?></button>
            <?php endforeach; ?>
        </div>
        <div class="tabs__panel" role="tabpanel" id="panel-details" aria-labelledby="tab-details">
            <div class="profile-forms">
    <section class="plain-section" aria-labelledby="account-photo">
        <div class="card__header"><h2 class="card__title" id="account-photo">Profile photo</h2></div>
        <div class="card__body">
            <form method="post" action="<?= e(route('account.photo')) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <?= $this->partial('partials/upload_field', [
                    'name' => 'photo',
                    'label' => 'New photo',
                    'types' => ['jpg', 'jpeg', 'png'],
                    'maxMb' => 5,
                    'help' => 'JPG or PNG, up to 5 MB. Your photo is cropped to a square.',
                ]) ?>
                <button type="submit" class="btn btn--primary">Upload photo</button>
            </form>
        </div>
    </section>

    <section class="plain-section" aria-labelledby="account-contact">
        <div class="card__header"><h2 class="card__title" id="account-contact">Year, section and contact number</h2></div>
        <div class="card__body">
            <form method="post" action="<?= e(route('account.contact')) ?>">
                <?= csrf_field() ?>
                <?= $this->partial('partials/student_details', ['values' => ['year_level' => $account['year_level'] ?? '', 'section' => $account['section'] ?? '', 'contact_no' => $account['contact_no']]]) ?>
                <button type="submit" class="btn btn--primary">Save</button>
            </form>
        </div>
    </section>

    <section class="plain-section" aria-labelledby="account-password">
        <div class="card__header"><h2 class="card__title" id="account-password">Change password</h2></div>
        <div class="card__body">
            <form method="post" action="<?= e(route('account.password')) ?>">
                <?= csrf_field() ?>
                <?= $this->partial('partials/form_field', ['name' => 'current_password', 'label' => 'Current password', 'type' => 'password', 'autocomplete' => 'current-password']) ?>
                <?= $this->partial('partials/form_field', ['name' => 'new_password', 'label' => 'New password', 'type' => 'password', 'autocomplete' => 'new-password', 'help' => 'At least 8 characters, with letters and numbers.']) ?>
                <?= $this->partial('partials/form_field', ['name' => 'new_password_confirmation', 'label' => 'Confirm new password', 'type' => 'password', 'autocomplete' => 'new-password']) ?>
                <button type="submit" class="btn btn--primary">Change password</button>
            </form>
        </div>
    </section>
            </div>
        </div>
        <?php if ($membership !== null) : ?>
            <div class="tabs__panel" role="tabpanel" id="panel-history" aria-labelledby="tab-history" hidden>
                <?= $this->partial('members/_profile', $memberPart(['history'])) ?>
            </div>
            <div class="tabs__panel" role="tabpanel" id="panel-applications" aria-labelledby="tab-applications" hidden>
                <?= $this->partial('members/_profile', $memberPart(['applications'])) ?>
            </div>
        <?php endif; ?>
    </div>
</div>
