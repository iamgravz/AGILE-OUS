<?php

declare(strict_types=1);

// EMAIL E1: seed.php leaves existing templates alone, so older databases get the new wording here.
// Safe to run again: a template already in the house style is skipped, so later edits to it are kept.
return static function (PDO $db): void {
    $update = $db->prepare(
        "UPDATE email_templates SET body_html = ?
         WHERE code = ? AND body_html NOT LIKE '%Greetings from the AGILE OUS%'"
    );
    $changed = 0;

    foreach (require __DIR__ . '/../seeds/email_template_text.php' as $code => [, $body]) {
        $update->execute([$body, $code]);
        $changed += $update->rowCount();
    }

    if ($changed > 0) {
        echo "Rewrote {$changed} email templates in the house style.\n";
    }
};
