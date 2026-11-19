<?php

declare(strict_types=1);

namespace App\Models;

final class ContentPostRepository extends BaseRepository
{
    protected string $table = 'content_posts';
    protected string $primaryKey = 'post_id';

    /** Locks the row, so two people cannot act on the same post at once. Use inside a transaction. */
    public function findForUpdate(int $id): ?array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM content_posts WHERE post_id = ? FOR UPDATE');
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    public function findPublishedBySlug(string $slug): ?array
    {
        return $this->findBy(['slug' => $slug, 'status' => 'published'])[0] ?? null;
    }

    /**
     * Published posts of one type. For events, $when is 'upcoming' (soonest first) or 'past' (latest first).
     *
     * @return array{data: list<array<string, mixed>>, page: int, perPage: int, lastPage: int, total: int}
     */
    public function publishedList(string $type, string $search, int $page, int $perPage, ?string $when = null): array
    {
        $conditions = "status = 'published' AND type = ?";
        $params = [$type];
        $order = 'published_at DESC, post_id DESC';

        if ($search !== '') {
            $conditions .= " AND title LIKE ? ESCAPE '|'";
            $params[] = '%' . strtr($search, ['|' => '||', '%' => '|%', '_' => '|_']) . '%';
        }

        if ($when === 'upcoming') {
            $conditions .= ' AND COALESCE(event_end, event_start) >= ?';
            $params[] = date('Y-m-d H:i:s');
            $order = 'event_start ASC, post_id ASC';
        } elseif ($when === 'past') {
            $conditions .= ' AND COALESCE(event_end, event_start) < ?';
            $params[] = date('Y-m-d H:i:s');
            $order = 'event_start DESC, post_id DESC';
        }

        $count = $this->pdo()->prepare("SELECT COUNT(*) FROM content_posts WHERE {$conditions}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));
        $offset = ($page - 1) * $perPage;

        // $order comes from the fixed choices above. $perPage and $offset are integers.
        $rows = $this->pdo()->prepare("SELECT * FROM content_posts WHERE {$conditions} ORDER BY {$order} LIMIT {$perPage} OFFSET {$offset}");
        $rows->execute($params);

        return ['data' => $rows->fetchAll(), 'page' => $page, 'perPage' => $perPage, 'lastPage' => $lastPage, 'total' => $total];
    }

    /** @return list<array<string, mixed>> Newest announcements and news together, for the home page. */
    public function latestNotices(int $limit): array
    {
        $limit = max(1, $limit);

        return $this->pdo()->query("SELECT * FROM content_posts WHERE status = 'published' AND type IN ('announcement', 'news') ORDER BY published_at DESC, post_id DESC LIMIT {$limit}")->fetchAll();
    }

    /** @return list<array<string, mixed>> Published events and news that have a cover photo. */
    public function publishedCoverPhotos(int $limit): array
    {
        $limit = max(1, $limit);

        return $this->pdo()->query("SELECT title, cover_image FROM content_posts WHERE status = 'published' AND type IN ('event', 'news') AND cover_image IS NOT NULL ORDER BY published_at DESC, post_id DESC LIMIT {$limit}")->fetchAll();
    }

    public function slugExists(string $slug, ?int $exceptId): bool
    {
        $statement = $this->pdo()->prepare('SELECT COUNT(*) FROM content_posts WHERE slug = ? AND post_id <> ?');
        $statement->execute([$slug, $exceptId ?? 0]);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * @param array{author_id?: int, status?: string, type?: string, q?: string} $filters
     * @return array{data: list<array<string, mixed>>, page: int, perPage: int, lastPage: int, total: int}
     */
    public function search(array $filters, int $page, int $perPage = 20): array
    {
        $conditions = [];
        $params = [];

        if (isset($filters['author_id'])) {
            $conditions[] = 'p.author_id = ?';
            $params[] = $filters['author_id'];
        }

        if (isset($filters['status'])) {
            $conditions[] = 'p.status = ?';
            $params[] = $filters['status'];
        }

        if (isset($filters['type'])) {
            $conditions[] = 'p.type = ?';
            $params[] = $filters['type'];
        }

        if (isset($filters['q'])) {
            $conditions[] = "p.title LIKE ? ESCAPE '|'";
            $params[] = '%' . strtr($filters['q'], ['|' => '||', '%' => '|%', '_' => '|_']) . '%';
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $count = $this->pdo()->prepare("SELECT COUNT(*) FROM content_posts p{$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));
        $offset = ($page - 1) * $perPage;

        // $perPage and $offset are integers, so they are safe to place in the SQL text.
        $rows = $this->pdo()->prepare(
            "SELECT p.*, CONCAT(u.first_name, ' ', u.last_name) AS author_name
             FROM content_posts p
             JOIN users u ON u.user_id = p.author_id
             {$where}
             ORDER BY p.post_id DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $rows->execute($params);

        return ['data' => $rows->fetchAll(), 'page' => $page, 'perPage' => $perPage, 'lastPage' => $lastPage, 'total' => $total];
    }
}
