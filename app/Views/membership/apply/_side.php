<?php
/**
 * Beside the wizard: the progress. On phones the progress stays above the form instead.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed>|null $application
 * @var int $step
 * @var int $reached
 */
?>
<section class="card side-note side-note--progress">
    <div class="card__body">
        <h2 class="side-note__title">Your progress</h2>
        <?= $this->partial('membership/apply/_stepper', compact('application', 'step', 'reached') + ['inPanel' => true]) ?>
    </div>
</section>