<?php
/**
 * @var array<string, mixed>|null $application
 * @var int $step
 */
?>
<div class="card__footer wizard-actions">
    <?php if ($application !== null && $step > 1) : ?>
        <a class="btn btn--ghost" href="<?= e(route('apply.step', ['id' => $application['application_id'], 'step' => $step - 1])) ?>">Back</a>
    <?php endif; ?>
    <button type="submit" class="btn btn--secondary" name="intent" value="save">Save and finish later</button>
    <button type="submit" class="btn btn--primary" name="intent" value="next">Save and continue</button>
</div>
