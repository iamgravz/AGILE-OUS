<?php
/**
 * The confidential case workspace.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $case
 * @var list<array<string, mixed>> $notes
 * @var list<array<string, mixed>> $referrals
 * @var list<array<string, mixed>> $followups
 * @var array<string, mixed>|null $resolution
 * @var list<array<string, mixed>> $attachments
 * @var list<array<string, mixed>> $history
 * @var list<array<string, mixed>> $handlers
 * @var list<array<string, mixed>> $categories
 * @var string $activeTab
 */
$tabs = ['overview' => 'Overview', 'notes' => 'Notes', 'referrals' => 'Referrals', 'followups' => 'Follow-ups', 'closure' => 'Closure and files', 'timeline' => 'Timeline'];
$activeTab = isset($tabs[$activeTab]) ? $activeTab : 'overview';
$isClosed = $case['status'] === 'closed';
$shared = ['case' => $case, 'isClosed' => $isClosed];
?>
<?php $this->start('title') ?>Case <?= e($case['case_code']) ?><?php $this->end() ?>
<?php $this->start('page_title') ?>Case <?= e($case['case_code']) ?><?php $this->end() ?>
<?php $this->start('page_actions') ?>
<?= status_badge($case['priority']) ?>
<?= status_badge($case['status']) ?>
<?php if ($case['overdue']) : ?>
    <?= status_badge('overdue') ?>
<?php endif; ?>
<?php $this->end() ?>

<div data-tabs>
    <div class="tabs__list" role="tablist" aria-label="Case sections">
        <?php foreach ($tabs as $key => $label) : ?>
            <button type="button" class="tabs__tab" role="tab" id="tab-<?= e($key) ?>" aria-selected="<?= $key === $activeTab ? 'true' : 'false' ?>" aria-controls="panel-<?= e($key) ?>"<?= $key === $activeTab ? '' : ' tabindex="-1"' ?>><?= e($label) ?></button>
        <?php endforeach; ?>
    </div>
    <div class="tabs__panel" role="tabpanel" id="panel-overview" aria-labelledby="tab-overview"<?= $activeTab === 'overview' ? '' : ' hidden' ?>>
        <?= $this->partial('welfare/cases/_overview', $shared + ['handlers' => $handlers, 'categories' => $categories]) ?>
    </div>
    <div class="tabs__panel" role="tabpanel" id="panel-notes" aria-labelledby="tab-notes"<?= $activeTab === 'notes' ? '' : ' hidden' ?>>
        <?= $this->partial('welfare/cases/_notes', $shared + ['notes' => $notes]) ?>
    </div>
    <div class="tabs__panel" role="tabpanel" id="panel-referrals" aria-labelledby="tab-referrals"<?= $activeTab === 'referrals' ? '' : ' hidden' ?>>
        <?= $this->partial('welfare/cases/_referrals', $shared + ['referrals' => $referrals]) ?>
    </div>
    <div class="tabs__panel" role="tabpanel" id="panel-followups" aria-labelledby="tab-followups"<?= $activeTab === 'followups' ? '' : ' hidden' ?>>
        <?= $this->partial('welfare/cases/_followups', $shared + ['followups' => $followups, 'handlers' => $handlers]) ?>
    </div>
    <div class="tabs__panel" role="tabpanel" id="panel-closure" aria-labelledby="tab-closure"<?= $activeTab === 'closure' ? '' : ' hidden' ?>>
        <?= $this->partial('welfare/cases/_closure', $shared + ['resolution' => $resolution, 'attachments' => $attachments]) ?>
    </div>
    <div class="tabs__panel" role="tabpanel" id="panel-timeline" aria-labelledby="tab-timeline"<?= $activeTab === 'timeline' ? '' : ' hidden' ?>>
        <?= $this->partial('welfare/cases/_timeline', ['history' => $history]) ?>
    </div>
</div>
