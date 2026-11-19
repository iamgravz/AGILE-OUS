<?php
/**
 * With $live, the list loads through fetch when opened. Otherwise $notifications is rendered as given.
 *
 * @var string $id unique per page
 * @var bool $live (optional)
 * @var int $unread (optional, used with $live)
 * @var list<array{title: string, time: string, link: string, unread: bool}> $notifications (optional)
 * @var string $class extra class for the dropdown wrapper (optional)
 */
$live ??= false;
$notifications ??= [];
$unread = $live ? ($unread ?? 0) : count(array_filter($notifications, static fn (array $item): bool => $item['unread']));
$buttonLabel = $unread > 0 ? "Notifications, {$unread} unread" : 'Notifications';
?>
<div class="dropdown <?= e($class ?? '') ?>">
    <button
        type="button"
        class="icon-btn"
        data-dropdown
        aria-expanded="false"
        aria-controls="<?= e($id) ?>"
        aria-label="<?= e($buttonLabel) ?>"
        <?= $live ? 'data-notifications-url="' . e(route('notifications.latest')) . '"' : '' ?>
    >
        <?= icon('bell') ?>
        <span class="bell-count" data-notification-count aria-hidden="true"<?= $unread > 0 ? '' : ' hidden' ?>><?= e($unread) ?></span>
    </button>
    <div id="<?= e($id) ?>" class="dropdown__menu notification-menu" hidden>
        <div class="notification-menu__header">
            <h2 class="dropdown__heading">Notifications</h2>
            <?php if ($live) : ?>
                <button type="button" class="btn btn--ghost btn--sm" data-notifications-read-all<?= $unread > 0 ? '' : ' hidden' ?>>Mark all read</button>
            <?php endif; ?>
        </div>
        <?php if ($live) : ?>
            <p class="notification-menu__empty" data-notification-status role="status">Loading...</p>
            <ul class="dropdown__list" data-notification-list hidden></ul>
            <a class="notification-menu__all" href="<?= e(route('notifications')) ?>">View all notifications</a>
        <?php elseif ($notifications === []) : ?>
            <p class="notification-menu__empty">No notifications yet.</p>
        <?php else : ?>
            <ul class="dropdown__list">
                <?php foreach ($notifications as $notification) : ?>
                    <li>
                        <a class="notification <?= $notification['unread'] ? 'notification--unread' : '' ?>" href="<?= e($notification['link']) ?>">
                            <?php if ($notification['unread']) : ?>
                                <span class="notification__unread">New</span>
                            <?php endif; ?>
                            <span class="notification__title"><?= e($notification['title']) ?></span>
                            <span class="notification__time"><?= e($notification['time']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
