<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $user
 * @var array<string, mixed> $dash
 */
?>
<?php $this->start('title') ?>My membership<?php $this->end() ?>
<?php $this->start('page_eyebrow') ?>Overview<?php $this->end() ?>
<?php $this->start('page_actions') ?><?= $this->partial('dashboard/_actions') ?><?php $this->end() ?>
<?php $this->start('page_title') ?>My membership<?php $this->end() ?>
<?php
// My welfare cases has its own section below, so it does not need a card too.
$dash['cards'] = array_values(array_filter($dash['cards'], static fn (array $card): bool => $card['label'] !== 'My welfare cases'));
$profile = $dash['profile'];
$approvedCredentials = $profile === null ? [] : array_values(array_filter($profile['activeCredentials'] ?? [], static fn (array $c): bool => $c['status'] === 'approved'));
?>
<?= $this->partial('dashboard/_cards', ['user' => $user, 'dash' => $dash]) ?>
<?php if ($approvedCredentials !== []) : ?>
    <section class="card dashboard-section" aria-labelledby="dashboard-credentials">
        <div class="card__header"><h2 class="card__title" id="dashboard-credentials">Your digital ID and certificate</h2></div>
        <div class="card__body">
            <?= $this->partial('members/_credential_cards', ['credentials' => $approvedCredentials, 'forOwner' => true, 'headingLevel' => 3]) ?>
        </div>
    </section>
<?php endif; ?>
<div class="grid grid--2 dashboard-section">
    <section class="card" aria-labelledby="dashboard-member-card">
        <div class="card__header"><h2 class="card__title" id="dashboard-member-card">Membership details</h2></div>
        <div class="card__body">
            <?php if ($profile === null) : ?>
                <p class="text-muted">Your member record is not ready. Please contact the MSW Committee.</p>
            <?php else : ?>
                <dl class="detail-list member-details">
                    <div><dt>Name</dt><dd><?= e($user['first_name'] . ' ' . $user['last_name']) ?></dd></div>
                    <div><dt>Member number</dt><dd><code><?= e($profile['member']['member_no']) ?></code></dd></div>
                    <div><dt>Status</dt><dd><?= status_badge($profile['member']['status']) ?></dd></div>
                    <div><dt>Role and position</dt><dd><?= e($profile['roleLabel']) ?></dd></div>
                </dl>
                <?php if ($approvedCredentials === []) : ?>
                    <p class="member-section"><a href="<?= e(route('account')) ?>">Digital ID and certificate</a></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
    <section class="card" aria-labelledby="dashboard-role-history">
        <div class="card__header"><h2 class="card__title" id="dashboard-role-history">Role history</h2></div>
        <div class="card__body">
            <?php if ($profile === null || $profile['history'] === []) : ?>
                <p class="text-muted">No role changes have been recorded.</p>
            <?php else : ?>
                <ol class="timeline">
                    <?php foreach (array_slice(array_reverse($profile['history']), 0, 3) as $index => $entry) : ?>
                        <li class="timeline__item <?= $index === 0 ? 'timeline__item--current' : '' ?>">
                            <strong><?= e($entry['new_role']) ?></strong>
                            <p class="timeline__meta"><?= e(date_ph($entry['changed_at'])) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>
    </section>
</div>
<div class="dashboard-section"><?= $this->partial('dashboard/_welfare_status', ['cases' => $dash['welfareCases']]) ?></div>
<?= $this->partial('dashboard/_feeds', ['dash' => $dash]) ?>
