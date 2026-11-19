<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use HTMLPurifier;
use HTMLPurifier_Config;

/** Allow-list for rich text from the CMS editor. Everything else is removed before it is stored. */
final class HtmlSanitizer
{
    private const ALLOWED = 'p,h2,h3,h4,strong,em,ul,ol,li,a[href],blockquote,img[src|alt]';
    private const MEDIA_URL = '#^/media/[a-f0-9]{32}$#';

    private ?HTMLPurifier $purifier = null;

    /**
     * For email template bodies: placeholders survive, and the details block and button keep their class.
     * No images, because the wrapper already has the banner.
     */
    public function cleanEmail(string $html): string
    {
        // Placeholders go through as plain words, because the purifier would otherwise escape them inside links.
        $marked = (string) preg_replace('/\{([a-z_]+)\}/', 'agileplaceholder-$1-end', $html);
        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $cache = Config::basePath('storage/cache/htmlpurifier');
        $config->set('Cache.SerializerPath', is_dir($cache) ? $cache : null);
        $config->set('HTML.Allowed', 'p[class],br,h2,h3,strong,em,ul,ol,li,a[href|class]');
        $config->set('Attr.AllowedClasses', ['details', 'button']);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('URI.DisableExternalResources', false);
        $clean = (new HTMLPurifier($config))->purify($marked);
        // A bare placeholder in href reads as a relative address, which is allowed, and comes back unchanged here.
        return trim((string) preg_replace('/agileplaceholder-([a-z_]+)-end/', '{$1}', $clean));
    }

    public function clean(string $html): string
    {
        $clean = $this->purifier()->purify($html);

        // Only images uploaded through this system may appear. Purifier output has its attributes in double quotes.
        $clean = (string) preg_replace_callback('/<img\b[^>]*>/i', static function (array $match): string {
            $isOurs = preg_match('/\ssrc="([^"]*)"/', $match[0], $src) === 1 && preg_match(self::MEDIA_URL, $src[1]) === 1;

            return $isOurs ? $match[0] : '';
        }, $clean);

        // Script source pasted as text is already harmless, but it would be published as visible junk.
        return trim((string) preg_replace('#&lt;script\b.*?&lt;/script&gt;#is', '', $clean));
    }

    private function purifier(): HTMLPurifier
    {
        if ($this->purifier !== null) {
            return $this->purifier;
        }

        $cache = Config::basePath('storage/cache/htmlpurifier');

        if (!is_dir($cache)) {
            mkdir($cache, 0750, true);
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('Cache.SerializerPath', $cache);
        $config->set('HTML.Allowed', self::ALLOWED);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        // Otherwise Purifier uses the random file name, which a screen reader would read aloud.
        $config->set('Attr.DefaultImageAlt', '');
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('AutoFormat.RemoveEmpty.RemoveNbsp', true);

        return $this->purifier = new HTMLPurifier($config);
    }
}
