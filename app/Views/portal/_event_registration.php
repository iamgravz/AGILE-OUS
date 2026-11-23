<?php
/**
 * Registration on an event page (Nov 23, 2026): members register or cancel; guests are asked to sign in.
 *
 * @var array<string, mixed> $post
 * @var array{enabled: bool, open: bool, reason: string, registered: int, capacity: ?int, mine: ?array<string, mixed>, isMember: bool} $registration
 */
if (!$registration['enabled']) {
    return;
}

$postId = (int) $post['post_id'];
$mine = $registration['mine'];
$slots = $registration['capacity'] === null
    ? $registration['registered'] . ' registered'
    : $registration['registered'] . ' of ' . $registration['capacity'] . ' slots taken';
?>
<div class="event-registration">
    <h3 class="docs-card__heading">Registration</h3>
    <p class="timeline__meta"><?= e($slots) ?></p>
    <?php if ($mine !== null) : ?>
        <p class="event-registration__status"><?= icon('check') ?> You are registered<?= $mine['attended_at'] !== null ? ' and checked in' : '' ?>.</p>
        <?php if ($mine['attended_at'] === null && $post['event_start'] > date('Y-m-d H:i:s')) : ?>
            <form method="post" action="<?= e(route('events.cancel', ['id' => $postId])) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn--secondary btn--sm" data-confirm="Your slot goes to someone else." data-confirm-title="Cancel your registration?" data-confirm-action="Cancel registration">Cancel registration</button>
            </form>
        <?php endif; ?>
    <?php elseif (!$registration['open']) : ?>
        <p class="text-muted"><?= e($registration['reason']) ?></p>
    <?php elseif (current_user_id() === null) : ?>
        <a class="btn btn--primary btn--sm" href="<?= e(route('login')) ?>">Sign in to register</a>
        <p class="field__help">For AGILE members. Bring your AGILE ID: officers scan it at the entrance.</p>
    <?php elseif (!$registration['isMember']) : ?>
        <p class="text-muted">Registration is for AGILE members.</p>
    <?php else : ?>
        <form method="post" action="<?= e(route('events.register', ['id' => $postId])) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn--primary btn--sm">Register for this event</button>
        </form>
        <p class="field__help">Bring your AGILE ID: officers scan it at the entrance.</p>
    <?php endif; ?>
</div>
