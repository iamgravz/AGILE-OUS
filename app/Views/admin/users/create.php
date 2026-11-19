<?php
/**
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $roles
 */
$roleError = field_error('role');
?>
<?php $this->start('title') ?>Create officer account<?php $this->end() ?>
<?php $this->start('form_side') ?><?= $this->partial('partials/side_panel', ['blocks' => [['title' => 'About officer accounts', 'items' => ['The account is active at once.', 'We email a link to set the password. It works for 72 hours.', 'Applicants do not need this page: they register after approval.']], ['title' => 'Committee heads', 'text' => 'Create the account first, then set them as head under Committees and positions.']]]) ?><?php $this->end() ?>
<?php $this->start('page_title') ?>Create officer account<?php $this->end() ?>

<form class="admin-form" method="post" action="<?= e(route('admin.users.create')) ?>">
    <?= csrf_field() ?>
    <p class="text-muted">Applicants do not need this page: they register themselves after approval. The account is active at once, and we email a link to set the password.</p>
    <div class="grid grid--2">
        <?= $this->partial('partials/form_field', ['name' => 'first_name', 'label' => 'First name', 'value' => (string) old('first_name', '')]) ?>
        <?= $this->partial('partials/form_field', ['name' => 'last_name', 'label' => 'Last name', 'value' => (string) old('last_name', '')]) ?>
    </div>
    <?= $this->partial('partials/form_field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => (string) old('email', '')]) ?>
    <?= $this->partial('partials/form_field', ['name' => 'student_no', 'label' => 'PUP student number', 'value' => (string) old('student_no', ''), 'required' => false, 'help' => 'Optional. For example, 2024-00101-OU-0.']) ?>
    <div class="field <?= $roleError !== null ? 'field--error' : '' ?>">
        <label class="field__label" for="role">Role <span class="field__required" aria-hidden="true">*</span></label>
        <select class="select" id="role" name="role" required>
            <option value="">Choose a role</option>
            <?php foreach ($roles as $role) : ?>
                <option value="<?= e($role['slug']) ?>"<?= old('role') === $role['slug'] ? ' selected' : '' ?>><?= e($role['role_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <p class="field__help">To make someone a committee head, create the account, then set them as head under Committees and positions.</p>
        <?php if ($roleError !== null) : ?><p class="field__error"><?= icon('alert') ?> <?= e($roleError) ?></p><?php endif; ?>
    </div>
    <button type="submit" class="btn btn--primary">Create account and email the link</button>
</form>
