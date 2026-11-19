<?php
/**
 * An officer on the About page. Photos are only served to staff and to the person themselves, so the public sees initials.
 *
 * @var array<string, mixed> $officer
 */
$name = $officer['first_name'] . ' ' . $officer['last_name'];
?>
<article class="card officer-card">
    <?= initials_avatar($name) ?>
    <div class="officer-card__body">
        <p class="officer-card__name"><?= e($name) ?></p>
        <p class="officer-card__line"><?= e($officer['position_title'] ?? $officer['role_name']) ?></p>
        <p class="officer-card__line"><?= e($officer['committee_name'] ?? 'Executive') ?></p>
    </div>
</article>
