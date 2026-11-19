<?php
/** @var \App\Core\View $this */
?>
<?php $this->start('title') ?>Create your account<?php $this->end() ?>
<?php $this->start('auth_glass') ?>1<?php $this->end() ?>
<h1>Create your account</h1>
<p>This is for applicants whose AGILE membership was approved. Enter your PUP student number exactly as it is on your application. We send a code to the email you used there.</p>
<form method="post" action="<?= e(route('register')) ?>">
    <?= csrf_field() ?>
    <?= $this->partial('partials/form_field', ['name' => 'student_no', 'label' => 'PUP student number', 'value' => (string) old('student_no'), 'autocomplete' => 'off', 'help' => 'For example, 2024-00101-OU-0.']) ?>
    <div class="hp-field" aria-hidden="true">
        <label for="website">Leave this field empty</label>
        <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
    </div>
    <button type="submit" class="btn btn--primary btn--block">Send my code</button>
</form>
<p>Not a member yet? <a href="<?= e(route('apply')) ?>">Apply for membership</a>. Already registered? <a href="<?= e(route('login')) ?>">Log in</a>.</p>
