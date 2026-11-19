<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Models\SettingRepository;
use App\Models\UserRepository;
use App\Services\LandingService;
use App\Services\RbacService;
use App\Services\SettingsService;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestServer;

/** P7. Needs the seeded database. Tests that save settings roll back, and the HTTP tests put back what they change. */
final class LandingPageTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
        RbacService::clearCache();
    }

    public function testCommitteeInitials(): void
    {
        $this->assertSame('AC', LandingService::initials('Academic'));
        $this->assertSame('SC', LandingService::initials('The Source Code'));
        $this->assertSame('MSW', LandingService::initials('Membership and Student Welfare'));
    }

    public function testGalleryIsOffByDefaultAndOldSavedListsLoseIt(): void
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $settings = $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            $db->prepare('DELETE FROM settings WHERE setting_key = ?')->execute(['landing.show_sections']);
            $this->assertNotContains('gallery', (new LandingService())->shownSections());
            $this->assertContains('positions', (new LandingService())->shownSections());

            $settings->execute(['landing.show_sections', '["hero","positions","intro","events","gallery","cta"]']);
            $this->assertSame(['hero', 'positions', 'cta'], (new LandingService())->shownSections());

            $settings->execute(['landing.show_sections', '["hero","gallery"]']);
            $this->assertSame(['hero', 'gallery'], (new LandingService())->shownSections());
        } finally {
            $db->rollBack();
        }
    }

    public function testSavedOldStepWordingBecomesTheNewDefault(): void
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $old = "Apply online | Fill in the membership form. You do not need an account, and we verify your email with a one-time code.\r\n"
                . "Verify documents | The MSW Committee checks your school ID and proof of enrollment.\r\n"
                . "Interview/Decision | Applicants for a position are also interviewed. We email you the decision.\r\n"
                . 'Register your account | Once approved, register with your student number. The MSW Committee Head then approves your account.';
            $settings = $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            $settings->execute(['landing.join_steps', $old]);
            $this->assertSame(LandingService::DEFAULT_JOIN_STEPS, (new LandingService())->joinStepsText());

            $settings->execute(['landing.join_steps', "A | a\nB | b\nC | c\nD | d"]);
            $this->assertSame("A | a\nB | b\nC | c\nD | d", (new LandingService())->joinStepsText());
        } finally {
            $db->rollBack();
        }
    }

    public function testTaglinesAndJoinStepsAreParsed(): void
    {
        $this->assertSame(['Learn', 'Collaborate', 'Innovate', 'Grow'], LandingService::taglinesFrom(LandingService::DEFAULT_TAGLINES));
        $this->assertSame([], LandingService::taglinesFrom(' , '));

        $steps = LandingService::joinStepsFrom(LandingService::DEFAULT_JOIN_STEPS);
        $this->assertCount(LandingService::JOIN_STEP_COUNT, $steps);
        $this->assertSame('Apply online', $steps[0]['title']);
    }

    public function testSettingsRejectBadLandingValuesAndSaveGoodOnes(): void
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $_SESSION['user_id'] = (int) (new UserRepository())->findBy(['email' => 'mswhead@agile.test'])[0]['user_id'];
            RbacService::clearCache();
            $service = new SettingsService();
            $input = $this->validSettings($service);

            $errors = $service->save(['landing_taglines' => ' , ', 'landing_join_steps' => "Only one | step", 'landing_headline' => ''] + $input);
            $this->assertArrayHasKey('landing_taglines', $errors);
            $this->assertArrayHasKey('landing_join_steps', $errors);
            $this->assertArrayHasKey('landing_headline', $errors);

            $noText = (string) preg_replace('/^Apply online.*$/m', 'Apply online', LandingService::DEFAULT_JOIN_STEPS);
            $this->assertArrayHasKey('landing_join_steps', $service->save(['landing_join_steps' => $noText] + $input));

            $saved = ['landing_headline' => 'New headline', 'landing_show_sections' => ['hero', 'join', 'not-a-section']] + $input;
            $this->assertSame([], $service->save($saved));

            $settings = new SettingRepository();
            $this->assertSame('New headline', $settings->value('landing.headline'));
            $this->assertSame(['hero', 'join'], (new LandingService())->shownSections());
        } finally {
            $db->rollBack();
        }
    }

    public function testHomeShowsRealContentWithNoLibraryOrOutsideScript(): void
    {
        $body = (new TestServer())->get('/')['body'];

        $this->assertStringContainsString('css/landing.css', $body);
        $this->assertStringContainsString('js/landing.js', $body);
        $this->assertStringContainsString('Learn · Collaborate · Innovate · Grow', $body);
        $this->assertStringContainsString('/about#section-our-mission', $body);

        // Nothing moves by itself, and the page has six sections at most.
        foreach (['data-ticker', 'data-carousel', 'data-network', '<canvas', 'data-countdown', 'data-taglines', 'home-gallery', 'mission-vision'] as $gone) {
            $this->assertStringNotContainsString($gone, $body);
        }

        $this->assertSame(6, substr_count($body, '<section'));

        // Every count is in the HTML, so the page is complete without JavaScript.
        $db = TestServer::db();
        $members = (int) $db->query("SELECT COUNT(*) FROM members WHERE status = 'active'")->fetchColumn();
        $this->assertStringContainsString('data-count="' . $members . '">' . $members . '</dd>', $body);
        $this->assertSame(1, substr_count($body, '<h1'));
        $this->assertDoesNotMatchRegularExpression('#<(script|link)[^>]+(src|href)="https?://#i', $body);

        $code = (string) file_get_contents(dirname(__DIR__) . '/public/assets/js/landing.js') . file_get_contents(dirname(__DIR__) . '/public/assets/css/landing.css');
        $this->assertDoesNotMatchRegularExpression('/gsap|lottie|three\.js|jquery|\bAOS\b|https?:\/\//i', $code);
    }

    public function testHiddenSectionsLeaveThePageAndHidingTheHeroKeepsTheHeading(): void
    {
        $db = TestServer::db();
        $find = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $find->execute(['landing.show_sections']);
        $before = $find->fetchColumn();
        $set = $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');

        try {
            $set->execute(['landing.show_sections', json_encode(['join'], JSON_THROW_ON_ERROR)]);
            $body = (new TestServer())->get('/')['body'];

            $this->assertStringContainsString('id="how-to-join"', $body);
            $this->assertStringNotContainsString('data-hero', $body);
            $this->assertStringNotContainsString('data-count', $body);
            $this->assertStringNotContainsString('home-committees', $body);
            $this->assertStringNotContainsString('home-cta', $body);
            $this->assertStringNotContainsString('home-lists', $body);
            $this->assertSame(1, substr_count($body, '<h1'));
        } finally {
            if ($before === false) {
                $db->prepare('DELETE FROM settings WHERE setting_key = ?')->execute(['landing.show_sections']);
            } else {
                $set->execute(['landing.show_sections', $before]);
            }
        }
    }

    public function testOnlySettingsPermissionHoldersCanChangeTheLandingPage(): void
    {
        $response = TestServer::loggedIn('member@agile.test')->post('/admin/settings', ['landing_headline' => 'Changed by a member']);

        $this->assertSame(403, $response['status']);
        $find = TestServer::db()->prepare('SELECT COUNT(*) FROM settings WHERE setting_value = ?');
        $find->execute(['Changed by a member']);
        $this->assertSame(0, (int) $find->fetchColumn());
    }

    /** @return array<string, mixed> */
    private function validSettings(SettingsService $service): array
    {
        $current = $service->current();
        $input = ['rubric_min' => (string) $current['rubric']['scale']['min'], 'rubric_max' => (string) $current['rubric']['scale']['max']];

        foreach ($current['values'] as $key => $value) {
            $input[SettingsService::fieldName($key)] = $value;
        }

        foreach ($current['rubric']['criteria'] as $i => $row) {
            $input['rubric_key'][$i] = $row['key'];
            $input['rubric_label'][$i] = $row['label'];
            $input['rubric_weight'][$i] = (string) $row['weight'];
        }

        return $input;
    }
}
