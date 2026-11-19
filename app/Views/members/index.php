<?php
/**
 * @var \App\Core\View $this
 * @var array{data: list<array<string, mixed>>, page: int, lastPage: int, total: int} $result
 * @var array<string, string> $filters
 * @var list<array<string, mixed>> $committees
 */
$query = array_filter($filters, static fn (string $value): bool => $value !== '');
?>
<?php $this->start('title') ?>Members<?php $this->end() ?>
<?php $this->start('page_title') ?>Member records<?php $this->end() ?>

<form class="applications-filters" method="get" action="<?= e(route('members')) ?>" data-no-guard>
    <div class="field applications-filters__search">
        <label class="field__label" for="q">Search by name, member number or student number</label>
        <input class="input" id="q" name="q" type="search" value="<?= e($filters['q']) ?>">
    </div>
    <div class="field">
        <label class="field__label" for="type">Membership type</label>
        <select class="select" id="type" name="type">
            <option value="">All types</option>
            <option value="general"<?= $filters['type'] === 'general' ? ' selected' : '' ?>>General</option>
            <option value="officer"<?= $filters['type'] === 'officer' ? ' selected' : '' ?>>Officer</option>
        </select>
    </div>
    <div class="field">
        <label class="field__label" for="committee">Committee</label>
        <select class="select" id="committee" name="committee">
            <option value="">All committees</option>
            <?php foreach ($committees as $committee) : ?>
                <option value="<?= e($committee['committee_id']) ?>"<?= $filters['committee'] === (string) $committee['committee_id'] ? ' selected' : '' ?>><?= e($committee['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label class="field__label" for="role_status">Role status</label>
        <select class="select" id="role_status" name="role_status">
            <option value="">All</option>
            <option value="active"<?= $filters['role_status'] === 'active' ? ' selected' : '' ?>>Role active</option>
            <option value="expired"<?= $filters['role_status'] === 'expired' ? ' selected' : '' ?>>Role expired</option>
            <option value="none"<?= $filters['role_status'] === 'none' ? ' selected' : '' ?>>No officer role</option>
        </select>
    </div>
    <div class="field">
        <label class="field__label" for="registration">Account</label>
        <select class="select" id="registration" name="registration">
            <option value="">All</option>
            <option value="unregistered"<?= $filters['registration'] === 'unregistered' ? ' selected' : '' ?>>Not yet registered</option>
        </select>
    </div>
    <div class="applications-filters__actions">
        <button type="submit" class="btn btn--secondary">Apply filters</button>
        <a class="btn btn--ghost" href="<?= e(route('members')) ?>">Clear</a>
    </div>
</form>

<?php if ($result['data'] === []) : ?>
    <div class="empty-state">
        <p class="empty-state__text">People appear here after their application is approved.</p>
        <a class="btn btn--secondary btn--sm" href="<?= e(route('members')) ?>">Clear the filters</a>
    </div>
<?php else : ?>
    <p class="text-muted applications-count"><?= e($result['total']) ?> <?= $result['total'] === 1 ? 'member' : 'members' ?></p>
    <div class="table-wrap">
        <table class="table table--stack">
            <caption class="sr-only">Members</caption>
            <thead>
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Member number</th>
                    <th scope="col">Student number</th>
                    <th scope="col">Role and position</th>
                    <th scope="col">Type</th>
                    <th scope="col">Role status</th>
                    <th scope="col">Joined</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['data'] as $member) : ?>
                    <tr>
                        <td data-label="Name"><a href="<?= e(route('members.show', ['id' => (int) $member['member_id']])) ?>"><?= e($member['first_name'] . ' ' . $member['last_name']) ?></a></td>
                        <td data-label="Member number"><code><?= e($member['member_no']) ?></code></td>
                        <td data-label="Student number"><?= e($member['student_no'] ?? '') ?></td>
                        <td data-label="Role and position"><span class="truncate" title="<?= e(\App\Services\MembershipService::roleLabel($member)) ?>"><?= e(\App\Services\MembershipService::roleLabel($member)) ?></span></td>
                        <td data-label="Type"><?= e(ucfirst($member['membership_type'])) ?></td>
                        <td data-label="Role status"><?= $member['role_status'] === null ? 'None' : status_badge($member['role_status']) ?></td>
                        <td data-label="Joined"><?= e(date_ph($member['joined_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="portal-pagination">
        <?= $this->partial('partials/pagination', ['page' => $result['page'], 'lastPage' => $result['lastPage'], 'url' => route('members'), 'query' => $query]) ?>
    </div>
<?php endif; ?>
