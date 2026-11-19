<?php
/**
 * @var \App\Core\View $this
 * @var array{data: list<array{id: int, title: string, link: ?string, time: string, read: bool}>, page: int, lastPage: int} $result
 */
$hasUnread = array_filter($result['data'], static fn (array $item): bool => !$item['read']) !== [];
?>
<?php $this->start('title') ?>Notifications<?php $this->end() ?>
<?php $this->start('page_title') ?>Notifications<?php $this->end() ?>
<?php if ($hasUnread) : ?>
    <?php $this->start('page_actions') ?>
    <form method="post" action="<?= e(route('notifications.read_all')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn--secondary btn--sm">Mark all read</button>
    </form>
    <?php $this->end() ?>
<?php endif; ?>

<?php if ($result['data'] === []) : ?>
    <div class="empty-state">
        <p class="empty-state__text">Updates about your account and requests appear here.</p>
    </div>
<?php else : ?>
    <ul class="notification-list">
        <?php foreach ($result['data'] as $item) : ?>
            <li class="card notification-row <?= $item['read'] ? '' : 'notification--unread' ?>">
                <div class="notification-row__text">
                    <?php if (!$item['read']) : ?>
                        <span class="notification__unread">New</span>
                    <?php endif; ?>
                    <?php if ($item['link'] !== null) : ?>
                        <a class="notification__title" href="<?= e($item['link']) ?>"><?= e($item['title']) ?></a>
                    <?php else : ?>
                        <span class="notification__title"><?= e($item['title']) ?></span>
                    <?php endif; ?>
                    <span class="notification__time"><?= e($item['time']) ?></span>
                </div>
                <?php if (!$item['read']) : ?>
                    <form method="post" action="<?= e(route('notifications.read', ['id' => $item['id']])) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn--ghost btn--sm">Mark read<span class="sr-only">: <?= e($item['title']) ?></span></button>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <?= $this->partial('partials/pagination', ['page' => $result['page'], 'lastPage' => $result['lastPage'], 'url' => route('notifications')]) ?>
<?php endif; ?>
