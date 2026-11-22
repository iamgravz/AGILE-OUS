<?php
/**
 * Candidacies: open or close filing, check each candidate's COR and grades, decide, and mark the winners elected.
 *
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $rows
 * @var array{year: string, status: string} $filters
 * @var bool $filingOpen
 * @var string $academicYear
 */
use App\Services\CandidacyService;

$tones = ['filed' => 'info', 'qualified' => 'success', 'disqualified' => 'danger', 'withdrawn' => 'neutral', 'elected' => 'success'];
?>
<?php $this->start('title') ?>Candidacies<?php $this->end() ?>
<?php $this->start('page_title') ?>Candidacies<?php $this->end() ?>

<section class="plain-section" aria-labelledby="filing-title">
    <div class="card__header"><h2 class="card__title" id="filing-title">Filing of candidacy, AY <?= e($academicYear) ?></h2></div>
    <div class="card__body">
        <p>Filing is <strong><?= $filingOpen ? 'open' : 'closed' ?></strong>. <?= $filingOpen ? 'Members can file under Run for office.' : 'Members see that filing is closed.' ?></p>
        <form method="post" action="<?= e(route('admin.candidacies.filing')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="open" value="<?= $filingOpen ? '0' : '1' ?>">
            <button type="submit" class="btn btn--<?= $filingOpen ? 'secondary' : 'primary' ?>"><?= $filingOpen ? 'Close filing' : 'Open filing' ?></button>
        </form>
    </div>
</section>

<form class="applications-filters admin-filters" method="get" action="<?= e(route('admin.candidacies')) ?>" data-no-guard>
    <div class="field">
        <label class="field__label" for="year">Academic year</label>
        <input class="input" id="year" name="year" value="<?= e($filters['year']) ?>" placeholder="<?= e($academicYear) ?>">
    </div>
    <div class="field">
        <label class="field__label" for="status">Status</label>
        <select class="select" id="status" name="status">
            <option value="">All</option>
            <?php foreach (CandidacyService::STATUSES as $key => $label) : ?>
                <option value="<?= e($key) ?>"<?= $filters['status'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn--secondary">Filter</button>
    <a class="btn btn--ghost" href="<?= e(route('admin.candidacies')) ?>">Clear</a>
</form>

<?php if ($rows === []) : ?>
    <p class="text-muted">No candidacies yet.</p>
<?php else : ?>
    <div class="table-wrap">
        <table class="table table--stack">
            <caption class="sr-only">Candidacies</caption>
            <thead>
                <tr>
                    <th scope="col">Candidate</th>
                    <th scope="col">Running for</th>
                    <th scope="col">Year and section</th>
                    <th scope="col">Documents</th>
                    <th scope="col">Status</th>
                    <th scope="col">Decision</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) : ?>
                    <?php $id = (int) $row['candidacy_id']; ?>
                    <tr>
                        <td data-label="Candidate">
                            <strong><?= e($row['first_name'] . ' ' . $row['last_name']) ?></strong><br>
                            <span class="text-muted"><?= e($row['email']) ?>, <?= e($row['contact_no']) ?></span>
                            <?php if ($row['statement'] !== null) : ?>
                                <details><summary>Statement</summary><p><?= nl2br(e($row['statement'])) ?></p></details>
                            <?php endif; ?>
                        </td>
                        <td data-label="Running for"><?= e($row['position_title']) ?><br><span class="text-muted">AY <?= e($row['academic_year']) ?></span></td>
                        <td data-label="Year and section"><?= e($row['year_level'] . ', ' . $row['section']) ?></td>
                        <td data-label="Documents">
                            <a href="<?= e(route('candidacy.document', ['id' => $id, 'kind' => 'cor'])) ?>" target="_blank" rel="noopener">COR</a><br>
                            <a href="<?= e(route('candidacy.document', ['id' => $id, 'kind' => 'grades'])) ?>" target="_blank" rel="noopener">Grades</a>
                        </td>
                        <td data-label="Status">
                            <span class="badge badge--<?= e($tones[$row['status']]) ?>"><?= e(CandidacyService::STATUSES[$row['status']]) ?></span>
                            <?php if ($row['remarks'] !== null) : ?><br><span class="text-muted"><?= e($row['remarks']) ?></span><?php endif; ?>
                        </td>
                        <td data-label="Decision">
                            <?php if (in_array($row['status'], ['filed', 'qualified', 'disqualified'], true)) : ?>
                                <form class="stack-sm" method="post" action="<?= e(route('admin.candidacies.decide', ['id' => $id])) ?>">
                                    <?= csrf_field() ?>
                                    <label class="sr-only" for="remarks-<?= e($id) ?>">Remarks</label>
                                    <input class="input" id="remarks-<?= e($id) ?>" name="remarks" maxlength="255" placeholder="Remarks (required to disqualify)">
                                    <div class="cluster">
                                        <button type="submit" name="decision" value="qualified" class="btn btn--secondary btn--sm">Qualified</button>
                                        <button type="submit" name="decision" value="disqualified" class="btn btn--ghost btn--sm">Disqualify</button>
                                    </div>
                                </form>
                            <?php endif; ?>
                            <?php if ($row['status'] === 'qualified') : ?>
                                <form method="post" action="<?= e(route('admin.candidacies.elected', ['id' => $id])) ?>" data-confirm="They get the position, the role and an account if they have none, and the term is recorded for AY <?= e($row['academic_year']) ?>." data-confirm-title="Mark as elected?" data-confirm-action="Mark elected">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="remarks" value="Elected in the AGILE-OUS general election, AY <?= e($row['academic_year']) ?>">
                                    <button type="submit" class="btn btn--primary btn--sm">Mark elected</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
