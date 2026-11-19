<?php
/**
 * The submitter's view: status and student-visible updates. No internal notes, referrals or handler names.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $case
 * @var list<array{time: string, status: ?string, note: ?string}> $timeline
 * @var list<array<string, mixed>> $attachments
 */
$files = array_values(array_filter($attachments, static fn (array $file): bool => $file['disposition'] !== 'deleted'));
?>
<?php $this->start('title') ?>Case <?= e($case['case_code']) ?><?php $this->end() ?>
<?php $this->start('page_title') ?>Case <?= e($case['case_code']) ?><?php $this->end() ?>
<?php $this->start('page_actions') ?><?= status_badge($case['status']) ?><?php $this->end() ?>

<div class="account-sections">
    <?php if ($case['status'] === 'closed') : ?>
        <div class="alert alert--info" role="status">
            <?= icon('info') ?>
            <p class="alert__text">This case is closed. Submit a new concern if you still need help.</p>
        </div>
        <p><a class="btn btn--primary" href="<?= e(route('welfare.new')) ?>?related=<?= e($case['case_id']) ?>">Submit a new concern</a></p>
    <?php endif; ?>

    <section class="plain-section" aria-labelledby="case-details">
        <div class="card__header"><h2 class="card__title" id="case-details">Case details</h2></div>
        <div class="card__body">
            <dl class="detail-list member-details">
                <div><dt>Subject</dt><dd><?= e($case['subject']) ?></dd></div>
                <div><dt>Submitted</dt><dd><?= e(datetime_ph($case['submitted_at'])) ?></dd></div>
            </dl>
            <?php if ($files !== []) : ?>
                <h3 class="member-section">Your attachments</h3>
                <ul class="member-applications">
                    <?php foreach ($files as $file) : ?>
                        <li><a href="<?= e(route('files.show', ['type' => 'welfare_attachment', 'id' => (int) $file['attachment_id']])) ?>"><?= e($file['original_name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>

    <section class="plain-section" aria-labelledby="case-updates">
        <div class="card__header"><h2 class="card__title" id="case-updates">Updates</h2></div>
        <div class="card__body">
            <ol class="timeline">
                <?php foreach (array_reverse($timeline) as $index => $entry) : ?>
                    <li class="timeline__item <?= $index === 0 ? 'timeline__item--current' : '' ?>">
                        <?php if ($entry['status'] !== null) : ?>
                            <?= status_badge($entry['status']) ?>
                        <?php endif; ?>
                        <?php if ($entry['note'] !== null) : ?>
                            <p class="timeline__note"><?= nl2br(e($entry['note'])) ?></p>
                        <?php endif; ?>
                        <p class="timeline__meta"><?= e(datetime_ph($entry['time'])) ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>
</div>
