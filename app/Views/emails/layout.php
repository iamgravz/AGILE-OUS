<?php
/**
 * Standard AGILE email wrapper: banner, body, signature, gold rule, maroon footer. Inline styles and tables only,
 * so it renders in Gmail and Outlook. The width attribute is for Outlook and max-width is for phones.
 *
 * @var string $subject
 * @var string $body already rendered and escaped by TemplateRenderer
 * @var bool $isAnnouncement
 * @var array{full_name: string, contact_email: string, signatory_name: string, signatory_position: string, signatory_committee: string} $org
 */
$bannerSrc = \App\Services\Mail\EmailBanner::src();
$font = 'font-family:Arial,Helvetica,sans-serif;';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($subject) ?></title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f4;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f4f4;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background-color:#ffffff;border:1px solid #dddddd;">
                    <tr>
                        <?php if ($bannerSrc !== '') : ?>
                            <td style="padding:0;line-height:0;font-size:0;">
                                <img src="<?= e($bannerSrc) ?>" alt="<?= e(\App\Services\Mail\EmailBanner::ALT) ?>" width="600" style="display:block;width:100%;max-width:600px;height:auto;border:0;">
                            </td>
                        <?php else : ?>
                            <td style="padding:20px 24px;border-bottom:1px solid #dddddd;<?= $font ?>font-size:18px;line-height:24px;font-weight:bold;color:#7a1a1a;"><?= e(\App\Services\Mail\EmailBanner::ALT) ?></td>
                        <?php endif; ?>
                    </tr>
                    <tr>
                        <td align="left" style="padding:24px;<?= $font ?>font-size:15px;line-height:23px;color:#222222;">
                            <?= $body ?>
                            <p style="margin:24px 0 0 0;">
                                Warm regards,<br>
                                <?php if ($org['signatory_name'] !== '') : ?>
                                    <strong><?= e($org['signatory_name']) ?></strong><br>
                                <?php endif; ?>
                                <?php if ($org['signatory_position'] !== '') : ?>
                                    <?= e($org['signatory_position']) ?><br>
                                <?php endif; ?>
                                <?php if ($org['signatory_committee'] !== '') : ?>
                                    <em><?= e($org['signatory_committee']) ?></em><br>
                                <?php endif; ?>
                                AGILE OUS
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height="3" bgcolor="#c9a227" style="height:3px;line-height:3px;font-size:0;background-color:#c9a227;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td bgcolor="#7a1a1a" align="left" style="padding:18px 24px;background-color:#7a1a1a;<?= $font ?>font-size:14px;line-height:21px;color:#ffffff;">
                            <p style="margin:0;"><strong><?= e($org['full_name']) ?></strong></p>
                            <p style="margin:0;"><strong>Membership and Student Welfare Committee</strong></p>
                            <p style="margin:0 0 10px 0;"><a href="mailto:<?= e($org['contact_email']) ?>" style="color:#ffffff;text-decoration:underline;"><?= e($org['contact_email']) ?></a></p>
                            <p style="margin:0;font-size:12px;line-height:18px;">This is an official communication from AGILE OUS. Please treat applicant-related information as confidential.</p>
                            <?php if ($isAnnouncement) : ?>
                                <p style="margin:8px 0 0 0;font-size:12px;line-height:18px;">You received this announcement as part of the AGILE community. To stop receiving announcements, email <a href="mailto:<?= e($org['contact_email']) ?>" style="color:#ffffff;text-decoration:underline;"><?= e($org['contact_email']) ?></a>.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
