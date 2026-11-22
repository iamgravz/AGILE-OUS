<?php
/**
 * Approved ID and certificate, each with a small look at the real file.
 * Only people who may open the file see this: the owner, and the MSW Committee Head (FileAccessPolicy::credential).
 *
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $credentials approved, current copies
 * @var bool $forOwner the owner downloads; staff only preview
 * @var int $headingLevel the level of each card's title
 */
$whose = $forOwner ? 'your' : 'this';
?>
<div class="credential-grid">
    <?php foreach ($credentials as $credential) : ?>
        <?php
        $typeName = \App\Services\CredentialService::TYPES[$credential['type']];
        $fileUrl = route('files.show', ['type' => 'credential', 'id' => (int) $credential['credential_id']]);
        // An uploaded JPG or PNG (manual mode) is shown whole as a picture; a PDF needs the frame.
        $isImage = preg_match('/\.(jpg|jpeg|png)$/', (string) ($credential['file_path'] ?? '')) === 1;
        ?>
        <article class="card credential-card credential-card--<?= e($credential['type']) ?>">
            <?php // The frame ignores the pointer, so a click on the preview opens the file full size. ?>
            <?php if ($isImage) : ?>
            <a class="credential-card__thumb credential-card__thumb--image" href="<?= e($fileUrl) ?>?inline=1" target="_blank" rel="noopener">
                <img src="<?= e($fileUrl) ?>?inline=1" alt="<?= e(ucfirst($whose) . ' ' . $typeName) ?>" loading="lazy">
                <span class="sr-only">Open <?= e($whose . ' ' . $typeName) ?> full size in a new tab</span>
            </a>
            <?php else : ?>
            <a class="credential-card__thumb" href="<?= e($fileUrl) ?>?inline=1" target="_blank" rel="noopener">
                <iframe src="<?= e($fileUrl) ?>?inline=1#toolbar=0&amp;navpanes=0&amp;scrollbar=0&amp;view=Fit" title="Preview of <?= e($whose . ' ' . $typeName) ?>" tabindex="-1" loading="lazy"></iframe>
                <?php // Phones cannot draw a PDF in a frame, so they get this tile instead (components.css). ?>
                <span class="credential-card__fallback" aria-hidden="true">
                    <img src="<?= e(asset('img/logo.png')) ?>" alt="" width="40" height="40" loading="lazy">
                    <span><?= e($typeName) ?></span>
                    <span class="credential-card__tap">Tap to view</span>
                </span>
                <span class="sr-only">Open <?= e($whose . ' ' . $typeName) ?> full size in a new tab</span>
            </a>
            <?php endif; ?>
            <div class="credential-card__body">
                <h<?= $headingLevel ?> class="credential-card__title"><?= e($typeName) ?></h<?= $headingLevel ?>>
                <dl class="detail-list">
                    <div><dt>Issued</dt><dd><?= e(date_ph($credential['issued_at'])) ?></dd></div>
                    <div><dt>Serial number</dt><dd><code><?= e($credential['serial_no']) ?></code></dd></div>
                </dl>
                <div class="cluster">
                    <?php if ($forOwner) : ?>
                        <a class="btn btn--secondary btn--sm" href="<?= e($fileUrl) ?>">Download<span class="sr-only"> <?= e($typeName) ?></span></a>
                    <?php endif; ?>
                    <a class="btn btn--<?= $forOwner ? 'ghost' : 'secondary' ?> btn--sm" href="<?= e($fileUrl) ?>?inline=1" target="_blank" rel="noopener">View full size<span class="sr-only"> <?= e($typeName) ?> (opens in a new tab)</span></a>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</div>
