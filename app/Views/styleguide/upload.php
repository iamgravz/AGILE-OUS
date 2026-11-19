<?php
/**
 * @var \App\Core\View $this
 * @var int $maxMb
 */
?>
<?php $this->start('title') ?>Style Guide: upload test<?php $this->end() ?>
<?php $this->start('page_title') ?>Upload test<?php $this->end() ?>
<?php $this->start('head') ?><link rel="stylesheet" href="<?= e(asset('css/styleguide.css')) ?>"><?php $this->end() ?>
<h1>Upload test</h1>
<p class="sg-note">Available only when <code>APP_ENV=local</code>. Accepted files are checked, saved, then deleted right away.</p>
<div class="account-sections">
    <form class="card" method="post" action="<?= e(route('styleguide.upload')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="card__header"><h2 class="card__title">With the browser check</h2></div>
        <div class="card__body">
            <?= $this->partial('partials/upload_field', [
                'name' => 'checked_file',
                'label' => 'File',
                'types' => ['pdf', 'jpg', 'jpeg', 'png'],
                'maxMb' => $maxMb,
                'help' => "PDF, JPG or PNG, up to {$maxMb} MB.",
            ]) ?>
            <button type="submit" class="btn btn--primary">Upload</button>
        </div>
    </form>
    <form class="card" method="post" action="<?= e(route('styleguide.upload')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="card__header"><h2 class="card__title">Server check only</h2></div>
        <div class="card__body">
            <?= $this->partial('partials/upload_field', [
                'name' => 'server_file',
                'label' => 'File',
                'types' => ['pdf', 'jpg', 'jpeg', 'png'],
                'maxMb' => $maxMb,
                'clientCheck' => false,
                'help' => 'The browser lets any file through here, so this shows what the server decides.',
            ]) ?>
            <button type="submit" class="btn btn--primary">Upload</button>
        </div>
    </form>
</div>
