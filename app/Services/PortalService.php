<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CommitteeRepository;
use App\Models\ContentPostRepository;
use App\Models\SettingRepository;
use App\Models\VacancyRepository;

/** Everything here is public, so it only ever reads published posts and open vacancies (BR-12). */
final class PortalService
{
    public const LIST_SIZE = 12;

    public function __construct(
        private readonly ContentPostRepository $posts = new ContentPostRepository(),
        private readonly VacancyRepository $vacancies = new VacancyRepository(),
        private readonly CommitteeRepository $committees = new CommitteeRepository(),
        private readonly SettingRepository $settings = new SettingRepository(),
    ) {
    }

    /** @return array<string, mixed> */
    public function about(): array
    {
        return $this->organization() + ['committees' => $this->committees->allByName()];
    }

    /** @return array{data: list<array<string, mixed>>, page: int, perPage: int, lastPage: int, total: int} */
    public function posts(string $type, string $search, int $page, ?string $when = null): array
    {
        return $this->posts->publishedList($type, $search, $page, self::LIST_SIZE, $when);
    }

    /** @return array<string, mixed>|null */
    public function post(string $slug): ?array
    {
        return $this->posts->findPublishedBySlug($slug);
    }

    /** @return list<array<string, mixed>> */
    public function openPositions(): array
    {
        return $this->vacancies->openList();
    }

    /** @return array<string, mixed>|null */
    public function openPosition(int $id): ?array
    {
        return $this->vacancies->findOpen($id);
    }

    public function guestWelfareEnabled(): bool
    {
        return $this->settings->value('welfare.guest_submission', '1') === '1';
    }

    /** @return array{mission: string, vision: string, objectives: string} One item per line where there are several (Article II). */
    private function organization(): array
    {
        return [
            'mission' => (string) $this->settings->value('org.mission', ''),
            'vision' => (string) $this->settings->value('org.vision', ''),
            'objectives' => (string) $this->settings->value('org.objectives', ''),
        ];
    }
}
