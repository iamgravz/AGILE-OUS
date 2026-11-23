<?php
/**
 * A notice that rolls across its bar (Nov 23, 2026), such as the eligibility reminder. The text scrolls right to left
 * and stops while the pointer or keyboard focus is on it, so the link can be read and used. Screen readers get the
 * plain text once; with reduced motion the bar holds still and the text wraps.
 *
 * @var \App\Core\View $this
 * @var string $tone warning, info, success or danger
 * @var string $message plain text
 * @var string|null $linkUrl (optional)
 * @var string|null $linkText (optional)
 * @var list<array{text: string, url: string}> $items (optional) linked entries after the message, such as new notifications
 */
$linkUrl ??= null;
$linkText ??= null;
$items ??= [];
$icon = $tone === 'success' ? 'check' : ($tone === 'info' ? 'info' : 'alert');
?>
<div class="rolling-notice rolling-notice--<?= e($tone) ?>" role="status">
    <span class="rolling-notice__icon"><?= icon($icon) ?></span>
    <div class="rolling-notice__track">
        <p class="rolling-notice__text">
            <?= e($message) ?>
            <?php foreach ($items as $item) : ?>
                <span class="rolling-notice__sep" aria-hidden="true">•</span> <a href="<?= e($item['url']) ?>"><?= e($item['text']) ?></a>
            <?php endforeach; ?>
            <?php if ($linkUrl !== null && $linkText !== null) : ?> <a href="<?= e($linkUrl) ?>"><?= e($linkText) ?></a><?php endif; ?>
        </p>
    </div>
</div>
