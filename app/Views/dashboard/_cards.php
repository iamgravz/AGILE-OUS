<?php
/**
 * The top of every role dashboard: greeting, what needs action, eligibility notice, figures.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $user
 * @var array<string, mixed> $dash
 */
$notice = $dash['eligibilityNotice'] ?? null;
$tasks = $dash['tasks'] ?? [];
$isInternal = ($user['role_slug'] ?? '') !== 'general_member';
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$today = date('l, ') . date_ph(date('Y-m-d'));
$semester = $dash['semester'] ?? null;
?>
<p class="dashboard-intro"><?= e($greeting . ', ' . $user['first_name']) ?>. <?= e($today) ?><?= $semester === null ? '' : ' · AY ' . e($semester) ?></p>
<?php
// New notifications roll across the top (Nov 23, 2026), with the way to choose which ones arrive.
$unread = array_values(array_filter((new \App\Services\NotificationService())->latest((int) $user['user_id'], 10), static fn (array $n): bool => $n['read_at'] === null));
?>
<?php if ($unread !== []) : ?>
    <?= $this->partial('partials/rolling_notice', [
        'tone' => 'info',
        'message' => count($unread) === 1 ? '1 new notification:' : count($unread) . ' new notifications:',
        'items' => array_map(static fn (array $n): array => ['text' => (string) $n['title'], 'url' => (string) ($n['link'] ?? '') !== '' ? (string) $n['link'] : route('notifications')], array_slice($unread, 0, 5)),
        'linkUrl' => route('account') . '#notifications',
        'linkText' => '· Notification settings',
    ]) ?>
<?php endif; ?>
<?php if ($notice !== null) : ?>
    <?= $this->partial('partials/rolling_notice', ['tone' => $notice['tone'], 'message' => $notice['message'], 'linkUrl' => route('my_eligibility'), 'linkText' => 'My Eligibility']) ?>
<?php endif; ?>
<?php if ($tasks !== [] || $isInternal) : ?>
    <section class="card dashboard-section" aria-labelledby="dashboard-tasks">
        <div class="card__header"><h2 class="card__title" id="dashboard-tasks"><?= $isInternal ? 'Needs your action' : 'Pending tasks' ?></h2></div>
        <div class="card__body">
            <?php if ($tasks === []) : ?>
                <p class="text-muted">Nothing needs your action right now.</p>
            <?php else : ?>
                <ul class="case-list case-list--rows">
                    <?php foreach ($tasks as $task) : ?>
                        <li><a href="<?= e($task['url']) ?>"><?= e($task['label']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
<?= $this->partial('dashboard/_numbers', ['cards' => $dash['cards'] ?? [], 'applicationStatuses' => $dash['applicationStatuses'] ?? []]) ?>
