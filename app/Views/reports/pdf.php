<?php
/**
 * Rendered to PDF, which has its own CSS support and no access to the site's stylesheet.
 *
 * @var string $title
 * @var string $subtitle
 * @var list<string> $columns
 * @var list<list<string>> $rows
 * @var string|null $logo
 * @var string $generatedAt
 */
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>AGILE <?= e($title) ?></title>
<style>
    @page { margin: 14mm 12mm 16mm; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; color: #2b1a1b; }
    .header { border-bottom: 1mm solid #611414; padding-bottom: 3mm; margin-bottom: 4mm; }
    .header td { border: 0; padding: 0; vertical-align: middle; }
    .logo { width: 16mm; height: 16mm; margin-right: 4mm; }
    .org { font-family: "DejaVu Serif", serif; font-size: 15pt; font-weight: bold; color: #611414; }
    .sub { font-size: 8.5pt; color: #6b5a5b; }
    h1 { font-family: "DejaVu Serif", serif; font-size: 13pt; color: #611414; margin: 0 0 1mm; }
    .meta { font-size: 8.5pt; color: #6b5a5b; margin-bottom: 4mm; }
    table.data { width: 100%; border-collapse: collapse; }
    table.data th { background: #611414; color: #ffffff; text-align: left; padding: 1.5mm 2mm; font-size: 8.5pt; }
    table.data td { border-bottom: 0.2mm solid #d9cfcf; padding: 1.5mm 2mm; word-wrap: break-word; }
    table.data thead { display: table-header-group; }
    table.data tr { page-break-inside: avoid; }
</style>
</head>
<body>
    <table class="header" width="100%">
        <tr>
            <?php if ($logo !== null) : ?>
                <td width="20mm"><img class="logo" src="<?= e($logo) ?>" alt=""></td>
            <?php endif; ?>
            <td>
                <div class="org">AGILE</div>
                <div class="sub">Academic Guild of IT Learners and Experts, PUP Open University System</div>
            </td>
        </tr>
    </table>
    <h1><?= e($title) ?></h1>
    <p class="meta"><?= e($subtitle) ?>. <?= e(count($rows)) ?> <?= count($rows) === 1 ? 'row' : 'rows' ?>. Generated <?= e($generatedAt) ?>. Confidential.</p>
    <?php if ($rows === []) : ?>
        <p>No records match.</p>
    <?php else : ?>
        <table class="data">
            <thead>
                <tr>
                    <?php foreach ($columns as $column) : ?>
                        <th><?= e($column) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <?php foreach ($row as $cell) : ?>
                            <td><?= e($cell) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
