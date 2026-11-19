<?php /** The public top header. */ ?>
    <header class="site-header">
        <div class="container site-header__inner">
            <a class="brand" href="/">
                <img class="brand__logo" src="<?= e(asset('img/logo-small.png')) ?>" alt="" width="48" height="48">
                <span class="brand__text">
                    <span class="brand__name">AGILE-OUS</span>
                    <span class="brand__tag">Academic Guild of IT Learners and Experts<br>PUP Open University System</span>
                </span>
            </a>
            <div class="site-header__tools">
                <?= $this->partial('partials/theme_toggle') ?>
                <button type="button" class="icon-btn nav-toggle" data-nav-toggle aria-controls="site-nav" aria-expanded="false" aria-label="Menu">
                    <?= icon('menu') ?>
                </button>
            </div>
            <nav id="site-nav" class="site-nav" aria-label="Main">
                <ul class="site-nav__list">
                    <?php foreach (config('menu.public', []) as $item) : ?>
                        <li>
                            <a class="site-nav__link" href="<?= e($item['url']) ?>"<?= is_current_path($item['url']) ? ' aria-current="page"' : '' ?>><?= e($item['label']) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="site-nav__auth">
                    <?php if (current_user_id() === null) : ?>
                        <a class="btn btn--ghost btn--sm" href="/login">Member log in</a>
                        <a class="btn btn--primary btn--sm" href="/apply">Apply</a>
                    <?php else : ?>
                        <a class="btn btn--primary btn--sm" href="/dashboard">Dashboard</a>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
    </header>
