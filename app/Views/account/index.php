<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $account
 * @var array<string, mixed>|null $membership
 */
$tabs = $membership === null ? ['details' => 'Details'] : ['details' => 'Details', 'history' => 'Membership history', 'applications' => 'Applications'];
$memberPart = static fn (array $parts): array => $membership + ['withDownloads' => true, 'headingLevel' => 3, 'parts' => $parts];
?>
<?php $this->start('title') ?>My account<?php $this->end() ?>
<?php $this->start('page_title') ?>My account<?php $this->end() ?>
<div class="profile-layout">
    <div class="profile-layout__side detail-stack">
    <section class="card" aria-labelledby="account-details">
        <div class="card__header"><h2 class="card__title" id="account-details">Account details</h2></div>
        <div class="card__body">
            <dl class="detail-list">
                <div><dt>Name</dt><dd><?= e($account['first_name'] . ' ' . $account['last_name']) ?></dd></div>
                <div><dt>Email</dt><dd><?= e($account['email']) ?></dd></div>
                <div><dt>Student number</dt><dd><?= e($account['student_no'] ?? 'Not set') ?></dd></div>
                <div><dt>Role</dt><dd><?= e($account['role_name']) ?></dd></div>
                <?php if (!empty($account['last_login_at'])) : ?>
                    <div><dt>Last login</dt><dd><?= e(datetime_ph($account['last_login_at'])) ?></dd></div>
                <?php endif; ?>
            </dl>
            <p class="field__help">Contact the MSW Committee to correct your name, email or student number.</p>
        </div>
    </section>
        <?php if ($membership !== null) : ?>
            <section class="card" id="membership" aria-labelledby="account-member">
                <div class="card__header"><h2 class="card__title" id="account-member">Membership</h2></div>
                <div class="card__body">
                    <?= $this->partial('members/_profile', $memberPart(['summary', 'credentials'])) ?>
                </div>
            </section>
        <?php endif; ?>
    </div>

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
            <?php if ($account['photo_path'] !== null) : ?>
                <img class="avatar avatar--large" src="<?= e(route('files.show', ['type' => 'avatar', 'id' => $account['user_id']])) ?>" alt="Your current profile photo" width="120" height="120">
            <?php endif; ?>
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
        <div class="card__header"><h2 class="card__title" id="account-contact">Contact number</h2></div>
        <div class="card__body">
            <form method="post" action="<?= e(route('account.contact')) ?>">
                <?= csrf_field() ?>
                <?= $this->partial('partials/form_field', ['name' => 'contact_no', 'label' => 'Contact number', 'type' => 'tel', 'value' => old('contact_no', $account['contact_no']), 'autocomplete' => 'tel']) ?>
                <button type="submit" class="btn btn--primary">Save contact number</button>
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
