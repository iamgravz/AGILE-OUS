<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $result
 * @var \App\Services\EmailDraftService $service
 */
?>
<?php $this->start('title') ?>Email drafts<?php $this->end() ?>
<?php $this->start('page_title') ?>Email drafts<?php $this->end() ?>
<?php $this->start('page_actions') ?><a class="btn btn--primary btn--sm" href="<?= e(route('emails.create')) ?>"><?= icon('plus') ?> Write an email</a><?php $this->end() ?>

<?php if ($result['data'] === []) : ?>
    <div class="empty-state"><p class="empty-state__text">Emails to members, officers or applicants appear here once you write one.</p></div>
<?php else : ?>
    <div class="table-wrap">
        <table class="table table--stack">
            <caption class="sr-only">Email drafts</caption>
            <thead><tr><th scope="col">Subject</th><th scope="col">To</th><th scope="col">Written by</th><th scope="col">Status</th><th scope="col">Last updated</th></tr></thead>
            <tbody>
                <?php foreach ($result['data'] as $draft) : ?>
                    <tr>
                        <td data-label="Subject"><a class="truncate" href="<?= e(route('emails.show', ['id' => (int) $draft['draft_id']])) ?>" title="<?= e($draft['subject']) ?>"><?= e($draft['subject']) ?></a></td>
                        <td data-label="To"><?= e($service->targetLabel($draft['target_group'])) ?></td>
                        <td data-label="Written by"><?= e($draft['author_name']) ?></td>
                        <td data-label="Status"><?= status_badge($draft['status']) ?></td>
                        <td data-label="Last updated"><?= e(date_ph($draft['updated_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= $this->partial('partials/pagination', ['page' => $result['page'], 'lastPage' => $result['lastPage'], 'url' => route('emails')]) ?>
<?php endif; ?>
