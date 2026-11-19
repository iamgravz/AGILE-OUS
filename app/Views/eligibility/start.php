<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $term
 * @var list<array<string, mixed>> $covered
 */
use App\Services\EligibilityService;
use App\Services\MembershipService;

$count = count($covered);
?>
<?php $this->start('title') ?>Start a screening<?php $this->end() ?>
<?php $this->start('form_side') ?><?= $this->partial('partials/side_panel', ['blocks' => [['title' => 'Who is screened', 'text' => 'Officers only: executive officers, committee heads, deputies, members and The Source Code. General Members are left out.'], ['title' => 'What happens next', 'items' => ['Each officer is told to submit their documents.', 'Reminders go out before the deadline.', 'After the deadline and grace days, those who did not submit are marked incomplete.']]]]) ?><?php $this->end() ?>
<?php $this->start('page_title') ?>Start a screening<?php $this->end() ?>

<div class="case-sections">
    <section class="card" aria-labelledby="start-title">
        <div class="card__header"><h2 class="card__title" id="start-title"><?= e(EligibilityService::termLabel($term)) ?></h2></div>
        <div class="card__body">
            <p>Deadline: <?= e(date_ph($term['screening_deadline'])) ?>, with <?= e($term['grace_days']) ?> grace days. General Members are not screened.</p>
            <?php if ($covered === []) : ?>
                <p class="text-muted">Every covered officer already has a screening for this term.</p>
            <?php else : ?>
                <p><strong><?= e($count) ?> <?= $count === 1 ? 'officer' : 'officers' ?></strong> will be asked for their documents.</p>
                <div class="table-wrap">
                    <table class="table table--stack">
                        <caption class="sr-only">Officers covered by this screening</caption>
                        <thead>
                            <tr><th scope="col">Officer</th><th scope="col">Member number</th><th scope="col">Position</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($covered as $member) : ?>
                                <tr>
                                    <td data-label="Officer"><?= e($member['officer_name']) ?></td>
                                    <td data-label="Member number"><code><?= e($member['member_no']) ?></code></td>
                                    <td data-label="Position"><span class="truncate" title="<?= e(MembershipService::roleLabel($member)) ?>"><?= e(MembershipService::roleLabel($member)) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <form class="member-reissue" method="post" action="<?= e(route('eligibility.start')) ?>" data-confirm="<?= e($count) ?> officer(s) get a request by email and in the app." data-confirm-title="Start this screening?" data-confirm-action="Start screening" data-confirm-tone="primary">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--primary">Start screening for <?= e($count) ?> <?= $count === 1 ? 'officer' : 'officers' ?></button>
                </form>
            <?php endif; ?>
        </div>
    </section>
</div>
