<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\EmailTemplateRepository;
use DomainException;

/** Email templates, for email_template.manage. A template may use only the values its code passes in. */
final class EmailTemplateService
{
    private const EXAMPLES = [
        'name' => 'Andrea', 'tracking_code' => 'AGL7K2M9QX4B', 'case_code' => 'CAS4T8N2PL6R', 'link' => '/track', 'member_no' => 'AGILE-2026-0001',
        'student_no' => '2024-00101-OU-0', 'register_url' => 'http://localhost:8000/register', 'remarks' => 'Please upload a clearer copy.', 'status' => 'Under review',
        'count' => '3', 'code' => '482915', 'position' => 'Committee Member', 'committee' => 'Academics and Training', 'slots' => '2', 'slots_filled' => '1', 'deadline' => 'November 30, 2026',
        'term' => '2026-2027, 1st Semester', 'scheduled_at' => 'November 3, 2026, 2:30 PM', 'mode' => 'Online', 'location' => 'https://meet.example.test/agile', 'effective_date' => 'November 15, 2026',
        'title' => 'Example title', 'author' => 'Bianca Cruz', 'document' => 'School ID', 'due_at' => 'November 10, 2026', 'link_text' => 'Open my member profile',
        'next_step' => 'Your digital ID and certificate will be ready after the MSW Committee Head reviews them.', 'message' => 'Your application draft is saved. Use this link to continue it.', 'action' => 'Continue my application',
        'subject' => 'Example announcement', 'body_html' => '<p>This is where your message goes.</p>',
    ];

    public function __construct(
        private readonly EmailTemplateRepository $templates = new EmailTemplateRepository(),
        private readonly TemplateRenderer $renderer = new TemplateRenderer(),
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        authorize('email_template.manage');

        return $this->templates->allByCode();
    }

    /** @return array<string, mixed> */
    public function find(int $id): array
    {
        authorize('email_template.manage');

        return $this->templates->find($id) ?? throw new DomainException('This template no longer exists.');
    }

    /** @return list<string> What this template may use: the original wording's values, plus the contact email. */
    public function placeholders(string $code, string $subject, string $body): array
    {
        $original = (require Config::basePath('database/seeds/email_template_text.php'))[$code] ?? [$subject, $body];

        return array_values(array_unique([...self::found($original[0] . $original[1]), 'contact_email']));
    }

    /** @throws DomainException */
    public function update(int $id, string $subject, string $body): void
    {
        authorize('email_template.manage');

        $template = $this->find($id);
        $body = (new HtmlSanitizer())->cleanEmail($body);
        $this->assertUsable($template['code'], $subject, $body);

        $this->templates->update($id, ['subject' => trim($subject), 'body_html' => $body, 'updated_by' => current_user_id()]);
        AuditService::log('email_template.updated', 'email_template', $id, [
            'code' => $template['code'],
            'subject_before' => $template['subject'],
            'subject_after' => trim($subject),
            'body_changed' => $template['body_html'] !== $body,
        ]);
    }

    /** The real wrapper and example values, for the unsaved text. */
    public function preview(int $id, string $subject, string $body): string
    {
        authorize('email_template.manage');

        $template = $this->find($id);
        $body = (new HtmlSanitizer())->cleanEmail($body);
        $this->assertUsable($template['code'], $subject, $body);

        $html = $this->renderer->renderText($subject, $body, $this->examples($template['code'], $subject, $body))['html'];

        return str_replace('cid:' . Mail\EmailBanner::CID, asset('img/email-banner.jpg'), $html);
    }

    /** Sends the saved template, with example values, to the person signed in. */
    public function sendTest(int $id): void
    {
        authorize('email_template.manage');

        $template = $this->find($id);
        $userId = (int) current_user_id();
        $payload = $this->examples($template['code'], $template['subject'], $template['body_html']);
        unset($payload['contact_email'], $payload['name']);

        (new NotificationService())->notify($userId, $template['code'], $payload, null, ['email']);
        AuditService::log('email_template.test_sent', 'email_template', $id, ['code' => $template['code']]);
    }

    /** @throws DomainException */
    private function assertUsable(string $code, string $subject, string $body): void
    {
        if (trim($subject) === '' || mb_strlen(trim($subject)) > 200) {
            throw new DomainException('Please write a subject of up to 200 characters.');
        }

        if (trim(strip_tags($body)) === '') {
            throw new DomainException('Please write the email.');
        }

        $unknown = array_diff(self::found($subject . $body), $this->placeholders($code, $subject, $body));

        if ($unknown !== []) {
            throw new DomainException('This template cannot use {' . implode('}, {', $unknown) . '}. Please use only the listed placeholders.');
        }
    }

    /** @return array<string, mixed> */
    private function examples(string $code, string $subject, string $body): array
    {
        $payload = [];

        foreach ($this->placeholders($code, $subject, $body) as $key) {
            if ($key !== 'contact_email') {
                $payload[$key] = self::EXAMPLES[$key] ?? 'Example ' . str_replace('_', ' ', $key);
            }
        }

        return $payload + ['announcement' => $code === 'ANNOUNCEMENT'];
    }

    /** @return list<string> */
    private static function found(string $text): array
    {
        preg_match_all('/\{([a-z_]+)\}/', $text, $matches);

        return array_values(array_unique($matches[1]));
    }
}
