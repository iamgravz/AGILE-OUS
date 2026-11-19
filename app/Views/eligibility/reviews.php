<?php
/**
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $terms
 * @var int $termId
 * @var string $filter
 * @var array{data: list<array<string, mixed>>, page: int, lastPage: int, total: int} $result
 */
use App\Services\EligibilityService;
use App\Services\MembershipService;
?>
<?php $this->start('title') ?>Screening reviews<?php $this->end() ?>
<?php $this->start('page_title') ?>Screening reviews<?php $this->end() ?>

<form class="applications-filters" method="get" action="<?= e(route('eligibility.review')) ?>" data-no-guard>
    <div class="field">
        <label class="field__label" for="term">Term</label>
        <select class="select" id="term" name="term">
            <?php foreach ($terms as $term) : ?>
                <option value="<?= e($term['term_id']) ?>"<?= (int) $term['term_id'] === $termId ? ' selected' : '' ?>><?= e(EligibilityService::termLabel($term)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label class="field__label" for="result">Status</label>
        <select class="select" id="result" name="result">
            <option value="">All statuses</option>
            <?php foreach (EligibilityService::STATUSES as $status) : ?>
                <option value="<?= e($status) ?>"<?= $filter === $status ? ' selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $status))) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="applications-filters__actions">
        <button type="submit" class="btn btn--secondary">Apply filters</button>
        <a class="btn btn--ghost" href="<?= e(route('eligibility.review')) ?>">Clear</a>
    </div>
</form>

<?php if ($result['data'] === []) : ?>
    <div class="empty-state">
        <p class="empty-state__text">Screenings appear here after one is started for the term.</p>
    </div>
<?php else : ?>
    <p class="text-muted applications-count"><?= e($result['total']) ?> <?= $result['total'] === 1 ? 'officer' : 'officers' ?></p>
    <div class="table-wrap">
        <table class="table table--stack">
            <caption class="sr-only">Officers covered by this screening</caption>
            <thead>
                <tr><th scope="col">Officer</th><th scope="col">Member number</th><th scope="col">Position</th><th scope="col">Status</th><th scope="col">Reviewer</th></tr>
            </thead>
            <tbody>
                <?php foreach ($result['data'] as $screening) : ?>
                    <?php $canOpen = $screening['can_review']; ?>
                    <tr>
                        <td data-label="Officer">
                            <?php if ($canOpen) : ?>
                                <a href="<?= e(route('eligibility.review.show', ['id' => (int) $screening['screening_id']])) ?>"><?= e($screening['officer_name']) ?></a>
                            <?php else : ?>
                                <?= e($screening['officer_name']) ?>
                            <?php endif; ?>
                        </td>
                        <td data-label="Member number"><code><?= e($screening['member_no']) ?></code></td>
                        <td data-label="Position"><span class="truncate" title="<?= e(MembershipService::roleLabel($screening)) ?>"><?= e(MembershipService::roleLabel($screening)) ?></span></td>
                        <td data-label="Status"><?= status_badge($screening['result']) ?></td>
                        <td data-label="Reviewer"><?= $canOpen ? 'You' : ($screening['role_slug'] === 'msw_head' ? 'The President' : 'The MSW Committee Head') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="portal-pagination">
        <?= $this->partial('partials/pagination', ['page' => $result['page'], 'lastPage' => $result['lastPage'], 'url' => route('eligibility.review'), 'query' => ['term' => (string) $termId] + ($filter === '' ? [] : ['result' => $filter])]) ?>
    </div>
<?php endif; ?>
