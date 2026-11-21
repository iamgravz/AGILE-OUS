<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $application
 */
$id = (int) $application['application_id'];
?>
<?php $this->start('title') ?>Application submitted<?php $this->end() ?>
<?php $this->start('page_title') ?>Application submitted<?php $this->end() ?>
<div class="card wizard-form">
    <div class="card__body">
        <p>We received your application. A confirmation is on its way to your email.</p>
        <p class="code-box__label" id="tracking-label">Your tracking code</p>
        <div class="code-box">
            <code class="code-box__code" id="tracking-code" aria-labelledby="tracking-label"><?= e($application['tracking_code']) ?></code>
            <button type="button" class="btn btn--secondary btn--sm" data-copy="tracking-code">Copy code</button>
        </div>
        <p class="text-muted">Use this code and your email on the Track Status page at any time.</p>
        <div class="cluster">
            <?php if (current_user_id() === null) : ?>
                <a class="btn btn--primary" href="<?= e(route('track')) ?>">Track my application</a>
            <?php else : ?>
                <a class="btn btn--primary" href="<?= e(route('my_applications.show', ['id' => $id])) ?>">View my application</a>
                <a class="btn btn--ghost" href="<?= e(route('dashboard')) ?>">Go to my dashboard</a>
            <?php endif; ?>
        </div>
    </div>
</div>
