<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $result
 * @var array<string, string> $filters
 * @var list<array<string, mixed>> $roles
 */
?>
<?php $this->start('title') ?>Users<?php $this->end() ?>
<?php $this->start('page_title') ?>Users<?php $this->end() ?>
<?php $this->start('page_actions') ?><a class="btn btn--secondary btn--sm" href="<?= e(route('members')) ?>?registration=unregistered">Members not yet registered</a><a class="btn btn--primary btn--sm" href="<?= e(route('admin.users.create')) ?>"><?= icon('plus') ?> Create officer account</a><?php $this->end() ?>

<form class="applications-filters admin-filters" method="get" action="<?= e(route('admin.users')) ?>" data-no-guard>
    <div class="field">
        <label class="field__label" for="q">Search</label>
        <input class="input" id="q" name="q" type="search" maxlength="100" placeholder="Name, email or student number" value="<?= e($filters['q']) ?>">
    </div>
    <div class="field">
        <label class="field__label" for="role">Role</label>
        <select class="select" id="role" name="role">
            <option value="">All roles</option>
            <?php foreach ($roles as $role) : ?>
                <option value="<?= e($role['slug']) ?>"<?= $filters['role'] === $role['slug'] ? ' selected' : '' ?>><?= e($role['role_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label class="field__label" for="status">Status</label>
        <select class="select" id="status" name="status">
            <option value="">All statuses</option>
            <?php foreach (\App\Services\UserAdminService::STATUSES as $status) : ?>
                <option value="<?= e($status) ?>"<?= $filters['status'] === $status ? ' selected' : '' ?>><?= e(ucfirst($status)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="applications-filters__actions">
        <button type="submit" class="btn btn--secondary">Apply filters</button>
        <a class="btn btn--ghost" href="<?= e(route('admin.users')) ?>">Clear</a>
    </div>
</form>

<?php if ($result['data'] === []) : ?>
    <div class="empty-state"><p class="empty-state__text">No accounts match these filters.</p></div>
<?php else : ?>
    <p class="text-muted applications-count"><?= e($result['total']) ?> <?= $result['total'] === 1 ? 'account' : 'accounts' ?></p>
    <div class="table-wrap">
        <table class="table table--stack table--codes table--compact">
            <caption class="sr-only">Users</caption>
            <thead>
                <tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Student number</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col">Last login</th></tr>
            </thead>
            <tbody>
                <?php foreach ($result['data'] as $user) : ?>
                    <tr>
                        <td class="nowrap" data-label="Name"><a href="<?= e(route('admin.users.show', ['id' => (int) $user['user_id']])) ?>"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></a></td>
                        <td data-label="Email"><span class="truncate" title="<?= e($user['email']) ?>"><?= e($user['email']) ?></span></td>
                        <td<?= $user['student_no'] === null ? ' class="is-empty"' : '' ?> data-label="Student number"><?php if ($user['student_no'] !== null) : ?><code><?= e($user['student_no']) ?></code><?php endif; ?></td>
                        <td data-label="Role"><?= e($user['role_name']) ?></td>
                        <td data-label="Status"><?= status_badge($user['is_locked'] ? 'locked' : $user['status']) ?></td>
                        <td class="nowrap" data-label="Last login"><?php if ($user['last_login_at'] === null) : ?>Never<?php else : ?><span title="<?= e(datetime_ph($user['last_login_at'])) ?>"><?= e(date_ph($user['last_login_at'])) ?></span><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= $this->partial('partials/pagination', ['page' => $result['page'], 'lastPage' => $result['lastPage'], 'url' => route('admin.users'), 'query' => array_filter($filters)]) ?>
<?php endif; ?>
