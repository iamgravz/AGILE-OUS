<?php
/**
 * Long public pages: a left column with "On this page", and the content in all the remaining width.
 * A page sets these sections: docs_group, docs_eyebrow, docs_title, docs_updated, docs_context, docs_related.
 * The contents list is built here from the page's h2 and h3, so it needs no JavaScript and nothing moves on load.
 *
 * @var \App\Core\View $this
 */
$toc = [];
$usedIds = [];
$content = (string) preg_replace_callback('/<h([23])\b([^>]*)>(.*?)<\/h\1>/si', static function (array $match) use (&$toc, &$usedIds): string {
    [$whole, $level, $attributes, $inner] = $match;
    $text = trim(html_entity_decode(strip_tags($inner), ENT_QUOTES, 'UTF-8'));

    if ($text === '') {
        return $whole;
    }

    if (preg_match('/\bid="([^"]+)"/', $attributes, $existing) === 1) {
        $id = $existing[1];
    } else {
        $base = 'section-' . trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
        $id = $base;

        for ($number = 2; in_array($id, $usedIds, true); $number++) {
            $id = "{$base}-{$number}";
        }

        $attributes .= ' id="' . e($id) . '"';
    }

    $usedIds[] = $id;
    $toc[] = ['id' => $id, 'text' => $text, 'level' => (int) $level];

    return "<h{$level}{$attributes}>{$inner}</h{$level}>";
}, $this->section('content'));
$contact = org_contact_email();
$context = $this->section('docs_context');
$updated = $this->section('docs_updated');
$tocLinks = static function () use ($toc): void { ?>
    <ul class="docs-toc__list">
        <?php foreach ($toc as $item) : ?>
            <li><a class="docs-toc__link docs-toc__link--h<?= e($item['level']) ?>" href="#<?= e($item['id']) ?>"><?= e($item['text']) ?></a></li>
        <?php endforeach; ?>
    </ul>
<?php };
?>
<!doctype html>
<html lang="en">
<head>
    <?= $this->partial('partials/head') ?>
    <script src="<?= e(asset('js/docs.js')) ?>" defer></script>
</head>
<body class="site-body">
    <a class="skip-link" href="#main">Skip to main content</a>

    <?= $this->partial('partials/site_header') ?>

    <main id="main" class="site-main site-main--docs" tabindex="-1">
        <div class="docs">
            <aside class="docs__side">
                <div class="docs__side-inner" data-docs-side>
                    <p class="docs__group"><?= $this->section('docs_group') ?></p>
                    <?php if ($toc !== []) : ?>
                        <nav class="docs-toc" aria-label="On this page" data-docs-toc>
                            <p class="docs-toc__title">On this page</p>
                            <?php $tocLinks(); ?>
                        </nav>
                    <?php endif; ?>
                    <ul class="docs__related">
                        <?= $this->section('docs_related') ?>
                        <li><a href="<?= e(route('track')) ?>">Track Status</a></li>
                        <li><a href="<?= e(route('ask_for_help')) ?>">Ask for Help</a></li>
                        <li><a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a></li>
                    </ul>
                </div>
            </aside>

            <div class="docs__main">
                <?= $this->partial('partials/flash') ?>
                <header class="docs__head">
                    <p class="docs__eyebrow"><?= $this->section('docs_eyebrow') ?></p>
                    <h1 class="docs__title"><?= $this->section('docs_title') ?></h1>
                    <?php if ($updated !== '') : ?>
                        <p class="docs__updated"><?= $updated ?></p>
                    <?php endif; ?>
                    <?php if ($toc !== []) : ?>
                        <details class="docs-panel" data-docs-panel>
                            <summary class="docs-panel__button">On this page</summary>
                            <nav class="docs-toc docs-panel__body" aria-label="On this page" data-docs-toc>
                                <?php $tocLinks(); ?>
                            </nav>
                        </details>
                    <?php endif; ?>
                </header>

                <div class="docs__body">
                    <?php if ($context !== '') : ?>
                        <aside class="docs__context" aria-label="Summary"><?= $context ?></aside>
                    <?php endif; ?>
                    <div class="docs__content"><?= $content ?></div>
                </div>
            </div>
        </div>
    </main>

    <?= $this->partial('partials/site_footer') ?>

    <?= $this->partial('partials/overlays') ?>
</body>
</html>
