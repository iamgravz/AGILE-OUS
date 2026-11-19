<?php /** @var list<array{label: string, url?: string}> $breadcrumbs */ ?>
<nav aria-label="Breadcrumb">
    <ol class="breadcrumb__list">
        <?php foreach ($breadcrumbs as $crumb) : ?>
            <li class="breadcrumb__item">
                <?php if (isset($crumb['url'])) : ?>
                    <a href="<?= e($crumb['url']) ?>"><?= e($crumb['label']) ?></a>
                <?php else : ?>
                    <span aria-current="page"><?= e($crumb['label']) ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
