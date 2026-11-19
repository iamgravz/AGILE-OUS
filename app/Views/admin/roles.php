<?php
/**
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $roles
 * @var list<array<string, mixed>> $permissions
 * @var array<int, list<string>> $granted
 * @var list<string> $locked
 */
?>
<?php $this->start('title') ?>Roles and permissions<?php $this->end() ?>
<?php $this->start('page_title') ?>Roles and permissions<?php $this->end() ?>

<p class="text-muted">Gray boxes are the MSW Committee Head's admin permissions, which always stay on. Hover over a permission to read what it allows. Every change goes in the audit log with before and after.</p>
<form method="post" action="<?= e(route('admin.roles')) ?>" data-confirm="This changes what everyone with these roles can see and do right away." data-confirm-title="Save these permissions?" data-confirm-action="Save permissions">
    <?= csrf_field() ?>
    <div class="table-wrap matrix-wrap">
        <table class="table matrix">
            <caption class="sr-only">Permissions for each role</caption>
            <thead>
                <tr>
                    <th scope="col">Permission</th>
                    <?php foreach ($roles as $role) : ?>
                        <th scope="col" class="matrix__role"><?= e($role['role_name']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($permissions as $permission) : ?>
                    <tr>
                        <th scope="row" title="<?= e($permission['description'] ?? '') ?>"><code><?= e($permission['code']) ?></code></th>
                        <?php foreach ($roles as $role) : ?>
                            <?php
                            $roleId = (int) $role['role_id'];
                            $on = in_array($permission['code'], $granted[$roleId], true);
                            $isLocked = $role['slug'] === 'msw_head' && in_array($permission['code'], $locked, true);
                            ?>
                            <td class="matrix__cell">
                                <input type="checkbox" name="granted[<?= e($roleId) ?>][]" value="<?= e($permission['code']) ?>" aria-label="<?= e($permission['code'] . ' for ' . $role['role_name']) ?>"<?= $on ? ' checked' : '' ?><?= $isLocked ? ' disabled' : '' ?>>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="admin-actions"><button type="submit" class="btn btn--primary">Save permissions</button></p>
</form>
