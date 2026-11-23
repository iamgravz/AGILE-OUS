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
