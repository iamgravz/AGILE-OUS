<?php
/**
 * Shown only when APP_DEBUG is true.
 *
 * @var \App\Core\View $this
 * @var \Throwable $exception
 */
?>
<?php $this->start('title') ?>Application error<?php $this->end() ?>
<h1><?= e($exception::class) ?></h1>
<p><?= e($exception->getMessage()) ?></p>
<p class="debug-location"><?= e($exception->getFile()) ?>:<?= e($exception->getLine()) ?></p>
<pre class="debug-trace"><?= e($exception->getTraceAsString()) ?></pre>
