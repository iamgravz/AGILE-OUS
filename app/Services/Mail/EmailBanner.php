<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Core\Config;

/** The banner at the top of every email. Embedded in the message, so it shows even when the app runs on localhost. */
final class EmailBanner
{
    public const CID = 'agile-banner';
    public const ALT = 'AGILE OUS — Membership and Student Welfare Committee';
    private const FILE = 'public/assets/img/email-banner.jpg';

    public static function path(): string
    {
        return Config::basePath(self::FILE);
    }

    /** What the wrapper's img points at. Empty when there is no banner, so the wrapper shows a text header instead. */
    public static function src(): string
    {
        return is_file(self::path()) ? 'cid:' . self::CID : (string) config('app.mail.logo_url');
    }

    /** storage/mail previews open as local files, so they point at the file itself. */
    public static function forPreview(string $html): string
    {
        return str_replace('cid:' . self::CID, '../../' . self::FILE, $html);
    }
}
