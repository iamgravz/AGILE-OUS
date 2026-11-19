<?php
/**
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $accounts
 */
?>
<?php $this->start('title') ?>New accounts for approval<?php $this->end() ?>
<?php $this->start('page_title') ?>New accounts for approval<?php $this->end() ?>
<?php if ($accounts === []) : ?>
    <p>No accounts are waiting. They appear here when an approved applicant registers.</p>
<?php else : ?>
    <form id="bulk-accounts" method="post" action="<?= e(route('accounts.approve_selected')) ?>" data-confirm="Each person you ticked is emailed that they can log in." data-confirm-title="Approve the selected accounts?" data-confirm-action="Approve selected">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn--secondary">Approve selected</button>
    </form>
    <div class="table-wrap">
        <table class="table table--stack">
            <caption class="sr-only">Accounts waiting for approval</caption>
            <thead>
                <tr>
                    <th scope="col"><span class="sr-only">Select</span></th>
                    <th scope="col">Name</th>
                    <th scope="col">Student number</th>
                    <th scope="col">Email</th>
                    <th scope="col">Member number</th>
                    <th scope="col">Application</th>
                    <th scope="col">Registered</th>
                    <th scope="col">Decision</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($accounts as $account) : ?>
                    <?php $id = (int) $account['user_id']; $name = $account['first_name'] . ' ' . $account['last_name']; ?>
                    <tr>
                        <td data-label="Select">
                            <label class="sr-only" for="select-<?= e($id) ?>">Select <?= e($name) ?></label>
                            <input id="select-<?= e($id) ?>" type="checkbox" name="ids[]" value="<?= e($id) ?>" form="bulk-accounts">
                        </td>
                        <td data-label="Name"><?= e($name) ?></td>
                        <td data-label="Student number"><code><?= e($account['student_no'] ?? 'None') ?></code></td>
                        <td data-label="Email"><?= e($account['email']) ?></td>
                        <td data-label="Member number"><?= $account['member_no'] !== null ? '<code>' . e($account['member_no']) . '</code>' : 'Not a member yet' ?></td>
                        <td data-label="Application">
                            <?php if ($account['approved_application_id'] !== null) : ?>
                                <a href="<?= e(route('applications.show', ['id' => (int) $account['approved_application_id']])) ?>">Approved application</a>
                            <?php else : ?>
                                No approved application
                            <?php endif; ?>
                        </td>
                        <td data-label="Registered"><?= e(date_ph($account['created_at'])) ?></td>
                        <td data-label="Decision">
                            <form method="post" action="<?= e(route('accounts.approve', ['id' => $id])) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn--primary btn--sm">Approve account<span class="sr-only"> account of <?= e($name) ?></span></button>
                            </form>
                            <form method="post" action="<?= e(route('accounts.reject', ['id' => $id])) ?>">
                                <?= csrf_field() ?>
                                <label class="field__label" for="reason-<?= e($id) ?>">Remarks for rejecting</label>
                                <input class="input" id="reason-<?= e($id) ?>" name="reason" type="text" maxlength="255" required>
                                <button type="submit" class="btn btn--danger btn--sm">Reject account<span class="sr-only"> account of <?= e($name) ?></span></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
