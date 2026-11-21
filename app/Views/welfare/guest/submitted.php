<?php
/**
 * @var \App\Core\View $this
 * @var string $code
 * @var string $email
 */
?>
<?php $this->start('title') ?>Concern received<?php $this->end() ?>
<div class="welfare-page">
    <h1>Concern received</h1>
    <div class="card">
        <div class="card__body">
            <p>We received your concern, and a confirmation is on its way to your email.</p>
            <p class="code-box__label" id="case-label">Your case code</p>
            <div class="code-box">
                <code class="code-box__code" id="case-code" aria-labelledby="case-label"><?= e($code) ?></code>
                <button type="button" class="btn btn--secondary btn--sm" data-copy="case-code">Copy code</button>
            </div>
            <p>Track this case at <a href="<?= e(route('track')) ?>">/track</a> with your case code and this email (<?= e($email) ?>).</p>
            <p class="text-muted">Please keep this code. Attachments cannot be downloaded again after submitting, because you have no account.</p>
            <a class="btn btn--primary" href="<?= e(route('track')) ?>">Track this case</a>
        </div>
    </div>
</div>
