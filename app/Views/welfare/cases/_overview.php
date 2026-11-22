<?php
/**
 * @var array<string, mixed> $case
 * @var bool $isClosed
 * @var list<array<string, mixed>> $handlers
 * @var list<array<string, mixed>> $categories
 */
use App\Services\WelfareService;

$id = (int) $case['case_id'];
$isGuest = $case['user_id'] === null;
$submitterType = $isGuest ? 'Guest' : 'Member';
$remarksError = field_error('classify_remarks');
$assignable = array_values(array_filter($handlers, static fn (array $user): bool => (int) $user['user_id'] === current_user_id() || $user['role_slug'] === 'president'));
?>
<div class="detail-layout">
    <section class="card detail-layout__side" aria-labelledby="case-summary">
        <div class="card__header"><h2 class="card__title" id="case-summary"><?= e($case['subject']) ?></h2></div>
        <div class="card__body">
            <dl class="detail-list member-details">
                <div><dt>Category</dt><dd><?= e($case['category_name']) ?></dd></div>
                <div><dt>Handler</dt><dd><?= e($case['handler_name'] ?? 'Unassigned') ?></dd></div>
                <div><dt>Submitted</dt><dd><?= e(datetime_ph($case['submitted_at'])) ?></dd></div>
                <?php if ((int) $case['student_marked_urgent'] === 1) : ?>
                    <div><dt>Submitter marked this as</dt><dd>Urgent</dd></div>
                <?php endif; ?>
                <div><dt>Acknowledged</dt><dd><?= e($case['acknowledged_at'] === null ? 'Not yet' : datetime_ph($case['acknowledged_at'])) ?></dd></div>
                <?php if ($case['related_case_id'] !== null) : ?>
                    <div><dt>Related case</dt><dd><a href="<?= e(route('welfare.show', ['id' => (int) $case['related_case_id']])) ?>"><code><?= e($case['related_code']) ?></code></a></dd></div>
                <?php endif; ?>
                <div><dt>Submitter type</dt><dd><?= e($submitterType) ?></dd></div>
                <div><dt>Name</dt><dd><?= e($isGuest ? $case['guest_name'] : $case['submitter_name']) ?></dd></div>
                <div><dt>Email</dt><dd><?= e($isGuest ? $case['guest_email'] : $case['submitter_email']) ?></dd></div>
                <?php $studentNo = $isGuest ? $case['guest_student_no'] : $case['submitter_student_no']; ?>
                <?php if ($studentNo !== null) : ?>
                    <div><dt>Student number</dt><dd><?= e($studentNo) ?></dd></div>
                <?php endif; ?>
                <?php $details = $case['submitter_details'] ?? ['contact_no' => '', 'year_level' => '', 'section' => '']; ?>
                <?php if ($details['year_level'] !== '' || $details['section'] !== '') : ?>
                    <div><dt>Year and section</dt><dd><?= e(trim($details['year_level'] . ', ' . $details['section'], ', ')) ?></dd></div>
                <?php endif; ?>
                <?php if ($details['contact_no'] !== '') : ?>
                    <div><dt>Contact number</dt><dd><a href="tel:<?= e($details['contact_no']) ?>"><?= e($details['contact_no']) ?></a></dd></div>
                <?php endif; ?>
                <div><dt>Description</dt><dd><?= nl2br(e($case['description'])) ?></dd></div>
            </dl>
            <?php if (!$isClosed && $case['acknowledged_at'] === null && can('welfare.handle')) : ?>
                <form class="member-reissue" method="post" action="<?= e(route('welfare.acknowledge', ['id' => $id])) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--primary">Acknowledge</button>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!$isClosed && can('welfare.assign_close')) : ?>
        <form class="plain-section" method="post" action="<?= e(route('welfare.assign', ['id' => $id])) ?>">
            <?= csrf_field() ?>
            <div class="card__header"><h2 class="card__title">Handler</h2></div>
            <div class="card__body">
                <div class="field">
                    <label class="field__label" for="handler_id"><?= $case['assigned_to'] === null ? 'Assign to' : 'Reassign to' ?> <span class="field__required" aria-hidden="true">*</span></label>
                    <select class="select" id="handler_id" name="handler_id" required>
                        <option value="">Choose a handler</option>
                        <?php foreach ($assignable as $user) : ?>
                            <option value="<?= e($user['user_id']) ?>"<?= (int) $case['assigned_to'] === (int) $user['user_id'] ? ' selected' : '' ?>><?= e($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['role_name'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn--secondary">Save handler</button>
            </div>
        </form>

        <form class="plain-section" method="post" action="<?= e(route('welfare.classify', ['id' => $id])) ?>">
            <?= csrf_field() ?>
            <div class="card__header"><h2 class="card__title">Classification and priority</h2></div>
            <div class="card__body">
                <div class="grid grid--2">
                    <div class="field">
                        <label class="field__label" for="category_id">Category <span class="field__required" aria-hidden="true">*</span></label>
                        <select class="select" id="category_id" name="category_id" required>
                            <?php foreach ($categories as $category) : ?>
                                <option value="<?= e($category['category_id']) ?>"<?= (int) $case['category_id'] === (int) $category['category_id'] ? ' selected' : '' ?>><?= e($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field__label" for="priority">Priority <span class="field__required" aria-hidden="true">*</span></label>
                        <select class="select" id="priority" name="priority" required>
                            <?php foreach (WelfareService::PRIORITIES as $priority) : ?>
                                <option value="<?= e($priority) ?>"<?= $case['priority'] === $priority ? ' selected' : '' ?>><?= e(ucfirst($priority)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="field <?= $remarksError !== null ? 'field--error' : '' ?>">
                    <label class="field__label" for="classify_remarks">Remarks <span class="field__required" aria-hidden="true">*</span></label>
                    <textarea class="textarea applications-actions__remarks" id="classify_remarks" name="classify_remarks" maxlength="2000" required aria-describedby="classify_remarks-help<?= $remarksError !== null ? ' classify_remarks-error' : '' ?>"<?= $remarksError !== null ? ' aria-invalid="true"' : '' ?>></textarea>
                    <p class="field__help" id="classify_remarks-help">Saved as an internal note. The submitter does not see it.</p>
                    <?php if ($remarksError !== null) : ?>
                        <p class="field__error" id="classify_remarks-error"><?= icon('alert') ?> <?= e($remarksError) ?></p>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn--secondary">Save classification</button>
            </div>
        </form>
    <?php endif; ?>
</div>
