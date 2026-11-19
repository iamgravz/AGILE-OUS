<?php

declare(strict_types=1);

namespace App\Services\Mail;

interface MailerInterface
{
    /** Throws when the message could not be handed off, so the queue can record the failure. */
    public function send(string $to, string $subject, string $html): void;
}
