<?php
/**
 * @var \App\Core\View $this
 * @var string $title
 */
?>
<?php $this->start('title') ?><?= e($title) ?><?php $this->end() ?>
<?php $this->start('page_title') ?><?= e($title) ?><?php $this->end() ?>
<div class="empty-state">
    <p class="empty-state__text">This page arrives in a later build step.</p>
    <a class="btn btn--secondary btn--sm" href="<?= e(route('dashboard')) ?>">Go to my dashboard</a>
</div>
