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
// One bar (Nov 23, 2026): the eligibility notice, then new notifications, rolling upward a line at a time.
$unread = array_values(array_filter((new \App\Services\NotificationService())->latest((int) $user['user_id'], 10), static fn (array $n): bool => $n['read_at'] === null));
$lines = [];

if ($notice !== null) {
    $lines[] = ['text' => (string) $notice['message'], 'url' => route('my_eligibility'), 'linkText' => 'My Eligibility'];
}

foreach (array_slice($unread, 0, 5) as $n) {
    $lines[] = ['text' => 'New: ' . $n['title'], 'url' => (string) ($n['link'] ?? '') !== '' ? (string) $n['link'] : route('notifications'), 'linkText' => null];
}

// A warning or problem with eligibility sets the colour; otherwise it is an information bar.
$tone = $notice !== null && in_array($notice['tone'], ['warning', 'danger'], true) ? $notice['tone'] : ($unread !== [] ? 'info' : ($notice['tone'] ?? 'info'));
?>
<?php if ($lines !== []) : ?>
    <?= $this->partial('partials/rolling_notice', [
        'tone' => $tone,
        'lines' => $lines,
        'asideUrl' => $unread !== [] ? route('account') . '#notifications' : null,
        'asideText' => $unread !== [] ? count($unread) . ' new · Settings' : null,
    ]) ?>
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
