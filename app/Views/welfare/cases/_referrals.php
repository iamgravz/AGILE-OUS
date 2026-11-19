<?php
/**
 * @var array<string, mixed> $case
 * @var bool $isClosed
 * @var list<array<string, mixed>> $referrals
 */
use App\Services\WelfareService;

$id = (int) $case['case_id'];
$toError = field_error('referred_to');
$remarksError = field_error('referral_remarks');
?>
<div class="case-sections">
    <?php if (!$isClosed && $case['status'] !== 'resolved' && can('welfare.handle')) : ?>
        <form class="plain-section" method="post" action="<?= e(route('welfare.referrals', ['id' => $id])) ?>">
            <?= csrf_field() ?>
            <div class="card__header"><h2 class="card__title">Add a referral</h2></div>
            <div class="card__body">
                <div class="field <?= $toError !== null ? 'field--error' : '' ?>">
                    <label class="field__label" for="referred_to">Referred to <span class="field__required" aria-hidden="true">*</span></label>
                    <input class="input" id="referred_to" name="referred_to" type="text" maxlength="150" autocomplete="off" required aria-describedby="referred_to-help<?= $toError !== null ? ' referred_to-error' : '' ?>"<?= $toError !== null ? ' aria-invalid="true"' : '' ?>>
                    <p class="field__help" id="referred_to-help">The office or person. Please write the details in the remarks.</p>
                    <?php if ($toError !== null) : ?>
                        <p class="field__error" id="referred_to-error"><?= icon('alert') ?> <?= e($toError) ?></p>
                    <?php endif; ?>
                </div>
                <div class="field <?= $remarksError !== null ? 'field--error' : '' ?>">
                    <label class="field__label" for="referral_remarks">Remarks <span class="field__required" aria-hidden="true">*</span></label>
                    <textarea class="textarea applications-actions__remarks" id="referral_remarks" name="referral_remarks" maxlength="2000" required<?= $remarksError !== null ? ' aria-invalid="true" aria-describedby="referral_remarks-error"' : '' ?>></textarea>
                    <?php if ($remarksError !== null) : ?>
                        <p class="field__error" id="referral_remarks-error"><?= icon('alert') ?> <?= e($remarksError) ?></p>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn--primary">Add referral</button>
            </div>
        </form>
    <?php endif; ?>

    <section class="plain-section" aria-labelledby="case-referrals">
        <div class="card__header"><h2 class="card__title" id="case-referrals">Referrals</h2></div>
        <div class="card__body">
            <?php if ($referrals === []) : ?>
                <p class="text-muted">No referrals have been added.</p>
            <?php else : ?>
                <ul class="case-list">
                    <?php foreach ($referrals as $referral) : ?>
                        <?php $referralId = (int) $referral['referral_id']; ?>
                        <li class="case-entry">
                            <div class="document-card__header">
                                <strong><?= e($referral['referred_to']) ?></strong>
                                <?= status_badge($referral['status']) ?>
                            </div>
                            <p class="timeline__meta"><?= e(datetime_ph($referral['referred_at'])) ?> by <?= e($referral['referred_by_name']) ?></p>
                            <p class="timeline__note"><?= nl2br(e($referral['remarks'])) ?></p>
                            <?php if (!$isClosed && can('welfare.handle')) : ?>
                                <form method="post" action="<?= e(route('welfare.referrals.update', ['id' => $id, 'referralId' => $referralId])) ?>">
                                    <?= csrf_field() ?>
                                    <div class="field">
                                        <label class="field__label" for="referral-status-<?= e($referralId) ?>">Status</label>
                                        <select class="select" id="referral-status-<?= e($referralId) ?>" name="status">
                                            <?php foreach (WelfareService::REFERRAL_STATUSES as $status) : ?>
                                                <option value="<?= e($status) ?>"<?= $referral['status'] === $status ? ' selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn--secondary btn--sm">Update referral<span class="sr-only"> to <?= e($referral['referred_to']) ?></span></button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
</div>
