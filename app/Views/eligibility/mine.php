<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed>|null $mine
 * @var int $maxMb
 */
use App\Services\EligibilityService;

$consentError = field_error('privacy_consent');
?>
<?php $this->start('title') ?>My eligibility<?php $this->end() ?>
<?php $this->start('form_side') ?><?= $this->partial('partials/side_panel', ['blocks' => [['title' => 'What to submit', 'items' => ['Your grades for the term', 'Your proof of enrollment', 'PDF, JPG or PNG files']], ['title' => 'How you are judged', 'text' => 'You are eligible with no failed subject and official enrollment for the whole semester. A person always reviews this.'], ['title' => 'Need help?', 'text' => 'Email the MSW Committee and we will get back to you.', 'contact' => true]]]) ?><?php $this->end() ?>
<?php $this->start('page_title') ?>My eligibility<?php $this->end() ?>

<?php if ($mine === null) : ?>
    <div class="empty-state">
        <p class="empty-state__text">Your screening appears here if you hold an officer role when a term is screened.</p>
    </div>
<?php else : ?>
    <?php $screening = $mine['screening']; $term = $mine['term']; ?>
    <?php $this->start('page_actions') ?><?= status_badge($screening['result']) ?><?php $this->end() ?>
    <div class="case-sections">
        <section class="card" aria-labelledby="mine-title">
            <div class="card__header"><h2 class="card__title" id="mine-title"><?= e(EligibilityService::termLabel($term)) ?></h2></div>
            <div class="card__body">
                <dl class="detail-list member-details">
                    <div><dt>Deadline</dt><dd><?= e(date_ph($term['screening_deadline'])) ?></dd></div>
                    <div><dt>Last day after grace days</dt><dd><?= e(date_ph($mine['lastUploadDate'])) ?></dd></div>
                    <div><dt>Status</dt><dd><?= status_badge($screening['result']) ?></dd></div>
                </dl>
                <?php if ($mine['documents'] !== []) : ?>
                    <h3 class="member-section">Your documents</h3>
                    <ul class="member-applications">
                        <?php foreach ($mine['documents'] as $document) : ?>
                            <li><a href="<?= e(route('files.show', ['type' => 'eligibility_document', 'id' => (int) $document['document_id']])) ?>"><?= e($document['original_name']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($mine['canUpload']) : ?>
            <form class="plain-section" method="post" action="<?= e(route('my_eligibility.documents')) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="card__header"><h2 class="card__title"><?= $mine['documents'] === [] ? 'Submit your documents' : 'Replace your documents' ?></h2></div>
                <div class="card__body">
                    <?= $this->partial('partials/upload_field', [
                        'name' => 'documents',
                        'label' => 'Grades and proof of official enrollment',
                        'types' => ['pdf', 'jpg', 'jpeg', 'png'],
                        'maxMb' => $maxMb,
                        'multiple' => true,
                        'help' => 'Up to ' . EligibilityService::MAX_DOCUMENTS . " PDF, JPG or PNG files, {$maxMb} MB each." . ($mine['documents'] === [] ? '' : ' These replace the files above.'),
                    ]) ?>
                    <div class="field <?= $consentError !== null ? 'field--error' : '' ?>">
                        <label class="check">
                            <input type="checkbox" name="privacy_consent" value="1" required<?= $consentError !== null ? ' aria-invalid="true" aria-describedby="privacy-consent-error"' : '' ?>>
                            <span>I have read the <a href="<?= e(route('privacy')) ?>">Privacy Notice</a> and agree to the processing of my personal data under the Data Privacy Act of 2012 (RA 10173). <span class="field__required" aria-hidden="true">*</span></span>
                        </label>
                        <?php if (config('app.ai.provider') !== 'none') : ?>
                            <p class="field__help">Your reviewer may send these documents to an outside AI provider, which suggests values from them. A person always confirms the result.</p>
                        <?php endif; ?>
                        <?php if ($consentError !== null) : ?>
                            <p class="field__error" id="privacy-consent-error"><?= icon('alert') ?> <?= e($consentError) ?></p>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn--primary">Submit documents</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>
