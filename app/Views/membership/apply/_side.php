<?php
/**
 * Beside the wizard: progress, what to prepare and who to ask. On phones the progress stays above the form instead.
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
<?= $this->partial('partials/side_panel', ['blocks' => [
    ['title' => 'What you need', 'items' => ['Your PUP student number', 'A clear copy of your school ID', 'Your proof of enrollment (COR)', 'PDF, JPG or PNG files, up to 5 MB each']],
    ['title' => 'Need help?', 'text' => 'Your answers are saved as you go. Email the MSW Committee if you get stuck.', 'contact' => true],
]]) ?>
