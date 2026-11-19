<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $application
 * @var list<array<string, mixed>> $timeline
 * @var array<string, mixed> $user
 * @var list<array<string, mixed>> $academicQuestions
 * @var list<array<string, mixed>> $questions
 * @var array<int, string> $answers
 * @var list<array<string, mixed>> $documents
 */
$id = (int) $application['application_id'];
$status = $application['status'];
$revisionRemarks = null;

foreach ($timeline as $entry) {
    if ($entry['new_status'] === 'for_revision') {
        $revisionRemarks = $entry['remarks'];
    }
}
?>
<?php $this->start('title') ?>Application details<?php $this->end() ?>
<?php $this->start('page_title') ?>Application details<?php $this->end() ?>
<?php $this->start('page_actions') ?><?= status_badge($status) ?><?php $this->end() ?>

<?php if ($status === 'for_revision') : ?>
    <div class="alert alert--warning" role="status">
        <?= icon('alert') ?>
        <div class="alert__text">
            <p><strong>Changes are needed.</strong><?= $revisionRemarks !== null ? ' Remarks: ' . e($revisionRemarks) : '' ?></p>
            <a class="btn btn--primary btn--sm" href="<?= e(route('apply.step', ['id' => $id, 'step' => 1])) ?>">Edit and resubmit</a>
        </div>
    </div>
<?php elseif ($status === 'draft') : ?>
    <div class="alert alert--info" role="status">
        <?= icon('info') ?>
        <div class="alert__text">
            <p>This application has not been submitted yet.</p>
            <a class="btn btn--primary btn--sm" href="<?= e(route('apply')) ?>">Continue</a>
        </div>
    </div>
<?php endif; ?>

<div class="application-grid">
    <div class="card">
        <div class="card__body">
            <?php if ($status !== 'draft') : ?>
                <p class="code-box__label">Tracking code</p>
                <div class="code-box">
                    <code class="code-box__code" id="tracking-code"><?= e($application['tracking_code']) ?></code>
                    <button type="button" class="btn btn--secondary btn--sm" data-copy="tracking-code">Copy code</button>
                </div>
            <?php endif; ?>
            <?= $this->partial('membership/_summary', compact('application', 'user', 'academicQuestions', 'questions', 'answers', 'documents') + ['withEditLinks' => false]) ?>
        </div>
    </div>

    <section class="plain-section" aria-labelledby="status-history">
        <div class="card__body">
            <h2 id="status-history">Status history</h2>
            <?php if ($timeline === []) : ?>
                <p class="text-muted">Nothing yet. Updates appear here after you submit.</p>
            <?php else : ?>
                <ol class="timeline">
                    <?php foreach (array_reverse($timeline) as $index => $entry) : ?>
                        <li class="timeline__item <?= $index === 0 ? 'timeline__item--current' : '' ?>">
                            <?= status_badge($entry['new_status']) ?>
                            <p class="timeline__meta"><?= e(datetime_ph($entry['changed_at'])) ?></p>
                            <?php if ($entry['remarks'] !== null && in_array($entry['new_status'], ['for_revision', 'rejected', 'approved'], true)) : ?>
                                <p class="timeline__note"><?= nl2br(e($entry['remarks'])) ?></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
            <?php if ($status === 'submitted') : ?>
                <form class="application-withdraw" method="post" action="<?= e(route('my_applications.withdraw', ['id' => $id])) ?>" data-confirm="Your application will be withdrawn and reviewers will no longer process it. To apply again, you would start a new application." data-confirm-title="Withdraw this application?" data-confirm-action="Withdraw">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--danger btn--sm">Withdraw application</button>
                </form>
            <?php endif; ?>
        </div>
    </section>
</div>
