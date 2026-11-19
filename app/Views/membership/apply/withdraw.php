<?php
/**
 * @var \App\Core\View $this
 * @var string $token
 */
?>
<?php $this->start('title') ?>Withdraw your application<?php $this->end() ?>
<?php $this->start('form_side') ?><?= $this->partial('partials/side_panel', ['blocks' => [['title' => 'What withdrawing does', 'text' => 'Your application stops being reviewed. You can apply again at any time.'], ['title' => 'Need help?', 'text' => 'Email the MSW Committee and we will get back to you.', 'contact' => true]]]) ?><?php $this->end() ?>
<div class="welfare-page">
    <h1>Withdraw your application</h1>
    <p>If you withdraw, the MSW Committee stops reviewing this application. This cannot be undone, but you can apply again later.</p>
    <form method="post" action="<?= e(route('apply.withdraw')) ?>" data-confirm="The MSW Committee will stop reviewing this application." data-confirm-title="Withdraw this application?" data-confirm-action="Withdraw">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <button type="submit" class="btn btn--danger">Withdraw my application</button>
    </form>
    <p class="welfare-notice"><a href="<?= e(route('track')) ?>">Keep my application and go to Track Status</a></p>
</div>
