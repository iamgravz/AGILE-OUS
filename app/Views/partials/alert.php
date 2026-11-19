<?php
/**
 * @var string $tone success, warning, danger or info
 * @var string $message
 */
$icons = ['success' => 'check-circle', 'warning' => 'alert', 'danger' => 'x-circle', 'info' => 'info'];
?>
<div class="alert alert--<?= e($tone) ?>" role="<?= $tone === 'danger' || $tone === 'warning' ? 'alert' : 'status' ?>">
    <?= icon($icons[$tone]) ?>
    <p class="alert__text"><?= e($message) ?></p>
    <button type="button" class="icon-btn" data-dismiss aria-label="Dismiss"><?= icon('close') ?></button>
</div>
