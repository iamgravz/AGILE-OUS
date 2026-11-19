<?php
/**
 * The useful panel beside a form. Each block is a heading with text, a list, links or the contact email.
 *
 * @var list<array{title: string, text?: string, items?: list<string>, links?: list<array{0: string, 1: string}>, contact?: bool}> $blocks
 */
?>
<?php foreach ($blocks as $block) : ?>
    <section class="card side-note">
        <div class="card__body">
            <h2 class="side-note__title"><?= e($block['title']) ?></h2>
            <?php if (isset($block['text'])) : ?><p><?= e($block['text']) ?></p><?php endif; ?>
            <?php if (isset($block['items'])) : ?>
                <ul class="side-note__list">
                    <?php foreach ($block['items'] as $item) : ?><li><?= e($item) ?></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if (isset($block['links'])) : ?>
                <ul class="side-note__links">
                    <?php foreach ($block['links'] as [$label, $url]) : ?><li><a href="<?= e($url) ?>"><?= e($label) ?></a></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($block['contact'] ?? false) : ?>
                <p><a href="mailto:<?= e(org_contact_email()) ?>"><?= e(org_contact_email()) ?></a></p>
            <?php endif; ?>
        </div>
    </section>
<?php endforeach; ?>
