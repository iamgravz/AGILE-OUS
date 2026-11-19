<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Models\ContentPostRepository;
use DomainException;
use InvalidArgumentException;

final class CmsService
{
    public const TYPES = ['news', 'event', 'publication', 'announcement'];
    public const STATUSES = ['draft', 'for_approval', 'published', 'returned', 'archived'];

    /** Section 8. */
    private const TRANSITIONS = [
        'draft' => ['for_approval'],
        'for_approval' => ['published', 'returned'],
        'returned' => ['for_approval'],
        'published' => ['archived'],
        'archived' => [],
    ];

    private const EDITABLE = ['draft', 'returned'];

    public function __construct(
        private readonly ContentPostRepository $posts = new ContentPostRepository(),
        private readonly FileStorageService $files = new FileStorageService(),
        private readonly HtmlSanitizer $sanitizer = new HtmlSanitizer(),
        private readonly NotificationService $notifications = new NotificationService(),
    ) {
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function isEditable(string $status): bool
    {
        return in_array($status, self::EDITABLE, true);
    }

    /**
     * Authors see their own posts, and the MSW Committee Head sees everything.
     *
     * @param array{status?: string, type?: string, q?: string} $filters
     */
    public function list(array $filters, int $page): array
    {
        authorize('content.draft');

        if (!can('content.publish')) {
            $filters['author_id'] = (int) current_user_id();
        }

        return $this->posts->search($filters, $page);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        authorize('content.draft');

        $post = $this->posts->find($id);

        if ($post !== null) {
            $this->assertCanAccess($post);
        }

        return $post;
    }

    public function clean(string $html): string
    {
        return $this->sanitizer->clean($html);
    }

    /**
     * Creates or updates a post in place. The status does not change here, so saving keeps a returned post returned.
     *
     * @param array{type: string, title: string, slug: string, excerpt: string, body: string, event_start: ?string, event_end: ?string, venue_or_link: ?string} $data
     * @param array<string, mixed>|null $cover An uploaded file, or null to keep the current one.
     * @throws FileRejectedException
     */
    public function save(?int $id, array $data, ?array $cover): int
    {
        authorize('content.draft');

        if (!in_array($data['type'], self::TYPES, true)) {
            throw new InvalidArgumentException('Unknown content type.');
        }

        $coverPath = $cover === null ? null : $this->files->store($cover, 'media', ['jpg', 'jpeg', 'png'])['stored_path'];
        $previousCover = null;

        $values = [
            'type' => $data['type'],
            'title' => $data['title'],
            'excerpt' => $data['excerpt'],
            'body' => $this->sanitizer->clean($data['body']),
            'event_start' => $data['type'] === 'event' ? $data['event_start'] : null,
            'event_end' => $data['type'] === 'event' ? $data['event_end'] : null,
            'venue_or_link' => $data['type'] === 'event' ? $data['venue_or_link'] : null,
        ];

        if ($coverPath !== null) {
            $values['cover_image'] = $coverPath;
        }

        $savedId = Database::transaction(function () use ($id, $data, $values, &$previousCover): int {
            if ($id === null) {
                $newId = $this->posts->insert($values + [
                    'slug' => $this->uniqueSlug($data['slug'] !== '' ? $data['slug'] : $data['title'], null),
                    'status' => 'draft',
                    'author_id' => current_user_id(),
                ]);
                AuditService::log('content.created', 'content_post', $newId, ['type' => $data['type']]);

                return $newId;
            }

            $post = $this->lockedPost($id);
            $this->assertCanAccess($post);

            if (!self::isEditable($post['status'])) {
                throw new DomainException('This post cannot be edited in its current status.');
            }

            $previousCover = isset($values['cover_image']) ? $post['cover_image'] : null;
            $this->posts->update($id, $values + ['slug' => $this->uniqueSlug($data['slug'] !== '' ? $data['slug'] : $data['title'], $id)]);
            AuditService::log('content.updated', 'content_post', $id);

            return $id;
        });

        if ($previousCover !== null) {
            $this->files->delete($previousCover);
        }

        return $savedId;
    }

    /** draft or returned -> for_approval, and tells the MSW Committee Head. */
    public function submit(int $id): void
    {
        authorize('content.draft');

        Database::transaction(function () use ($id): void {
            $post = $this->lockedPost($id);
            $this->assertCanAccess($post);
            $this->assertComplete($post);
            $this->transition($post, 'for_approval', 'content.submitted');

            $author = current_user();
            $this->notifications->notifyRole('msw_head', 'CONTENT_FOR_APPROVAL', [
                'title' => $post['title'],
                'author' => trim($author['first_name'] . ' ' . $author['last_name']),
            ], "/cms/{$id}/review");
        });
    }

    public function publish(int $id, string $remarks): void
    {
        authorize('content.publish');
        $this->requireRemarks($remarks);

        Database::transaction(function () use ($id, $remarks): void {
            $post = $this->lockedPost($id);
            $this->transition($post, 'published', 'content.published', [
                'review_remarks' => $remarks,
                'approved_by' => current_user_id(),
                'published_at' => date('Y-m-d H:i:s'),
            ]);
        });
    }

    /** for_approval -> returned, and tells the author why. */
    public function returnToAuthor(int $id, string $remarks): void
    {
        authorize('content.publish');
        $this->requireRemarks($remarks);

        Database::transaction(function () use ($id, $remarks): void {
            $post = $this->lockedPost($id);
            $this->transition($post, 'returned', 'content.returned', ['review_remarks' => $remarks]);
            $this->notifications->notify((int) $post['author_id'], 'CONTENT_RETURNED', [
                'title' => $post['title'],
                'remarks' => $remarks,
            ], "/cms/{$id}/edit");
        });
    }

    public function archive(int $id): void
    {
        authorize('content.publish');

        Database::transaction(function () use ($id): void {
            $this->transition($this->lockedPost($id), 'archived', 'content.archived');
        });
    }

    /**
     * Saves an image placed inside a post's body.
     *
     * @param array<string, mixed> $file
     * @throws FileRejectedException
     */
    public function uploadBodyImage(array $file): string
    {
        authorize('content.draft');

        $path = $this->files->store($file, 'media', ['jpg', 'jpeg', 'png'])['stored_path'];
        AuditService::log('content.image_uploaded', 'content_post');

        return media_url($path);
    }

    /** @param array<string, mixed> $post */
    private function transition(array $post, string $to, string $action, array $values = []): void
    {
        $from = $post['status'];

        if (!self::canTransition($from, $to)) {
            throw new DomainException("A post cannot move from {$from} to {$to}.");
        }

        $id = (int) $post['post_id'];
        $this->posts->update($id, $values + ['status' => $to]);
        AuditService::log($action, 'content_post', $id, ['from' => $from, 'to' => $to]);
    }

    /** @return array<string, mixed> */
    private function lockedPost(int $id): array
    {
        return $this->posts->findForUpdate($id) ?? throw new DomainException('This post no longer exists.');
    }

    /** @param array<string, mixed> $post */
    private function assertCanAccess(array $post): void
    {
        if ((int) $post['author_id'] !== current_user_id() && !can('content.publish')) {
            throw new ForbiddenException('content.own_post');
        }
    }

    /** The controller shows these per field. This stops an incomplete post reaching review by any other path. */
    private function assertComplete(array $post): void
    {
        $isEvent = $post['type'] === 'event';

        if ($post['title'] === '' || $post['excerpt'] === '' || trim(strip_tags($post['body'], '<img>')) === ''
            || ($isEvent && ($post['event_start'] === null || (string) $post['venue_or_link'] === ''))) {
            throw new DomainException('This post is missing required details.');
        }
    }

    private function requireRemarks(string $remarks): void
    {
        if (trim($remarks) === '') {
            throw new InvalidArgumentException('Remarks are required.');
        }
    }

    private function uniqueSlug(string $source, ?int $exceptId): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($source)), '-');
        $base = substr($base, 0, 200) ?: 'post';
        $slug = $base;

        for ($suffix = 2; $this->posts->slugExists($slug, $exceptId); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
