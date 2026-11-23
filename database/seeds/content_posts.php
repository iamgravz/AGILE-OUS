<?php

declare(strict_types=1);

// The posts in content_post_list.php, published by the MSW Committee Head's seeded account. Outside production only.
// Found again by slug, so running this twice adds nothing.
return static function (PDO $db): void {
    $author = (int) $db->query("SELECT user_id FROM users WHERE email = 'mswhead@agile.test'")->fetchColumn();
    $now = date('Y-m-d H:i:s');
    $inDays = static fn (int $days, string $time): string => date('Y-m-d', strtotime("+{$days} days")) . " {$time}";
    $posts = (require __DIR__ . '/content_post_list.php')($inDays);

    foreach ($posts as $post) {
        seed_row($db, 'content_posts', 'post_id', ['slug' => $post['slug']], [
            'type' => $post['type'],
            'title' => $post['title'],
            'excerpt' => $post['excerpt'],
            'body' => $post['body'],
            'event_start' => $post['start'],
            'event_end' => $post['end'],
            'venue_or_link' => $post['venue'],
            'status' => 'published',
            'author_id' => $author,
            'approved_by' => $author,
            'published_at' => $now,
        ]);
    }

    // Free Pexels photos as covers (seed-media/SOURCES.md), copied once into storage/uploads/media. A post that already
    // has a cover, for example one changed in the CMS, keeps it. The files in the repo are copied, never moved.
    $find = $db->prepare('SELECT post_id FROM content_posts WHERE slug = ? AND cover_image IS NULL');
    $set = $db->prepare('UPDATE content_posts SET cover_image = ? WHERE post_id = ?');

    foreach ($posts as $post) {
        $photo = __DIR__ . '/seed-media/' . $post['photo'] . '.jpg';

        if ($post['photo'] === null || !is_file($photo)) {
            continue;
        }

        $find->execute([$post['slug']]);
        $postId = $find->fetchColumn();

        if ($postId === false) {
            continue;
        }

        $stored = 'media/' . date('Y/m') . '/' . bin2hex(random_bytes(16)) . '.jpg';
        $target = \App\Core\Config::basePath('storage/uploads/' . $stored);

        if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0750, true) && !is_dir(dirname($target))) {
            continue;
        }

        if (copy($photo, $target)) {
            $set->execute([$stored, (int) $postId]);
        }
    }
};
