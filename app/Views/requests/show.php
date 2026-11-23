<?php
/**
 * One service request and its thread, for its owner or MSW staff.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $request
 * @var list<array<string, mixed>> $messages
 * @var string $code
 */
use App\Services\ServiceRequestService;

$tones = ['open' => 'info', 'in_progress' => 'warning', 'resolved' => 'success', 'closed' => 'neutral', 'cancelled' => 'neutral'];
$isOwner = (int) $request['user_id'] === current_user_id();
$active = in_array($request['status'], ServiceRequestService::ACTIVE, true);
?>
<?php $this->start('title') ?><?= e($code) ?><?php $this->end() ?>
<?php $this->start('page_eyebrow') ?><?= e($code) ?><?php $this->end() ?>
<?php $this->start('page_title') ?><?= e($request['subject']) ?><?php $this->end() ?>
<?php $this->start('page_actions') ?>
<span class="badge badge--<?= e($tones[$request['status']]) ?>"><?= e(ServiceRequestService::STATUSES[$request['status']]) ?></span>
<?php $this->end() ?>

<p class="timeline__meta">
    <?= e(ServiceRequestService::TYPES[$request['type']]) ?> · sent <?= e(datetime_ph((string) $request['created_at'])) ?>
    <?= $isOwner ? '' : ' by ' . e($request['requester_name']) ?>
    <?= $request['assigned_name'] !== null ? ' · handled by ' . e($request['assigned_name']) : '' ?>
</p>

<div class="account-sections">
    <section class="plain-section" aria-labelledby="thread-title">
        <div class="card__header"><h2 class="card__title" id="thread-title">Messages</h2></div>
        <div class="card__body">
            <ol class="request-thread">
                <?php foreach ($messages as $message) : ?>
                    <li class="request-thread__item<?= (int) $message['from_staff'] === 1 ? ' request-thread__item--staff' : '' ?>">
                        <p class="request-thread__meta"><strong><?= e($message['author_name']) ?></strong><?= (int) $message['from_staff'] === 1 ? ' · MSW Committee' : '' ?> · <?= e(datetime_ph((string) $message['created_at'])) ?></p>
                        <p class="request-thread__body"><?= nl2br(e($message['body'])) ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>

    <?php if ($active) : ?>
        <form class="plain-section" method="post" action="<?= e(route('requests.reply', ['id' => (int) $request['request_id']])) ?>" id="reply">
            <?= csrf_field() ?>
            <div class="card__header"><h2 class="card__title">Reply</h2></div>
            <div class="card__body">
                <div class="field">
                    <label class="field__label" for="body">Message</label>
                    <textarea class="textarea" id="body" name="body" rows="4" maxlength="3000" required></textarea>
                </div>
                <div class="cluster">
                    <button type="submit" class="btn btn--primary">Send reply</button>
                    <?php if ($isOwner) : ?>
                        <button type="submit" class="btn btn--ghost" formaction="<?= e(route('requests.cancel', ['id' => (int) $request['request_id']])) ?>" formnovalidate data-confirm="The MSW Committee stops working on it." data-confirm-title="Cancel this request?" data-confirm-action="Cancel request">Cancel request</button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    <?php else : ?>
        <p class="text-muted">This request is <?= e(strtolower(ServiceRequestService::STATUSES[$request['status']])) ?>. <?= $isOwner ? 'Send a new request if you need more help.' : '' ?></p>
    <?php endif; ?>
</div>
