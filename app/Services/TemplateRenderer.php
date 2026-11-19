<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\View;
use App\Models\EmailTemplateRepository;
use App\Models\SettingRepository;
use InvalidArgumentException;

final class TemplateRenderer
{
    public function __construct(
        private readonly EmailTemplateRepository $templates = new EmailTemplateRepository(),
        private readonly SettingRepository $settings = new SettingRepository(),
    ) {
    }

    /**
     * Set $payload['announcement'] to true to add the unsubscribe note to the footer.
     *
     * @param array<string, mixed> $payload
     * @return array{template_id: int, subject: string, html: string}
     */
    public function render(string $code, array $payload): array
    {
        $template = $this->templates->findByCode($code)
            ?? throw new InvalidArgumentException("Unknown email template: {$code}");

        return ['template_id' => (int) $template['template_id']] + $this->renderText($template['subject'], $template['body_html'], $payload, $code);
    }

    /**
     * Any subject and body in the real wrapper. The template editor previews unsaved text with this.
     *
     * @param array<string, mixed> $payload
     * @return array{subject: string, html: string}
     */
    public function renderText(string $subjectText, string $bodyText, array $payload, string $code = 'preview'): array
    {
        $org = $this->organization();
        $payload += ['contact_email' => $org['contact_email']];

        $subject = $this->fill($code, $subjectText, $payload, static function (string $value): string {
            return trim((string) preg_replace('/\s+/', ' ', $value));
        });
        $body = $this->styleBody($this->fill($code, $bodyText, $payload, static function (string $value): string {
            return nl2br(e($value));
        }));

        return [
            'subject' => $subject,
            'html' => View::render('emails/layout', [
                'subject' => $subject,
                'body' => $body,
                'isAnnouncement' => ($payload['announcement'] ?? false) === true,
                'org' => $org,
            ]),
        ];
    }

    /**
     * One pass, so a {placeholder} typed inside a value is left alone. A missing value throws instead of
     * sending an email with a gap in it.
     *
     * @param array<string, mixed> $payload
     */
    private function fill(string $code, string $text, array $payload, callable $format): string
    {
        return (string) preg_replace_callback('/\{([a-z_]+)\}/', function (array $match) use ($code, $payload, $format): string {
            $key = $match[1];

            if (!array_key_exists($key, $payload) || !(is_scalar($payload[$key]) || $payload[$key] === null)) {
                throw new InvalidArgumentException("Template {$code} needs a value for {{$key}}.");
            }

            if ($key === 'body_html') {
                return (new HtmlSanitizer())->clean((string) $payload[$key]);
            }

            return $key === 'link' ? e($this->absoluteLink((string) $payload[$key])) : $format((string) $payload[$key]);
        }, $text);
    }

    /** Emails open outside the site, so links must be absolute, and only web links are allowed. */
    private function absoluteLink(string $link): string
    {
        if (str_starts_with($link, '/') && !str_starts_with($link, '//')) {
            return url($link);
        }

        return preg_match('#^https?://#i', $link) === 1 ? $link : '#';
    }

    /**
     * The signature and footer details, editable in Settings.
     *
     * @return array{full_name: string, contact_email: string, signatory_name: string, signatory_position: string, signatory_committee: string}
     */
    private function organization(): array
    {
        return [
            'full_name' => (string) $this->settings->value('org.full_name', 'AGILE OUS — Academic Guild of IT Learners and Experts'),
            'contact_email' => (string) $this->settings->value('org.contact_email', (string) config('app.contact_email')),
            'signatory_name' => (string) $this->settings->value('email.signatory_name', ''),
            'signatory_position' => (string) $this->settings->value('email.signatory_position', 'Committee Head'),
            'signatory_committee' => (string) $this->settings->value('email.signatory_committee', 'Membership and Student Welfare Committee'),
        ];
    }

    /** Gmail drops style sheets, so the styles go on the elements themselves. */
    private function styleBody(string $html): string
    {
        // The template editor's sanitizer can put href before class, so the button matches in either order.
        $html = (string) preg_replace('/<a\b([^>]*?)\s+class="button"([^>]*)>/', '<a$1$2 style="display:inline-block;padding:10px 18px;background-color:#7a1a1a;color:#ffffff;font-weight:bold;text-decoration:none;">', $html);
        $html = (string) preg_replace('/<a class="button"([^>]*)>/', '<a$1 style="display:inline-block;padding:10px 18px;background-color:#7a1a1a;color:#ffffff;font-weight:bold;text-decoration:none;">', $html);

        // Every other link, leaving the button's own style alone.
        $html = (string) preg_replace('/<a (?![^>]*style=)/', '<a style="color:#0b57d0;text-decoration:underline;" ', $html);

        return str_replace(
            ['<p class="details">', '<p>'],
            [
                '<p style="margin:0 0 16px 0;padding:12px 14px;background-color:#f7f7f7;border-left:3px solid #c9a227;">',
                '<p style="margin:0 0 16px 0;">',
            ],
            $html
        );
    }
}
