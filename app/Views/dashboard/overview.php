<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $user
 * @var array<string, mixed> $dash
 */
?>
<?php $this->start('title') ?>All modules at a glance<?php $this->end() ?>
<?php $this->start('page_eyebrow') ?>Overview<?php $this->end() ?>
<?php $this->start('page_title') ?>All modules at a glance<?php $this->end() ?>
<?php $this->start('page_actions') ?><span class="badge badge--gold">View only</span><?= $this->partial('dashboard/_actions') ?><?php $this->end() ?>
<?php $this->start('head') ?>
<script src="<?= e(asset('vendor/chartjs/chart.umd.js')) ?>" defer></script>
<script src="<?= e(asset('js/dashboards.js')) ?>" defer></script>
<?php $this->end() ?>
<?= $this->partial('dashboard/_numbers', ['cards' => $dash['cards']]) ?>
<?= $this->partial('dashboard/_charts', ['charts' => $dash['charts']]) ?>
<?= $this->partial('dashboard/_overview', ['dash' => $dash]) ?>
