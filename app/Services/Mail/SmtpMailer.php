<?php

declare(strict_types=1);

namespace App\Services\Mail;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

final class SmtpMailer implements MailerInterface
{
    private const TIMEOUT_SECONDS = 10;

    public function __construct(private readonly ?string $bannerPath = null)
    {
    }

    public function send(string $to, string $subject, string $html): void
    {
        $mail = $this->build($to, $subject, $html);

        try {
            $mail->send();
        } catch (PHPMailerException $exception) {
            throw new RuntimeException($mail->ErrorInfo ?: $exception->getMessage(), 0, $exception);
        }
    }

    /** The finished message, not yet sent. */
    public function build(string $to, string $subject, string $html): PHPMailer
    {
        $host = (string) config('app.mail.smtp.host');

        if ($host === '') {
            throw new RuntimeException('SMTP_HOST is not set.');
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = (int) config('app.mail.smtp.port');
            $mail->Timeout = self::TIMEOUT_SECONDS;
            $mail->CharSet = PHPMailer::CHARSET_UTF8;

            $user = (string) config('app.mail.smtp.user');

            if ($user !== '') {
                $mail->SMTPAuth = true;
                $mail->Username = $user;
                $mail->Password = (string) config('app.mail.smtp.pass');
            }

            $mail->SMTPAutoTLS = false;
            $mail->SMTPSecure = match (strtolower((string) config('app.mail.smtp.encryption'))) {
                'ssl' => PHPMailer::ENCRYPTION_SMTPS,
                'tls' => PHPMailer::ENCRYPTION_STARTTLS,
                default => '',
            };

            $mail->setFrom((string) config('app.mail.from_address'), (string) config('app.mail.from_name'));
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $html;
            $mail->AltBody = trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));

            $banner = $this->bannerPath ?? EmailBanner::path();

            if (str_contains($html, 'cid:' . EmailBanner::CID) && is_file($banner)) {
                $mail->addEmbeddedImage($banner, EmailBanner::CID, 'email-banner.jpg', PHPMailer::ENCODING_BASE64, 'image/jpeg');
            }
        } catch (PHPMailerException $exception) {
            throw new RuntimeException($mail->ErrorInfo ?: $exception->getMessage(), 0, $exception);
        }

        return $mail;
    }
}
