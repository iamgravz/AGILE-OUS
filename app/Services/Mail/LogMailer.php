<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Core\Config;

/** Development driver: writes each email to storage/mail/ instead of sending it. */
final class LogMailer implements MailerInterface
{
    public function send(string $to, string $subject, string $html): void
    {
        $name = sprintf('%s_%s.html', date('Ymd_His'), bin2hex(random_bytes(4)));
        $envelope = sprintf("<!-- To: %s | Subject: %s -->\n", e($to), e($subject));

        file_put_contents(Config::basePath("storage/mail/{$name}"), $envelope . EmailBanner::forPreview($html));
    }
}
