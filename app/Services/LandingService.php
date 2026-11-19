<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicTermRepository;
use App\Models\CommitteeRepository;
use App\Models\ContentPostRepository;
use App\Models\MemberRepository;
use App\Models\SettingRepository;
use App\Models\VacancyRepository;

/** The public home page. Wording comes from Settings and everything else from the database, so no figure is invented. */
final class LandingService
{
    public const SECTIONS = [
        'hero' => 'Hero',
        'numbers' => 'Numbers',
        'committees' => 'What AGILE does',
        'join' => 'How to join',
        'positions' => 'Open positions and upcoming events',
        'notices' => 'Latest announcements',
        'cta' => 'Closing band',
        'gallery' => 'Gallery',
    ];
    // Committees moved to the nav bar (About, "Our committees"); Settings can still show them here.
    public const OFF_BY_DEFAULT = ['gallery', 'committees'];

    public const DEFAULT_HEADLINE = 'Academic Guild of IT Learners and Experts';
    public const DEFAULT_SUBHEADLINE = 'The BSIT student organization of the PUP Open University System.';
    public const DEFAULT_TAGLINES = 'Learn, Collaborate, Innovate, Grow';
    public const DEFAULT_JOIN_STEPS = "Apply online | No account needed.\n"
        . "Verify documents | We check your ID and enrollment.\n"
        . "Interview/Decision | Interviews apply to positions only.\n"
        . 'Register your account | Use your student number.';
    // Settings saved before the wording was shortened hold this. It is treated as the default, not as an edit.
    private const OLD_DEFAULT_JOIN_STEPS = "Apply online | Fill in the membership form. You do not need an account, and we verify your email with a one-time code.\n"
        . "Verify documents | The MSW Committee checks your school ID and proof of enrollment.\n"
        . "Interview/Decision | Applicants for a position are also interviewed. We email you the decision.\n"
        . 'Register your account | Once approved, register with your student number. The MSW Committee Head then approves your account.';
    public const JOIN_STEP_COUNT = 4;

    private const LIST_ROWS = 3;
    private const NOTICES = 3;
    private const PHOTOS = 6;

    public function __construct(
        private readonly SettingRepository $settings = new SettingRepository(),
        private readonly MemberRepository $members = new MemberRepository(),
        private readonly CommitteeRepository $committees = new CommitteeRepository(),
        private readonly VacancyRepository $vacancies = new VacancyRepository(),
        private readonly ContentPostRepository $posts = new ContentPostRepository(),
        private readonly AcademicTermRepository $terms = new AcademicTermRepository(),
        private readonly FileStorageService $files = new FileStorageService(),
    ) {
    }

    /** @return array<string, mixed> */
    public function page(): array
    {
        $committees = $this->committees->allByName();
        $vacancies = $this->vacancies->openList();
        $events = $this->posts->publishedList('event', '', 1, self::LIST_ROWS, 'upcoming');

        return [
            'shown' => $this->shownSections(),
            'headline' => $this->text('landing.headline', self::DEFAULT_HEADLINE),
            'subheadline' => $this->text('landing.subheadline', self::DEFAULT_SUBHEADLINE),
            'taglines' => self::taglinesFrom($this->text('landing.taglines', self::DEFAULT_TAGLINES)),
            'heroImage' => $this->text('landing.hero_image', ''),
            'term' => $this->terms->current(),
            // The terms table has no start or end dates, so this counts every published event still to come.
            'numbers' => [
                ['label' => 'Active members', 'value' => $this->members->activeCount()],
                ['label' => 'Committees', 'value' => count($committees)],
                ['label' => 'Open positions', 'value' => count($vacancies)],
                ['label' => 'Upcoming events', 'value' => $events['total']],
            ],
            'vacancies' => array_slice($vacancies, 0, self::LIST_ROWS),
            'committees' => self::sourceCodeLast($committees),
            'joinSteps' => self::joinStepsFrom($this->joinStepsText()),
            'events' => $events['data'],
            'notices' => $this->posts->latestNotices(self::NOTICES),
            'photos' => $this->photos(),
        ];
    }

    /** @return list<string> */
    public function shownSections(): array
    {
        $saved = $this->settings->value('landing.show_sections');
        $list = $saved === null ? null : json_decode($saved, true);

        if (!is_array($list)) {
            return array_values(array_diff(array_keys(self::SECTIONS), self::OFF_BY_DEFAULT));
        }

        // A list with "intro" was saved before Gallery became off by default, when it was ticked for everyone.
        if (in_array('intro', $list, true)) {
            $list = array_diff($list, self::OFF_BY_DEFAULT);
        }

        return array_values(array_intersect(array_keys(self::SECTIONS), $list));
    }

    public function joinStepsText(): string
    {
        $saved = str_replace("\r\n", "\n", $this->text('landing.join_steps', self::DEFAULT_JOIN_STEPS));

        return $saved === self::OLD_DEFAULT_JOIN_STEPS ? self::DEFAULT_JOIN_STEPS : $saved;
    }

    /** Up to three letters for a committee's small mark: The Source Code is SC, Academic is AC. */
    public static function initials(string $name): string
    {
        $words = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: [], static fn (string $word): bool => !in_array(strtolower($word), ['the', 'and', 'of'], true)));

        if (count($words) === 1) {
            return mb_strtoupper(mb_substr($words[0], 0, 2));
        }

        return mb_strtoupper(implode('', array_map(static fn (string $word): string => mb_substr($word, 0, 1), array_slice($words, 0, 3))));
    }

    /** @return list<string> */
    public static function taglinesFrom(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $tagline): bool => $tagline !== ''));
    }

    /**
     * One step per line, as "Title | Description".
     *
     * @return list<array{title: string, text: string}>
     */
    public static function joinStepsFrom(string $value): array
    {
        $steps = [];

        foreach (preg_split('/\R/', $value) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line, 2));
            $steps[] = ['title' => $parts[0], 'text' => $parts[1] ?? ''];
        }

        return $steps;
    }

    /**
     * Cover photos of published events and news. A file that is missing or is not an image is left out.
     *
     * @return list<array{url: string, title: string, width: int, height: int}>
     */
    private function photos(): array
    {
        $photos = [];

        foreach ($this->posts->publishedCoverPhotos(self::PHOTOS) as $post) {
            $size = @getimagesize($this->files->path($post['cover_image']));

            if ($size !== false) {
                $photos[] = ['url' => media_url($post['cover_image']), 'title' => $post['title'], 'width' => $size[0], 'height' => $size[1]];
            }
        }

        return $photos;
    }

    /**
     * @param list<array<string, mixed>> $committees
     * @return list<array<string, mixed>>
     */
    private static function sourceCodeLast(array $committees): array
    {
        usort($committees, static fn (array $a, array $b): int => ($a['name'] === 'The Source Code') <=> ($b['name'] === 'The Source Code'));

        return $committees;
    }

    private function text(string $key, string $default): string
    {
        return (string) $this->settings->value($key, $default);
    }
}
