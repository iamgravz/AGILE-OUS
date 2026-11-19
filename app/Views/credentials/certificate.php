<?php
/**
 * Rendered to PDF, which has its own CSS support and no access to the site's stylesheet.
 *
 * @var string $name
 * @var string $serial
 * @var string $issuedAt
 * @var string $approval who approved it and when, or that it is pending
 * @var array<string, mixed> $member
 * @var string|null $logo
 * @var string $qr
 */
$nameSize = mb_strlen($name) > 60 ? 20 : (mb_strlen($name) > 36 ? 26 : 34);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>AGILE Certificate of Membership</title>
<style>
    @page { margin: 0; }
    body { margin: 0; font-family: "DejaVu Sans", sans-serif; color: #2b1a1b; background: #ffffff; }
    .frame { position: absolute; top: 8mm; left: 8mm; width: 277mm; height: 190mm; border: 1.2mm solid #611414; }
    .inner { position: absolute; top: 10.5mm; left: 10.5mm; width: 272mm; height: 185mm; border: 0.4mm solid #c9a227; }
    .content { position: absolute; top: 18mm; left: 25mm; width: 247mm; text-align: center; }
    .logo { width: 26mm; height: 26mm; }
    .org { margin-top: 2mm; font-family: "DejaVu Serif", serif; font-size: 15pt; font-weight: bold; color: #611414; }
    .sub { font-size: 9.5pt; color: #6b5a5b; }
    .title { margin-top: 7mm; font-family: "DejaVu Serif", serif; font-size: 27pt; font-weight: bold; color: #611414; }
    .lead { margin-top: 5mm; font-size: 11pt; }
    .name { margin-top: 3mm; font-family: "DejaVu Serif", serif; font-weight: bold; color: #2b1a1b; line-height: 1.15; }
    .rule { width: 150mm; margin: 2mm auto 0; border-top: 0.5mm solid #c9a227; }
    .body { margin-top: 4mm; font-size: 11pt; line-height: 1.45; }
    .signature { position: absolute; top: 176mm; left: 26mm; width: 76mm; border-top: 0.3mm solid #2b1a1b; padding-top: 1.5mm; font-size: 9.5pt; text-align: center; }
    .signature--right { left: 118mm; }
    .qr { position: absolute; right: 24mm; bottom: 26mm; width: 29mm; height: 29mm; }
    .serial { position: absolute; right: 14mm; bottom: 19mm; width: 49mm; font-size: 6.5pt; color: #6b5a5b; text-align: center; }
</style>
</head>
<body>
    <div class="frame"></div>
    <div class="inner"></div>
    <div class="content">
        <?php if ($logo !== null) : ?><img class="logo" src="<?= e($logo) ?>" alt=""><?php endif; ?>
        <div class="org">Academic Guild of IT Learners and Experts</div>
        <div class="sub">PUP Open University System</div>
        <div class="title">Certificate of Membership</div>
        <div class="lead">This certifies that</div>
        <div class="name" style="font-size: <?= e($nameSize) ?>pt;"><?= e($name) ?></div>
        <div class="rule"></div>
        <div class="body">
            Member No. <?= e($member['member_no']) ?><br>
            is an Active member of AGILE, having joined on <?= e(date_ph($member['joined_at'])) ?>.<br>
            Issued on <?= e(date_ph($issuedAt)) ?>.<br>
            <?= e($approval) ?>
        </div>
    </div>
    <div class="signature">MSW Committee Head</div>
    <div class="signature signature--right">President</div>
    <img class="qr" src="<?= e($qr) ?>" alt="">
    <div class="serial"><?= e($serial) ?></div>
</body>
</html>
