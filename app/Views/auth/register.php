<?php
/**
 * Create your account, step 1 of 3, in the apply page's look (the navy stage, Iskobot on the card).
 *
 * @var \App\Core\View $this
 */
?>
<?php $this->start('title') ?>Create your account<?php $this->end() ?>
<?php ob_start(); ?>
<form class="card glass robot-card" method="post" action="<?= e(route('register')) ?>">
    <?= csrf_field() ?>
    <div class="card__body">
        <div class="alert alert--info" role="note">
            <?= icon('info') ?>
            <div class="alert__text">
                <strong>With an AGILE account, you can:</strong>
                <ul class="register-perks">
                    <li>See your digital ID and certificate and download them</li>
                    <li>Apply for committee positions and run for office</li>
                    <li>Follow your welfare concerns from your own dashboard</li>
                </ul>
            </div>
        </div>
        <?= $this->partial('partials/form_field', ['name' => 'student_no', 'label' => 'PUP student number', 'value' => (string) old('student_no'), 'autocomplete' => 'off', 'help' => 'For example, 2024-xxxxx-MN-0.']) ?>
        <div class="hp-field" aria-hidden="true">
            <label for="website">Leave this field empty</label>
            <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
        </div>
        <button type="submit" class="btn btn--primary btn--block">Send my code</button>
        <p class="login-page__links">
            <a href="<?= e(route('apply')) ?>">Not a member yet? Apply</a>
            <a href="<?= e(route('login')) ?>">Already registered? Log in</a>
        </p>
    </div>
</form>
<?php $form = (string) ob_get_clean(); ?>
<?= $this->partial('auth/_robot_page', [
    'heading' => 'Create your AGILE account',
    'lead' => 'Step 1 of 3. For applicants whose membership was approved: enter your PUP student number exactly as on your application, and we send a code to the email you used there.',
    'bubble' => "Mabuhay! I'm Iskobot. Let's set up your AGILE account!",
    'form' => $form,
]) ?>
