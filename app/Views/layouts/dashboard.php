<?php
/**
 * The admin shell: sidebar with an icon rail, top bar, page header.
 * Sections: title, page_eyebrow (optional), page_title, page_actions (optional), content.
 *
 * @var \App\Core\View $this
 * @var list<array{label: string, url?: string}> $breadcrumbs (optional)
 * @var array{name: string, role: string} $currentUser (optional, only the style guide passes it)
 * @var list<array<string, mixed>> $notifications (optional, only the style guide passes it)
 * @var list<array<string, mixed>> $sidebarMenu (optional, only the style guide passes it)
 */
$pageTitle = $this->section('page_title');
$user = current_user();
$account = $currentUser ?? ($user === null ? ['name' => 'Account', 'role' => ''] : [
    'name' => trim($user['first_name'] . ' ' . $user['last_name']),
    'role' => $user['role_name'],
]);
$bell = isset($notifications) || $user === null
    ? ['notifications' => $notifications ?? []]
    : ['live' => true, 'unread' => (new \App\Services\NotificationService())->unreadCount((int) $user['user_id'])];
$menu = $sidebarMenu ?? sidebar_menu();

// The eyebrow names the sidebar group this page belongs to.
$eyebrow = $this->section('page_eyebrow');
foreach ($menu as $group) {
    foreach ($group['items'] as $item) {
        if ($eyebrow === '' && is_current_path($item['url'])) {
            $eyebrow = e($group['heading']);
        }
    }
}
$crumbs = $breadcrumbs ?? [
    ['label' => 'Home', 'url' => route('dashboard')],
    ['label' => html_entity_decode(strip_tags($pageTitle), ENT_QUOTES)],
];
$avatar = static function () use ($user, $account): string {
    if (($user['photo_path'] ?? null) !== null) {
        return '<img class="avatar" src="' . e(route('files.show', ['type' => 'avatar', 'id' => $user['user_id']])) . '" alt="" width="32" height="32">';
    }

    return initials_avatar($account['name']);
};
?>
<!doctype html>
<html lang="en">
<head>
    <?= $this->partial('partials/head') ?>
</head>
<body>
    <a class="skip-link" href="#main">Skip to main content</a>

    <div class="app-shell">
        <aside id="sidebar" class="sidebar">
            <div class="sidebar__top">
                <a class="brand sidebar__brand" href="/">
                    <img class="brand__logo" src="<?= e(asset('img/logo.png')) ?>" alt="" width="40" height="40">
                    <span class="brand__name">AGILE-OUS</span>
                </a>
                <button type="button" class="icon-btn sidebar__close" data-sidebar-close aria-label="Close menu"><?= icon('close') ?></button>
            </div>
            <nav class="sidebar__nav" aria-label="Dashboard">
                <?php foreach ($menu as $group) : ?>
                    <h2 class="sidebar__heading"><?= e($group['heading']) ?></h2>
                    <ul class="sidebar__list">
                        <?php foreach ($group['items'] as $item) : ?>
                            <li>
                                <a class="sidebar__link" href="<?= e($item['url']) ?>" title="<?= e($item['label']) ?>"<?= is_current_path($item['url']) ? ' aria-current="page"' : '' ?>>
                                    <?= icon($item['icon']) ?>
                                    <span><?= e($item['label']) ?></span>
                                    <?php $badge = isset($item['badge']) ? sidebar_badge($item['badge']) : 0; ?>
                                    <?php if ($badge > 0) : ?>
                                        <span class="sidebar__badge"><?= e($badge) ?><span class="sr-only"> waiting</span></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>
            </nav>
            <a class="sidebar__user" href="/account" title="<?= e($account['name']) ?>">
                <?= $avatar() ?>
                <span class="sidebar__user-text">
                    <?= e($account['name']) ?>
                    <?php if ($account['role'] !== '') : ?>
                        <span class="sidebar__user-role"><?= e($account['role']) ?></span>
                    <?php endif; ?>
                </span>
            </a>
        </aside>
        <div class="sidebar-backdrop" data-sidebar-backdrop hidden></div>

        <div class="app-content">
            <header class="topbar">
                <button type="button" class="icon-btn sidebar-toggle" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false" aria-label="Open menu">
                    <?= icon('menu') ?>
                </button>
                <div class="topbar__path"><?= $this->partial('partials/breadcrumbs', ['breadcrumbs' => $crumbs]) ?></div>
                <?php if (\App\Services\SearchService::modules() !== []) : ?>
                    <form class="topbar__search" method="get" action="<?= e(route('search')) ?>" role="search" data-no-guard>
                        <label class="sr-only" for="topbar-search">Search</label>
                        <input class="input" id="topbar-search" name="q" type="search" maxlength="100" placeholder="Search" value="<?= e(is_current_path(route('search')) && is_string($_GET['q'] ?? null) ? $_GET['q'] : '') ?>">
                        <kbd class="topbar__hint" aria-hidden="true">/</kbd>
                    </form>
                <?php endif; ?>
                <div class="topbar__tools">
                    <?= $this->partial('partials/theme_toggle') ?>
                    <?= $this->partial('partials/notification_dropdown', ['id' => 'topbar-notifications'] + $bell) ?>
                    <a class="icon-btn topbar__mail" href="/my-submissions" aria-label="My Submissions" title="My Submissions"><?= icon('inbox') ?></a>
                    <div class="dropdown">
                        <button type="button" class="user-menu__button" data-dropdown aria-expanded="false" aria-controls="user-menu">
                            <?= $avatar() ?>
                            <span class="sr-only">Account menu for <?= e($account['name']) ?></span>
                        </button>
                        <div id="user-menu" class="dropdown__menu" hidden>
                            <?php if ($account['role'] !== '') : ?>
                                <p class="user-menu__role"><?= e($account['role']) ?></p>
                            <?php endif; ?>
                            <ul class="dropdown__list">
                                <li><a class="dropdown__item" href="/account">My Account</a></li>
                                <li>
                                    <form method="post" action="/logout">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="dropdown__item">Log out</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </header>

            <main id="main" class="app-main" tabindex="-1">
                <?php if ($pageTitle !== '') : ?>
                    <div class="page-header">
                        <div>
                            <?php if ($eyebrow !== '') : ?>
                                <p class="eyebrow"><?= $eyebrow ?></p>
                            <?php endif; ?>
                            <h1><?= $pageTitle ?></h1>
                        </div>
                        <?php if ($this->section('page_actions') !== '') : ?>
                            <div class="cluster"><?= $this->section('page_actions') ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?= $this->partial('partials/flash') ?>
                <?= $this->partial('partials/page_content') ?>
            </main>
            <footer class="app-footer"><a href="/privacy">Privacy Notice</a></footer>
        </div>
    </div>

    <?= $this->partial('partials/overlays') ?>
</body>
</html>
