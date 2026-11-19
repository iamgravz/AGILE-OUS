<?php
/**
 * @var \App\Core\View $this
 * @var array{data: list<array<string, mixed>>, page: int, lastPage: int, total: int} $result
 */
?>
<?php $this->start('title') ?>My welfare cases<?php $this->end() ?>
<?php $this->start('page_title') ?>My welfare cases<?php $this->end() ?>
<?php $this->start('page_actions') ?>
<a class="btn btn--primary btn--sm" href="<?= e(route('welfare.new')) ?>">Submit a concern</a>
<?php $this->end() ?>

<?php if ($result['data'] === []) : ?>
    <div class="empty-state">
        <p class="empty-state__text">Concerns you submit appear here, and only the MSW Committee Head and the President can read them.</p>
        <a class="btn btn--secondary btn--sm" href="<?= e(route('welfare.new')) ?>">Ask for help</a>
    </div>
<?php else : ?>
    <div class="table-wrap">
        <table class="table table--stack">
            <caption class="sr-only">My welfare cases</caption>
            <thead>
                <tr>
                    <th scope="col">Case code</th>
                    <th scope="col">Subject</th>
                    <th scope="col">Status</th>
                    <th scope="col">Submitted</th>
                    <th scope="col">Last updated</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['data'] as $case) : ?>
                    <tr>
                        <td data-label="Case code"><a href="<?= e(route('my_welfare_cases.show', ['id' => (int) $case['case_id']])) ?>"><code><?= e($case['case_code']) ?></code></a></td>
                        <td data-label="Subject"><span class="truncate" title="<?= e($case['subject']) ?>"><?= e($case['subject']) ?></span></td>
                        <td data-label="Status"><?= status_badge($case['status']) ?></td>
                        <td data-label="Submitted"><?= e(date_ph($case['submitted_at'])) ?></td>
                        <td data-label="Last updated"><?= e(date_ph($case['updated_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="portal-pagination">
        <?= $this->partial('partials/pagination', ['page' => $result['page'], 'lastPage' => $result['lastPage'], 'url' => route('my_welfare_cases')]) ?>
    </div>
<?php endif; ?>
