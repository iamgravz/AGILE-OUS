<?php
/**
 * Read-only answers and documents, shared by the review step and the application page.
 *
 * @var array<string, mixed> $application
 * @var array<string, mixed> $user
 * @var list<array<string, mixed>> $academicQuestions
 * @var list<array<string, mixed>> $questions
 * @var array<int, string> $answers
 * @var list<array<string, mixed>> $documents
 * @var bool $withEditLinks links back to the wizard's steps
 * @var bool $hideDocuments (optional) for viewers who may not see documents
 */
$section = static function (int $number, string $title) use ($application, $withEditLinks): string {
    $link = '';

    if ($withEditLinks) {
        $url = route('apply.step', ['id' => $application['application_id'], 'step' => $number]);
        $link = sprintf(' <a class="review__edit" href="%s">Edit<span class="sr-only"> %s</span></a>', e($url), e($title));
    }

    return sprintf('<h3 class="review__heading">%s%s</h3>', e($title), $link);
};
?>
<?= $section(1, 'Personal info') ?>
<dl class="detail-list">
    <div><dt>Name</dt><dd><?= e($user['first_name'] . ' ' . $user['last_name']) ?></dd></div>
    <div><dt>Email</dt><dd><?= e($user['email']) ?></dd></div>
    <div><dt>Contact number</dt><dd><?= e($user['contact_no']) ?></dd></div>
</dl>

<?= $section(2, 'Academic info') ?>
<dl class="detail-list">
    <div><dt>Student number</dt><dd><?= e($user['student_no'] ?? 'Not set') ?></dd></div>
    <div><dt>Program</dt><dd>BSIT-OU</dd></div>
    <?php if (($user['section'] ?? null) !== null && $user['section'] !== '') : ?>
        <div><dt>Section</dt><dd><?= e($user['section']) ?></dd></div>
    <?php endif; ?>
    <?php foreach ($academicQuestions as $question) : ?>
        <div><dt><?= e($question['question_text']) ?></dt><dd><?= e($answers[(int) $question['question_id']] ?? 'Not answered') ?></dd></div>
    <?php endforeach; ?>
    <div><dt>Currently enrolled</dt><dd><?= $application['enrollment_confirmed_at'] !== null ? 'Confirmed' : 'Not confirmed' ?></dd></div>
</dl>

<?= $section(3, 'Membership type') ?>
<p><?= e($application['vacancy_title'] ?? 'General Membership') ?></p>

<?php if ($hideDocuments ?? false) {
    return;
} ?>
<?= $section(5, 'Documents') ?>
<?php if ($documents === []) : ?>
    <p>No documents uploaded.</p>
<?php else : ?>
    <ul class="doc-list">
        <?php foreach ($documents as $document) : ?>
            <li class="doc-list__item">
                <div class="doc-list__name">
                    <a href="<?= e(application_file_url($document)) ?>"><?= e($document['original_name']) ?></a>
                    <span class="text-muted">(<?= e(\App\Services\MembershipService::DOCUMENT_TYPES[$document['doc_type']] ?? 'Attachment') ?>)</span>
                    <?php if ($application['status'] !== 'draft') : ?>
                        <?= status_badge($document['verification_status']) ?>
                    <?php endif; ?>
                    <?php if ($document['verification_remarks'] !== null) : ?>
                        <p class="field__help">Remarks: <?= e($document['verification_remarks']) ?></p>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
