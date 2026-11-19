<?php
/**
 * A page's content. A page with a form_side section gets its form column on the left and the panel on the right.
 *
 * @var \App\Core\View $this
 */
$side = $this->section('form_side');
?>
<?php if ($side === '') : ?>
    <?= $this->section('content') ?>
<?php else : ?>
    <div class="form-layout">
        <div class="form-layout__main"><?= $this->section('content') ?></div>
        <aside class="form-layout__side" aria-label="Help for this page"><?= $side ?></aside>
    </div>
<?php endif; ?>
