<?php

declare(strict_types=1);

return static function (PDO $db): void {
    foreach (require __DIR__ . '/email_template_text.php' as $code => [$subject, $body]) {
        seed_row($db, 'email_templates', 'template_id', ['code' => $code], [
            'subject' => $subject,
            'body_html' => $body,
        ]);
    }
};
