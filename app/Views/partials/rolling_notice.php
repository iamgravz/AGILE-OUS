<?php
/**
 * A notice bar whose lines roll upward one at a time (Nov 23, 2026): the eligibility reminder and new notifications
 * share one bar. A single line holds still. Rolling stops while the pointer or keyboard focus is on the bar, and with
 * reduced motion every line is shown at once. Screen readers get each line as a list item. app.js runs the rolling.
 *
 * @var \App\Core\View $this
 * @var string $tone warning, info, success or danger
 * @var list<array{text: string, url?: string|null, linkText?: string|null}> $lines (optional) the lines, in order
 * @var string $message (optional) a single line, when $lines is not given
 * @var string|null $linkUrl (optional) a link after the single line
 * @var string|null $linkText (optional)
 * @var string|null $asideUrl (optional) a link that stays at the right of the bar, such as Notification settings
 * @var string|null $asideText (optional)
 */
$lines ??= [['text' => (string) ($message ?? ''), 'url' => $linkUrl ?? null, 'linkText' => $linkText ?? null]];
$asideUrl ??= null;
$asideText ??= null;
$icon = $tone === 'success' ? 'check' : ($tone === 'info' ? 'info' : 'alert');
?>
<div class="rolling-notice rolling-notice--<?= e($tone) ?>" role="status"<?= count($lines) > 1 ? ' data-rolling-up' : '' ?>>
    <span class="rolling-notice__icon"><?= icon($icon) ?></span>
    <div class="rolling-notice__window">
        <ul class="rolling-notice__lines">
            <?php foreach ($lines as $line) : ?>
                <li class="rolling-notice__line">
                    <?php if (($line['url'] ?? null) !== null && ($line['linkText'] ?? null) === null) : ?>
                        <a href="<?= e($line['url']) ?>"><?= e($line['text']) ?></a>
                    <?php else : ?>
                        <?= e($line['text']) ?>
                        <?php if (($line['url'] ?? null) !== null) : ?> <a href="<?= e($line['url']) ?>"><?= e($line['linkText']) ?></a><?php endif; ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php if ($asideUrl !== null && $asideText !== null) : ?>
        <a class="rolling-notice__aside" href="<?= e($asideUrl) ?>"><?= e($asideText) ?></a>
    <?php endif; ?>
</div>
