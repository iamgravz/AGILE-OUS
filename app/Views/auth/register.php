<?php
/** @var \App\Core\View $this */
?>
<?php $this->start('title') ?>Create your account<?php $this->end() ?>
<?php $this->start('auth_style') ?>iskobot<?php $this->end() ?>
<?= $this->partial('partials/iskobot', ['step' => 1, 'label' => 'Your PUP student number', 'bubble' => "Mabuhay! I'm Iskobot. Let's set up your AGILE account!"]) ?>
<div class="iskobot-card">
    <h1>Create your AGILE account</h1>
    <p class="iskobot-card__lead">For applicants whose AGILE membership was approved. Enter your PUP student number exactly as it is on your application. We send a code to the email you used there.</p>

    <div class="iskobot-note">
        <p class="iskobot-note__title"><?= icon('info') ?> With an AGILE account, you can:</p>
        <ul>
            <li>See your <strong>digital ID and certificate</strong> and download them</li>
            <li>Apply for <strong>committee and officer positions</strong></li>
            <li>Follow your <strong>welfare concerns</strong> from your own dashboard</li>
        </ul>
    </div>

    <form method="post" action="<?= e(route('register')) ?>">
        <?= csrf_field() ?>
        <?= $this->partial('partials/form_field', ['name' => 'student_no', 'label' => 'PUP student number', 'value' => (string) old('student_no'), 'autocomplete' => 'off', 'help' => 'For example, 2024-xxxxx-MN-0.']) ?>
        <div class="hp-field" aria-hidden="true">
            <label for="website">Leave this field empty</label>
            <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
        </div>
        <button type="submit" class="iskobot-btn">Send my code</button>
    </form>

    <p class="iskobot-card__foot">Not a member yet? <a href="<?= e(route('apply')) ?>">Apply for membership</a>. Already registered? <a href="<?= e(route('login')) ?>">Log in</a>.</p>
</div>
