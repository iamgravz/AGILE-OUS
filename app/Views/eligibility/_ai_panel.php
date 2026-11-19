<?php
/**
 * The optional AI panel. Suggestions sit beside the manual fields, and nothing is saved from here (BR-11).
 *
 * @var \App\Core\View $this
 * @var int $id
 * @var bool $aiEnabled
 * @var array<string, mixed>|null $suggestion
 */
?>
<section class="plain-section" aria-labelledby="review-ai">
    <div class="card__header"><h2 class="card__title" id="review-ai">Suggest values (AI)</h2></div>
    <div class="card__body">
        <?php if (!$aiEnabled) : ?>
            <p class="text-muted">AI suggestions are turned off. Enter values manually.</p>
        <?php else : ?>
            <div class="alert alert--warning" role="note">
                <?= icon('alert') ?>
                <p class="alert__text"><strong>AI suggestions can be wrong.</strong> This sends the documents to an outside AI provider. Do not use it with real student data until AGILE and the University approve it.</p>
            </div>
            <?php if ($suggestion !== null) : ?>
                <dl class="detail-list member-details">
                    <div><dt>Confidence</dt><dd><?= e($suggestion['confidence'] === null ? 'Not given' : round($suggestion['confidence'] * 100) . '% (the AI\'s own estimate)') ?></dd></div>
                    <div><dt>Student name</dt><dd><?= e($suggestion['fields']['student_name'] ?? 'Not found') ?></dd></div>
                    <div><dt>Semester</dt><dd><?= e($suggestion['fields']['semester'] ?? 'Not found') ?></dd></div>
                    <div><dt>Academic year</dt><dd><?= e($suggestion['fields']['academic_year'] ?? 'Not found') ?></dd></div>
                    <div><dt>Enrollment</dt><dd><?= e(ucfirst(str_replace('_', ' ', (string) ($suggestion['fields']['enrollment_status'] ?? 'unclear')))) ?></dd></div>
                </dl>
                <?php if (($suggestion['fields']['subjects'] ?? []) !== []) : ?>
                    <div class="table-wrap">
                        <table class="table table--stack">
                            <caption class="sr-only">Subjects and grades the AI read</caption>
                            <thead><tr><th scope="col">Subject</th><th scope="col">Grade</th></tr></thead>
                            <tbody>
                                <?php foreach ($suggestion['fields']['subjects'] as $subject) : ?>
                                    <tr>
                                        <td data-label="Subject"><span class="truncate" title="<?= e($subject['code'] ?? '') ?>"><?= e($subject['code'] ?? '') ?></span></td>
                                        <td data-label="Grade"><?= e($subject['grade'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                <p class="field__help">Compare these with the documents. Use a value below, or answer yourself, then press Save verification.</p>
            <?php endif; ?>
            <form method="post" action="<?= e(route('eligibility.review.suggest', ['id' => $id])) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn--secondary"><?= $suggestion === null ? 'Suggest values' : 'Suggest again' ?></button>
            </form>
        <?php endif; ?>
    </div>
</section>
