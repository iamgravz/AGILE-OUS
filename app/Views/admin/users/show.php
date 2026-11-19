<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $user
 * @var array<string, mixed>|null $member
 * @var bool $isLocked
 * @var list<array<string, mixed>> $permissions
 * @var list<string> $fromRole
 * @var list<string> $extra
 * @var list<array<string, mixed>> $roles
 */
$id = (int) $user['user_id'];
$name = $user['first_name'] . ' ' . $user['last_name'];
$groups = [];

foreach ($permissions as $permission) {
    $groups[ucfirst(str_replace('_', ' ', explode('.', $permission['code'])[0]))][] = $permission;
}
?>
<?php $this->start('title') ?><?= e($name) ?><?php $this->end() ?>
<?php $this->start('page_eyebrow') ?>Administration<?php $this->end() ?>
<?php $this->start('page_title') ?>User account<?php $this->end() ?>

<?php
$facts = [
    ['Email', e($user['email'])],
    ['Student number', $user['student_no'] !== null ? '<code>' . e($user['student_no']) . '</code>' : 'Not set'],
    ['Role', e($user['role_name'])],
    ['Member number', $member !== null ? '<a href="' . e(route('members.show', ['id' => (int) $member['member_id']])) . '"><code>' . e($member['member_no']) . '</code></a>' : 'No member record'],
    ['Last login', $user['last_login_at'] === null ? 'Never' : e(datetime_ph($user['last_login_at']))],
];
if ($isLocked) {
    $facts[] = ['Locked until', e(datetime_ph($user['locked_until']))];
}
?>
<div class="account-page">
    <?= $this->partial('partials/profile_hero', [
        'name' => $name,
        'photoUrl' => ($user['photo_path'] ?? null) !== null ? route('files.show', ['type' => 'avatar', 'id' => $id]) : null,
        'headingId' => 'user-details',
        'lines' => [e($user['role_name']), status_badge($isLocked ? 'locked' : $user['status'])],
        'facts' => $facts,
    ]) ?>

<div class="account-forms">
    <section class="plain-section" aria-labelledby="user-role">
        <div class="card__header"><h2 class="card__title" id="user-role">Role</h2></div>
        <div class="card__body">
            <form method="post" action="<?= e(route('admin.users.role', ['id' => $id])) ?>" data-confirm="This changes what they can see and do right away. Their position does not change: to change a position, use Committees and positions or an appointment." data-confirm-title="Change this role?" data-confirm-action="Change role">
                <?= csrf_field() ?>
                <div class="grid grid--2">
                    <div class="field">
                        <label class="field__label" for="role">New role</label>
                        <select class="select" id="role" name="role" required>
                            <?php foreach ($roles as $role) : ?>
                                <option value="<?= e($role['slug']) ?>"<?= $role['slug'] === $user['role_slug'] ? ' selected' : '' ?>><?= e($role['role_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field__label" for="role-reason">Reason for the change</label>
                        <input class="input" id="role-reason" name="reason" maxlength="255" required>
                        <p class="field__help">Saved in the audit log.</p>
                    </div>
                </div>
                <button type="submit" class="btn btn--primary">Change role</button>
            </form>
        </div>
    </section>

    <section class="plain-section" aria-labelledby="user-access">
        <div class="card__header"><h2 class="card__title" id="user-access">Login access</h2></div>
        <div class="card__body">
            <?php if ($isLocked || $user['status'] === 'active') : ?>
                <div class="cluster">
                    <?php if ($isLocked) : ?>
                        <form method="post" action="<?= e(route('admin.users.unlock', ['id' => $id])) ?>"><?= csrf_field() ?><button type="submit" class="btn btn--secondary">Unlock account</button></form>
                    <?php endif; ?>
                    <?php if ($user['status'] === 'active') : ?>
                        <form method="post" action="<?= e(route('admin.users.reset_password', ['id' => $id])) ?>" data-confirm="Their current password stops working now, and we email them a link to set a new one." data-confirm-title="Force a password reset?" data-confirm-action="Force reset"><?= csrf_field() ?><button type="submit" class="btn btn--secondary">Force password reset</button></form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($user['status'] === 'active' || $user['status'] === 'disabled') : ?>
                <?php $enable = $user['status'] === 'disabled'; ?>
                <form class="<?= $isLocked || !$enable ? 'admin-send' : '' ?>" method="post" action="<?= e(route('admin.users.status', ['id' => $id])) ?>" data-confirm="<?= $enable ? 'They can log in again.' : 'They are logged out and cannot log in until the account is enabled.' ?>" data-confirm-title="<?= $enable ? 'Enable this account?' : 'Disable this account?' ?>" data-confirm-action="<?= $enable ? 'Enable account' : 'Disable account' ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $enable ? 'enable' : 'disable' ?>">
                    <div class="field">
                        <label class="field__label" for="status-reason">Reason for <?= $enable ? 'enabling' : 'disabling' ?></label>
                        <input class="input" id="status-reason" name="reason" maxlength="255" required>
                        <p class="field__help">Saved in the audit log.</p>
                    </div>
                    <button type="submit" class="btn <?= $enable ? 'btn--secondary' : 'btn--danger' ?>"><?= $enable ? 'Enable account' : 'Disable account' ?></button>
                </form>
            <?php else : ?>
                <p class="text-muted">This account is waiting for approval. <a href="<?= e(route('accounts.approvals')) ?>">Decide it on New accounts for approval.</a></p>
            <?php endif; ?>
        </div>
    </section>

    <section class="plain-section" aria-labelledby="user-extra">
        <div class="card__header"><h2 class="card__title" id="user-extra">Extra permissions</h2></div>
        <div class="card__body">
            <?php if ($extra === []) : ?>
                <p class="text-muted">None. This person has only what the role gives.</p>
            <?php else : ?>
                <p><?= e(count($extra)) ?> on top of the role:
                    <?php foreach ($extra as $code) : ?><code><?= e($code) ?></code> <?php endforeach; ?>
                </p>
            <?php endif; ?>
            <details class="admin-more">
                <summary>Change extra permissions</summary>
                <p class="text-muted">A box that is on and cannot be changed comes from the role.</p>
                <form method="post" action="<?= e(route('admin.users.permissions', ['id' => $id])) ?>">
                    <?= csrf_field() ?>
                    <?php foreach ($groups as $group => $items) : ?>
                        <fieldset class="permission-group">
                            <legend><?= e($group) ?></legend>
                            <div class="admin-permissions">
                                <?php foreach ($items as $permission) : ?>
                                    <?php $fromThisRole = in_array($permission['code'], $fromRole, true); ?>
                                    <label class="check">
                                        <input type="checkbox" name="permissions[]" value="<?= e($permission['code']) ?>"<?= $fromThisRole || in_array($permission['code'], $extra, true) ? ' checked' : '' ?><?= $fromThisRole ? ' disabled' : '' ?>>
                                        <span><code><?= e($permission['code']) ?></code><?php if (($permission['description'] ?? '') !== '') : ?><span class="permission-group__text"><?= e($permission['description']) ?></span><?php endif; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn--secondary">Save extra permissions</button>
                </form>
            </details>
        </div>
    </section>
</div>
</div>
