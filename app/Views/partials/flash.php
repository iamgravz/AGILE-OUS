<?php /** @var \App\Core\View $this */ ?>
<?php foreach (['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'] as $key => $tone) : ?>
    <?php $message = flash($key); ?>
    <?php if ($message !== null) : ?>
        <?= $this->partial('partials/alert', ['tone' => $tone, 'message' => $message]) ?>
    <?php endif; ?>
<?php endforeach; ?>
