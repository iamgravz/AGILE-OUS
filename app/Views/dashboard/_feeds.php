<?php
/**
 * Recent activity is this user's own notifications, so nobody sees another role's welfare details. Latest announcements beside it.
 *
 * @var array<string, mixed> $dash
 */
?>
<div class="grid grid--2 dashboard-section">
    <section class="card" aria-labelledby="dashboard-notifications">
        <div class="card__header">
            <h2 class="card__title" id="dashboard-notifications">Recent activity</h2>
            <?php if ($dash['notifications'] !== []) : ?>
                <a class="card__link" href="<?= e(route('notifications')) ?>">View all <?= icon('arrow-right') ?></a>
            <?php endif; ?>
        </div>
        <div class="card__body">
            <?php if ($dash['notifications'] === []) : ?>
                <p class="text-muted">Updates about your work and requests appear here.</p>
            <?php else : ?>
                <ul class="activity">
                    <?php foreach ($dash['notifications'] as $notification) : ?>
                        <li class="activity__item">
                            <?= initials_avatar($notification['title']) ?>
                            <p class="activity__text">
                                <?php if ($notification['link'] !== null) : ?>
                                    <a href="<?= e($notification['link']) ?>"><?= e($notification['title']) ?></a>
                                <?php else : ?>
                                    <strong><?= e($notification['title']) ?></strong>
                                <?php endif; ?>
                                <time class="activity__time" datetime="<?= e(date('c', (int) strtotime($notification['created_at']))) ?>" title="<?= e(datetime_ph($notification['created_at'])) ?>"><?= e(time_ago($notification['created_at'])) ?></time>
                            </p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
    <section class="card" aria-labelledby="dashboard-announcements">
        <div class="card__header"><h2 class="card__title" id="dashboard-announcements">Latest announcements</h2></div>
        <div class="card__body">
            <?php if ($dash['announcements'] === []) : ?>
                <p class="text-muted">No announcements yet.</p>
            <?php else : ?>
                <ul class="case-list case-list--rows">
                    <?php foreach ($dash['announcements'] as $post) : ?>
                        <li>
                            <a href="<?= e(route('posts.show', ['slug' => $post['slug']])) ?>"><?= e($post['title']) ?></a>
                            <span class="timeline__meta"><?= e(date_ph($post['published_at'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
</div>
