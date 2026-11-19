<?php
/**
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $requests
 */
?>
<?php $this->start('title') ?>Create vacancy<?php $this->end() ?>
<?php $this->start('page_title') ?>Create vacancy<?php $this->end() ?>

<?php if ($requests === []) : ?>
    <div class="empty-state">
        <p class="empty-state__text">A vacancy starts from an approved HR request, and none is waiting right now.</p>
        <a class="btn btn--secondary" href="<?= e(route('hr_requests')) ?>">Go to HR requests</a>
    </div>
<?php else : ?>
    <p>Choose the approved HR request this vacancy is for.</p>
    <div class="table-wrap">
        <table class="table table--stack">
            <caption class="sr-only">Approved HR requests without a vacancy</caption>
            <thead>
                <tr>
                    <th scope="col">Position</th>
                    <th scope="col">Committee</th>
                    <th scope="col" class="num">Slots</th>
                    <th scope="col">Approved</th>
                    <th scope="col">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $hrRequest) : ?>
                    <tr>
                        <td data-label="Position"><a href="<?= e(route('hr_requests.show', ['id' => (int) $hrRequest['request_id']])) ?>"><?= e($hrRequest['position_title']) ?></a></td>
                        <td data-label="Committee"><?= e($hrRequest['committee_name']) ?></td>
                        <td class="num" data-label="Slots"><?= e($hrRequest['slots']) ?></td>
                        <td data-label="Approved"><?= e(date_ph($hrRequest['approved_at'])) ?></td>
                        <td data-label="Action"><a class="btn btn--ghost btn--sm" href="<?= e(route('vacancies.create')) ?>?request=<?= e($hrRequest['request_id']) ?>">Create vacancy<span class="sr-only"> for <?= e($hrRequest['position_title']) ?></span></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
