<?php
/**
 * Run for office: a member files a candidacy for the Executive Board or the Year-Level Council (Article VI).
 *
 * @var \App\Core\View $this
 * @var array<string, string> $input
 * @var array<string, string> $errors
 * @var list<array<string, mixed>> $seats
 * @var list<array<string, mixed>> $mine
 * @var bool $filingOpen
 * @var bool $canFile
 * @var string $academicYear
 * @var int $maxMb
 */
use App\Services\CandidacyService;
use App\Services\ElectedOfficerService;

$byGroup = [];

foreach ($seats as $seat) {
    $byGroup[$seat['group']][] = $seat;
}

$ordinal = static fn (int $n): string => $n . ([1 => 'st', 2 => 'nd', 3 => 'rd'][$n] ?? 'th');
$needs = static fn (array $seat): string => $seat['group'] === 'Executive Board'
    ? 'at least ' . $ordinal(ElectedOfficerService::minimumYear($seat)) . ' year'
    : $ordinal((int) $seat['title']) . ' year only';
$positionError = $errors['position_id'] ?? null;
$statementError = $errors['statement'] ?? null;
$tones = ['filed' => 'info', 'qualified' => 'success', 'disqualified' => 'danger', 'withdrawn' => 'neutral', 'elected' => 'success'];
?>
<?php $this->start('title') ?>Run for office<?php $this->end() ?>
<?php $this->start('page_title') ?>Run for office<?php $this->end() ?>
<div class="centered-page">

<p class="text-muted">The Executive Board and the Year-Level Representatives are elected by the members (Constitution and By-Laws, Article VI). File your candidacy here for AY <?= e($academicYear) ?>. The MSW Committee checks your COR and grades, then tells you whether you qualify to run.</p>

<?php if ($mine !== []) : ?>
    <section class="plain-section" aria-labelledby="my-candidacies">
        <div class="card__header"><h2 class="card__title" id="my-candidacies">My candidacies</h2></div>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead><tr><th scope="col">Position</th><th scope="col">Academic year</th><th scope="col">Status</th><th scope="col">Remarks</th><th scope="col"><span class="sr-only">Action</span></th></tr></thead>
                <tbody>
                    <?php foreach ($mine as $row) : ?>
                        <tr>
                            <td data-label="Position"><?= e($row['position_title']) ?></td>
                            <td data-label="Academic year"><?= e($row['academic_year']) ?></td>
                            <td data-label="Status"><span class="badge badge--<?= e($tones[$row['status']]) ?>"><?= e(CandidacyService::STATUSES[$row['status']]) ?></span></td>
                            <td data-label="Remarks"><?= e($row['remarks'] ?? '') ?></td>
                            <td data-label="Action">
                                <?php if (in_array($row['status'], ['filed', 'qualified'], true)) : ?>
                                    <form method="post" action="<?= e(route('candidacy.withdraw', ['id' => (int) $row['candidacy_id']])) ?>" data-confirm="You can file again for another position while filing is open." data-confirm-title="Withdraw your candidacy?" data-confirm-action="Withdraw">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn--ghost btn--sm">Withdraw</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php if (!$canFile) : ?>
    <?= $this->partial('partials/alert', ['tone' => 'info', 'message' => 'Only AGILE members can run for office. Join AGILE first, then file when the next election opens.']) ?>
<?php elseif (!$filingOpen) : ?>
    <?= $this->partial('partials/alert', ['tone' => 'info', 'message' => 'Filing of candidacy is closed right now. The MSW Committee announces when it opens.']) ?>
<?php else : ?>
    <form class="plain-section" method="post" action="<?= e(route('candidacy')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="card__header"><h2 class="card__title">File your candidacy</h2></div>
        <div class="card__body">
            <?php if (isset($errors['files'])) : ?>
                <?= $this->partial('partials/alert', ['tone' => 'danger', 'message' => $errors['files']]) ?>
            <?php endif; ?>
            <div class="field <?= $positionError !== null ? 'field--error' : '' ?>">
                <label class="field__label" for="position_id">Running for <span class="field__required" aria-hidden="true">*</span></label>
                <select class="select" id="position_id" name="position_id" required<?= $positionError !== null ? ' aria-invalid="true" aria-describedby="position-error"' : '' ?>>
                    <option value="">Choose a position</option>
                    <?php foreach ($byGroup as $group => $rows) : ?>
                        <optgroup label="<?= e($group) ?>">
                            <?php foreach ($rows as $seat) : ?>
                                <option value="<?= e($seat['position_id']) ?>"<?= ($input['position_id'] ?? '') === (string) $seat['position_id'] ? ' selected' : '' ?>><?= e($seat['title']) ?> (<?= e($needs($seat)) ?>)</option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <p class="field__help">Article VI, Section 2: the President and Vice Presidents are at least 3rd year, the other Executive Board posts at least 2nd year. You need a full academic load and no grade of 5.0, W or D last semester.</p>
                <?php if ($positionError !== null) : ?><p class="field__error" id="position-error"><?= icon('alert') ?> <?= e($positionError) ?></p><?php endif; ?>
            </div>
            <?= $this->partial('partials/student_details', ['values' => $input, 'errors' => $errors, 'row' => true, 'contactHelp' => 'For example, 09171234567. Saved to your account.']) ?>
            <div class="form-row">
            <?= $this->partial('partials/upload_field', ['name' => 'cor', 'label' => 'Current COR', 'types' => ['pdf', 'jpg', 'jpeg', 'png'], 'maxMb' => $maxMb, 'help' => "PDF, JPG or PNG, up to {$maxMb} MB. Shows your full load this semester.", 'errorMessage' => null]) ?>
            <?= $this->partial('partials/upload_field', ['name' => 'grades', 'label' => 'Previous semester copy of grades', 'types' => ['pdf', 'jpg', 'jpeg', 'png'], 'maxMb' => $maxMb, 'help' => "PDF, JPG or PNG, up to {$maxMb} MB.", 'errorMessage' => null]) ?>
            </div>
            <div class="field <?= $statementError !== null ? 'field--error' : '' ?>">
                <label class="field__label" for="statement">Why you are running</label>
                <textarea class="textarea" id="statement" name="statement" rows="3" maxlength="<?= e(CandidacyService::STATEMENT_MAX) ?>" aria-describedby="statement-help"><?= e($input['statement'] ?? '') ?></textarea>
                <p class="field__help" id="statement-help">Optional. Your platform, in a few sentences.</p>
                <?php if ($statementError !== null) : ?><p class="field__error"><?= icon('alert') ?> <?= e($statementError) ?></p><?php endif; ?>
            </div>
            <button type="submit" class="btn btn--primary">File candidacy</button>
        </div>
    </form>
<?php endif; ?>
</div>
