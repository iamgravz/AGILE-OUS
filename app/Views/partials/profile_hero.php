<?php
/**
 * A person across the top of the page: photo, name and a few lines, then their details in columns.
 * Used by My Account, a member's record and a user account.
 *
 * @var \App\Core\View $this
 * @var string $name
 * @var string|null $photoUrl
 * @var string $headingId
 * @var list<string> $lines HTML, already escaped: shown under the name
 * @var list<array{0: string, 1: string}> $facts label, then the value as escaped HTML
 * @var string|null $note (optional) plain text under the details
 * @var string $extra (optional) HTML under the details, such as a small form
 */
$note ??= null;
$extra ??= '';
?>
<section class="card account-hero" aria-labelledby="<?= e($headingId) ?>">
    <div class="account-hero__who">
        <?php if ($photoUrl !== null) : ?>
            <img class="avatar account-hero__avatar" src="<?= e($photoUrl) ?>" alt="" width="96" height="96">
        <?php else : ?>
            <span class="account-hero__avatar account-hero__avatar--initials"><?= initials_avatar($name) ?></span>
        <?php endif; ?>
        <div class="account-hero__name">
            <h2 id="<?= e($headingId) ?>"><?= e($name) ?></h2>
            <?php foreach ($lines as $line) : ?>
                <p><?= $line ?></p>
            <?php endforeach; ?>
        </div>
    </div>
    <dl class="detail-list account-hero__facts">
        <?php foreach ($facts as [$label, $value]) : ?>
            <div><dt><?= e($label) ?></dt><dd><?= $value ?></dd></div>
        <?php endforeach; ?>
    </dl>
    <?php if ($note !== null) : ?>
        <p class="field__help account-hero__note"><?= e($note) ?></p>
    <?php endif; ?>
    <?php if ($extra !== '') : ?>
        <div class="account-hero__note"><?= $extra ?></div>
    <?php endif; ?>
</section>
