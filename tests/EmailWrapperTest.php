<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Services\Mail\EmailBanner;
use App\Services\Mail\LogMailer;
use App\Services\Mail\SmtpMailer;
use App\Services\TemplateRenderer;
use PHPUnit\Framework\TestCase;

/** EMAIL E1: the wrapper, the banner and the house style of every template. */
final class EmailWrapperTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string}> */
    private function templates(): array
    {
        return Database::connection()->query('SELECT code, body_html FROM email_templates')->fetchAll(\PDO::FETCH_KEY_PAIR) ?: [];
    }

    /** @return array<string, string> Every placeholder the template uses, except the one the renderer fills in. */
    private function payloadFor(string $subject, string $body): array
    {
        preg_match_all('/\{([a-z_]+)\}/', $subject . $body, $matches);
        $payload = [];

        foreach (array_unique($matches[1]) as $key) {
            $payload[$key] = $key === 'link' ? '/track' : "value-{$key}";
        }

        unset($payload['contact_email']);

        return $payload;
    }

    public function testEveryTemplateFollowsTheHouseStyle(): void
    {
        $subjects = Database::connection()->query('SELECT code, subject FROM email_templates')->fetchAll(\PDO::FETCH_KEY_PAIR);
        $renderer = new TemplateRenderer();
        $this->assertGreaterThanOrEqual(38, count($subjects));

        foreach ($this->templates() as $code => $body) {
            $html = $renderer->render($code, $this->payloadFor($subjects[$code], $body))['html'];

            $this->assertMatchesRegularExpression('/Dear [^,<]+,/', $html, $code);
            $this->assertStringContainsString('Greetings from the AGILE OUS Membership and Student Welfare Committee!', $html, $code);
            $this->assertStringContainsString('For assistance, contact', $html, $code);
            $this->assertStringContainsString('Warm regards,', $html, $code);
            $this->assertStringContainsString('official communication from AGILE OUS', $html, $code);
            $this->assertStringContainsString('#c9a227', $html, $code);
            $this->assertStringContainsString('#7a1a1a', $html, $code);
            $this->assertStringNotContainsString('{', strip_tags($html), $code);
            $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $html, $code);
            $this->assertDoesNotMatchRegularExpression('/<(p|a)(\s+class="[a-z]+")?\s*>/', $html, "{$code} left an element unstyled");
            $this->assertStringNotContainsString('class=', $html, "{$code} kept a class, which Gmail would ignore");
        }
    }

    public function testSignatureAndOrganizationComeFromSettings(): void
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $set = $db->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?');

            foreach (['email.signatory_name' => 'Test Signatory', 'email.signatory_position' => 'Test Position', 'email.signatory_committee' => 'Test Committee', 'org.full_name' => 'Test Org Name', 'org.contact_email' => 'help@org.test'] as $key => $value) {
                $set->execute([$value, $key]);
                $this->assertSame(1, $set->rowCount(), "{$key} is not seeded");
            }

            $html = (new TemplateRenderer())->render('GUEST_EMAIL_CODE', ['code' => '123456'])['html'];

            $this->assertStringContainsString('<strong>Test Signatory</strong>', $html);
            $this->assertStringContainsString('Test Position', $html);
            $this->assertStringContainsString('<em>Test Committee</em>', $html);
            $this->assertStringContainsString('<strong>Test Org Name</strong>', $html);
            $this->assertSame(2, substr_count($html, 'mailto:help@org.test'), 'The help line and the footer both use it.');
        } finally {
            $db->rollBack();
        }
    }

    public function testOnlyAnnouncementsGetTheUnsubscribeNote(): void
    {
        $renderer = new TemplateRenderer();

        $this->assertStringNotContainsString('stop receiving announcements', $renderer->render('GUEST_EMAIL_CODE', ['code' => '1'])['html']);
        $this->assertStringContainsString('stop receiving announcements', $renderer->render('GUEST_EMAIL_CODE', ['code' => '1', 'announcement' => true])['html']);
    }

    public function testSmtpEmbedsTheBannerAsAnInlineImage(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'banner');
        file_put_contents($file, "\xFF\xD8\xFF\xE0fake-jpeg");

        try {
            $mail = (new SmtpMailer($file))->build('to@example.test', 'Subject', '<img src="cid:' . EmailBanner::CID . '" alt="x">');
            $mail->preSend();
            $message = $mail->getSentMIMEMessage();

            $this->assertStringContainsString('Content-ID: <' . EmailBanner::CID . '>', $message);
            $this->assertStringContainsString('Content-Disposition: inline', $message);
            $this->assertStringContainsString('multipart/related', $message);
        } finally {
            unlink($file);
        }
    }

    public function testSmtpAttachesNothingWhenTheEmailHasNoBanner(): void
    {
        $mail = (new SmtpMailer('C:/does/not/exist.jpg'))->build('to@example.test', 'Subject', '<p>No banner</p>');
        $mail->preSend();

        $this->assertStringNotContainsString('Content-ID:', $mail->getSentMIMEMessage());
    }

    public function testLogMailerPreviewPointsAtTheFile(): void
    {
        $this->assertSame('<img src="../../public/assets/img/email-banner.jpg">', EmailBanner::forPreview('<img src="cid:' . EmailBanner::CID . '">'));
        $this->assertTrue(method_exists(LogMailer::class, 'send'));
    }
}
