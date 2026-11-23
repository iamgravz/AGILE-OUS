<?php
/**
 * Document archive: decided COR and grades, moved per semester. Records stay; nothing is deleted.
 *
 * @var \App\Core\View $this
 * @var array{total: int, bytes: int, folders: array<string, array{count: int, bytes: int}>, archived: int} $summary
 * @var int $days
 */
$size = static fn (int $bytes): string => $bytes >= 1048576 ? number_format($bytes / 1048576, 1) . ' MB' : number_format($bytes / 1024, 0) . ' KB';
?>
<?php $this->start('title') ?>Document archive<?php $this->end() ?>
<?php $this->start('page_title') ?>Document archive<?php $this->end() ?>

<p class="text-muted">Once a semester, move the COR and copy of grades of decided applications and closed candidacies into the archive, <?= e($days) ?> days after the decision (Constitution and By-Laws, Article III, Section 6). Member records stay as they are, the files still open from the same pages, and nothing is deleted.</p>

<div class="stat-grid">
    <div class="stat-card"><p class="stat-card__label">Ready for the archive</p><p class="stat-card__value"><?= e($summary['total']) ?></p></div>
    <div class="stat-card"><p class="stat-card__label">Space</p><p class="stat-card__value"><?= e($size($summary['bytes'])) ?></p></div>
    <div class="stat-card"><p class="stat-card__label">Already archived</p><p class="stat-card__value"><?= e($summary['archived']) ?></p></div>
</div>

<?php if ($summary['total'] === 0) : ?>
    <p>Nothing to archive right now.</p>
<?php else : ?>
    <div class="table-wrap">
        <table class="table">
            <caption class="sr-only">Files ready for the archive, by semester</caption>
            <thead><tr><th scope="col">Semester folder</th><th scope="col" class="num">Files</th><th scope="col" class="num">Space</th></tr></thead>
            <tbody>
                <?php foreach ($summary['folders'] as $folder => $row) : ?>
                    <tr><td>archive/<?= e($folder) ?></td><td class="num"><?= e($row['count']) ?></td><td class="num"><?= e($size($row['bytes'])) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <form method="post" action="<?= e(route('admin.archive')) ?>" data-confirm="The files move into their semester folder. They still open from the same pages, and nothing is deleted." data-confirm-title="Archive <?= e($summary['total']) ?> files?" data-confirm-action="Archive">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn--primary">Archive <?= e($summary['total']) ?> files</button>
    </form>
<?php endif; ?>
