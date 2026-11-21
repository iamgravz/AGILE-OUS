<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $application
 * @var list<array<string, mixed>> $documents
 * @var list<string> $requiredDocuments doc_types this application must have (MembershipService::requiredDocuments)
 * @var list<string> $documentsToAsk the upload slots: the required ones, then optional certificates
 * @var bool $firstYearFirstSemester a 1st year in a 1st semester, who gives no grades
 * @var int $step
 * @var int $reached
 */
$id = (int) $application['application_id'];
$maxMb = (new \App\Services\FileStorageService())->maxUploadMb();
$isDraft = $application['status'] === 'draft';
?>
<?php $this->start('title') ?>Apply: Documents<?php $this->end() ?>
<?php $this->start('page_title') ?>Membership application<?php $this->end() ?>
<?php $this->start('form_side') ?><?= $this->partial('membership/apply/_side', compact('application', 'step', 'reached')) ?><?php $this->end() ?>
<?= $this->partial('membership/apply/_stepper', compact('application', 'step', 'reached')) ?>
<div class="wizard-form">
    <div class="plain-section wizard-docs-intro">
        <div class="card__body">
            <h2>Documents</h2>
            <?php if (in_array('grades', $requiredDocuments, true)) : ?>
                <p>You are applying for <strong><?= e($application['vacancy_title'] ?? 'a position') ?></strong>, so we need your <strong>current COR</strong> and your <strong>previous semester copy of grades</strong>. 1st years joining in the 2nd semester give their 1st semester grades.</p>
            <?php elseif ($firstYearFirstSemester && ($application['vacancy_id'] !== null || $application['committee_id'] !== null)) : ?>
                <p>You are a 1st year in your 1st semester, so you have no grades yet. We only need your <strong>current COR</strong>.</p>
            <?php else : ?>
                <p>For General Membership we only need your <strong>current COR</strong>.</p>
            <?php endif; ?>
            <p class="text-muted">Upload each file, then choose Save and continue. PDF, JPG or PNG, up to <?= e($maxMb) ?> MB each.</p>
        </div>
    </div>
    <?php foreach ($documentsToAsk as $type) : ?>
        <?php
        $label = \App\Services\MembershipService::documentLabel($type);
        $mine = array_values(array_filter($documents, static fn (array $d): bool => $d['doc_type'] === $type));
        $isRequired = in_array($type, $requiredDocuments, true);
        $hasUsable = array_filter($mine, static fn (array $d): bool => $d['verification_status'] !== 'rejected') !== [];
        ?>
        <section class="plain-section wizard-doc" aria-labelledby="doc-<?= e($type) ?>">
            <div class="card__body">
                <h3 id="doc-<?= e($type) ?>"><?= e($label) ?><?= $isRequired ? ' <span class="field__required" aria-hidden="true">*</span>' : '' ?></h3>
                <?php if ($mine !== []) : ?>
                    <ul class="doc-list">
                        <?php foreach ($mine as $document) : ?>
                            <li class="doc-list__item">
                                <div class="doc-list__name">
                                    <a href="<?= e(application_file_url($document)) ?>"><?= e($document['original_name']) ?></a>
                                    <?php if (!$isDraft) : ?>
                                        <?= status_badge($document['verification_status']) ?>
                                    <?php endif; ?>
                                    <?php if ($document['verification_remarks'] !== null) : ?>
                                        <p class="field__help">Remarks: <?= e($document['verification_remarks']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php if ($isDraft || $document['verification_status'] === 'pending') : ?>
                                    <form method="post" action="<?= e(route('apply.document_remove', ['id' => $id, 'documentId' => $document['document_id']])) ?>" data-confirm="This file will be deleted." data-confirm-title="Remove this document?" data-confirm-action="Remove">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn--ghost btn--sm">Remove<span class="sr-only"> <?= e($document['original_name']) ?></span></button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <form method="post" enctype="multipart/form-data" action="<?= e(route('apply.document', ['id' => $id, 'type' => $type])) ?>">
                    <?= csrf_field() ?>
                    <?= $this->partial('membership/apply/_doc_upload', [
                        'type' => $type,
                        'label' => $hasUsable && $type !== 'certificate' ? 'Replace file' : 'Add a file',
                        'maxMb' => $maxMb,
                    ]) ?>
                    <button type="submit" class="btn btn--secondary btn--sm">Upload</button>
                </form>
            </div>
        </section>
    <?php endforeach; ?>
    <form class="plain-section" method="post" action="<?= e(route('apply.step', ['id' => $id, 'step' => 5])) ?>">
        <?= csrf_field() ?>
        <?= $this->partial('membership/apply/_actions', compact('application', 'step')) ?>
    </form>
</div>
