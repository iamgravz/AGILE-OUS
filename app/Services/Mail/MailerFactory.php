<?php

declare(strict_types=1);

namespace App\Services\Mail;

use InvalidArgumentException;

final class MailerFactory
{
    public static function make(): MailerInterface
    {
        $driver = (string) config('app.mail.driver');

        // To add a driver, implement MailerInterface and add its MAIL_DRIVER value here.
        // Step 24 adds 'gmail' => new GmailApiMailer().
        return match ($driver) {
            'log' => new LogMailer(),
            'smtp' => new SmtpMailer(),
            default => throw new InvalidArgumentException("Unknown MAIL_DRIVER: {$driver}"),
        };
    }
}
