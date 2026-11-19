<?php
/**
 * Rendered to PDF, which has its own CSS support and no access to the site's stylesheet.
 *
 * @var string $name
 * @var string $role
 * @var string $serial
 * @var string $issuedAt
 * @var string $approval who approved it and when, or that it is pending
 * @var array<string, mixed> $member
 * @var string|null $logo
 * @var string|null $photo
 * @var string $qr
 */
$nameSize = mb_strlen($name) > 44 ? 7 : (mb_strlen($name) > 26 ? 8.5 : 10.5);
$initials = mb_strtoupper(mb_substr($member['first_name'], 0, 1) . mb_substr($member['last_name'], 0, 1));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>AGILE Digital ID</title>
<style>
    @page { margin: 0; }
    body { margin: 0; font-family: "DejaVu Sans", sans-serif; color: #2b1a1b; background: #ffffff; }
    .band { position: absolute; top: 0; left: 0; width: 85.6mm; height: 11.5mm; background: #611414; color: #ffffff; }
    .band img { position: absolute; top: 1.4mm; left: 2.5mm; width: 8.7mm; height: 8.7mm; }
    .org { position: absolute; top: 2.1mm; left: 12.7mm; font-family: "DejaVu Serif", serif; font-size: 10pt; font-weight: bold; }
    .org-sub { position: absolute; top: 6.8mm; left: 12.7mm; font-size: 4.6pt; color: #f3dfa2; }
    .stripe { position: absolute; top: 11.5mm; left: 0; width: 85.6mm; height: 0.7mm; background: #c9a227; }
    .photo { position: absolute; top: 14.5mm; left: 3mm; width: 19mm; height: 19mm; border: 0.3mm solid #611414; }
    .initials { position: absolute; top: 14.5mm; left: 3mm; width: 19mm; height: 13mm; padding-top: 6mm; border: 0.3mm solid #611414; background: #f6ecd2; color: #611414; font-size: 16pt; font-weight: bold; text-align: center; }
    .details { position: absolute; top: 14.1mm; left: 24mm; width: 40mm; }
    .name { font-family: "DejaVu Serif", serif; font-weight: bold; color: #611414; line-height: 1.1; }
    .label { margin-top: 1.1mm; font-size: 4.4pt; color: #6b5a5b; text-transform: uppercase; }
    .value { font-size: 6.4pt; line-height: 1.15; }
    .qr { position: absolute; top: 14.5mm; right: 2.6mm; width: 17mm; height: 17mm; }
    .footer { position: absolute; left: 3mm; right: 3mm; bottom: 1.8mm; font-size: 4.6pt; color: #6b5a5b; }
    .status { position: absolute; top: 34.6mm; left: 3mm; width: 19mm; padding: 0.5mm 0; background: #1f6b3a; color: #ffffff; font-size: 6pt; font-weight: bold; text-align: center; }
    .serial { position: absolute; top: 32.6mm; right: 2.6mm; width: 17mm; font-size: 3.3pt; color: #6b5a5b; text-align: center; }
</style>
</head>
<body>
    <div class="band">
        <?php if ($logo !== null) : ?><img src="<?= e($logo) ?>" alt=""><?php endif; ?>
        <div class="org">AGILE</div>
        <div class="org-sub">Academic Guild of IT Learners and Experts</div>
    </div>
    <div class="stripe"></div>

    <?php if ($photo !== null) : ?>
        <img class="photo" src="<?= e($photo) ?>" alt="">
    <?php else : ?>
        <div class="initials"><?= e($initials) ?></div>
    <?php endif; ?>
    <div class="status">Active</div>

    <div class="details">
        <div class="name" style="font-size: <?= e($nameSize) ?>pt;"><?= e($name) ?></div>
        <div class="label">Member number</div>
        <div class="value"><?= e($member['member_no']) ?></div>
        <div class="label">Role and position</div>
        <div class="value"><?= e($role) ?></div>
        <div class="label">Issued</div>
        <div class="value"><?= e(date_ph($issuedAt)) ?></div>
    </div>

    <img class="qr" src="<?= e($qr) ?>" alt="">
    <div class="serial"><?= e($serial) ?></div>
    <div class="footer"><?= e($approval) ?><br>PUP Open University System. Scan the QR code to verify this ID.</div>
</body>
</html>
