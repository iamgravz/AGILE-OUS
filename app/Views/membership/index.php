<?php
/**
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $applications
 */
$canApply = can('application.submit');
?>
<?php $this->start('title') ?>My Applications<?php $this->end() ?>
<?php $this->start('page_title') ?>My applications<?php $this->end() ?>
<?php if ($canApply) : ?>
    <?php $this->start('page_actions') ?>
    <a class="btn btn--primary btn--sm" href="<?= e(route('apply')) ?>">Apply</a>
    <?php $this->end() ?>
<?php endif; ?>

<?php if ($applications === []) : ?>
    <div class="empty-state">
        <p class="empty-state__text">Your applications appear here after you apply.</p>
        <?php if ($canApply) : ?>
            <a class="btn btn--primary" href="<?= e(route('apply')) ?>">Start an application</a>
        <?php endif; ?>
    </div>
<?php else : ?>
    <div class="table-wrap">
        <table class="table table--stack">
            <caption class="sr-only">Your applications</caption>
            <thead>
                <tr>
                    <th scope="col">Applying for</th>
                    <th scope="col">Tracking code</th>
                    <th scope="col">Status</th>
                    <th scope="col">Submitted</th>
                    <th scope="col">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applications as $application) : ?>
                    <?php
                    $id = (int) $application['application_id'];
                    $isDraft = $application['status'] === 'draft';
                    $label = $application['vacancy_title'] ?? ($isDraft && (int) $application['wizard_step'] <= 3 ? 'Not chosen yet' : 'General Membership');
                    ?>
                    <tr>
                        <td data-label="Applying for"><span class="truncate" title="<?= e($label) ?>"><?= e($label) ?></span></td>
                        <td data-label="Tracking code"><?= $isDraft ? 'Given when submitted' : '<code>' . e($application['tracking_code']) . '</code>' ?></td>
                        <td data-label="Status"><?= status_badge($application['status']) ?></td>
                        <td data-label="Submitted"><?= $application['submitted_at'] === null ? 'Not yet' : e(date_ph($application['submitted_at'])) ?></td>
                        <td data-label="Action">
                            <?php if ($isDraft) : ?>
                                <a class="btn btn--ghost btn--sm" href="<?= e(route('apply')) ?>">Continue<span class="sr-only"> your draft</span></a>
                            <?php else : ?>
                                <a class="btn btn--ghost btn--sm" href="<?= e(route('my_applications.show', ['id' => $id])) ?>">View<span class="sr-only"> <?= e($application['tracking_code']) ?></span></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
