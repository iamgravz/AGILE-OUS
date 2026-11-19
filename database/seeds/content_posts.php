<?php

declare(strict_types=1);

// Demo posts for testing the public pages. Every title starts with [Demo], and none go into production.
return static function (PDO $db): void {
    $author = (int) $db->query("SELECT user_id FROM users WHERE email = 'mswhead@agile.test'")->fetchColumn();
    $now = date('Y-m-d H:i:s');
    $inDays = static fn (int $days, string $time): string => date('Y-m-d', strtotime("+{$days} days")) . " {$time}";

    $posts = [
        ['news', '[Demo] Sample news: recruitment opens soon', 'demo-news-recruitment', 'A sample news post for testing. It is not a real announcement.', '<p>This is a <strong>sample news post</strong> for testing the portal. It is not a real announcement.</p><h2>What this shows</h2><ul><li>Headings and lists</li><li>Bold and italic text</li></ul>', null, null, null],
        ['news', '[Demo] Sample news: committee highlights', 'demo-news-highlights', 'A second sample news post for testing lists and pagination.', '<p>Another <em>sample news post</em>, included so list pages have more than one item.</p><blockquote>This quote is sample text.</blockquote>', null, null, null],
        ['event', '[Demo] Sample event: online orientation', 'demo-event-orientation', 'A sample online event for testing. It is not a real event.', '<p>This <strong>sample event</strong> shows the date, time and online link on a public page.</p>', $inDays(30, '09:00:00'), $inDays(30, '11:00:00'), 'https://example.com/sample-meeting'],
        ['event', '[Demo] Sample event: skills workshop', 'demo-event-workshop', 'A sample on-site event for testing. It is not a real event.', '<p>This <strong>sample event</strong> shows a venue instead of an online link.</p>', $inDays(45, '13:00:00'), $inDays(45, '16:00:00'), 'Sample venue, for testing only'],
        ['publication', '[Demo] Sample publication: student newsletter', 'demo-publication-newsletter', 'A sample publication for testing. It is not a real newsletter.', '<p>This is a <strong>sample publication</strong> for testing the portal.</p><p>Real publications will appear here after the MSW Committee Head approves them.</p>', null, null, null],
        ['announcement', '[Demo] Sample announcement: portal testing', 'demo-announcement-testing', 'A sample announcement for testing. It is not a real notice.', '<p>This is a <strong>sample announcement</strong> for testing the portal. No action is needed.</p>', null, null, null],
    ];

    foreach ($posts as [$type, $title, $slug, $excerpt, $body, $start, $end, $venue]) {
        seed_row($db, 'content_posts', 'post_id', ['slug' => $slug], [
            'type' => $type,
            'title' => $title,
            'excerpt' => $excerpt,
            'body' => $body,
            'event_start' => $start,
            'event_end' => $end,
            'venue_or_link' => $venue,
            'status' => 'published',
            'review_remarks' => 'Demo post, published by the seeder.',
            'author_id' => $author,
            'approved_by' => $author,
            'published_at' => $now,
        ]);
    }
};
