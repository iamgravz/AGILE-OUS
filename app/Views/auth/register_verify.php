<?php
/**
 * Create your account, step 2 of 3: the emailed code, in the apply page's look.
 *
 * @var \App\Core\View $this
 */
?>
<?php $this->start('title') ?>Enter your code<?php $this->end() ?>
<?php ob_start(); ?>
<div class="card glass robot-card">
    <div class="card__body">
        <form method="post" action="<?= e(route('register.verify')) ?>">
            <?= csrf_field() ?>
            <?= $this->partial('partials/form_field', ['name' => 'code', 'label' => 'Code', 'autocomplete' => 'one-time-code', 'help' => 'The 6 digits from the email. It expires in 15 minutes.']) ?>
            <button type="submit" class="btn btn--primary btn--block">Confirm my code</button>
        </form>
        <div class="login-page__links">
            <form method="post" action="<?= e(route('register.resend')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn--ghost btn--sm">Send a new code</button>
            </form>
            <form method="post" action="<?= e(route('register.restart')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn--ghost btn--sm">Use a different student number</button>
            </form>
        </div>
    </div>
</div>
<?php $form = (string) ob_get_clean(); ?>
<?= $this->partial('auth/_robot_page', [
    'heading' => 'Enter your code',
    'lead' => 'Step 2 of 3. If this student number has an approved AGILE membership, we sent a 6-digit code to the email used in the application.',
    'bubble' => 'Check your email. I sent you a 6-digit code!',
    'form' => $form,
]) ?>
