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

        // Nov 23, 2026: five of each for the redesigned public pages. Still samples, still marked [Demo].
        ['news', '[Demo] AGILE team places 2nd at the regional hackathon', 'demo-news-hackathon', 'Three members built a barangay health-records app in 36 hours and placed second out of 24 teams.', '<p>A team of three AGILE members placed <strong>second out of 24 teams</strong> at a regional student hackathon. Their app keeps barangay health records offline and syncs when a connection returns.</p><h2>What the judges liked</h2><ul><li>It works without internet</li><li>Plain screens that health workers learned in minutes</li></ul><p>Sample story for testing; the event and team are not real.</p>', null, null, null],
        ['news', '[Demo] New Academics and Training review sessions every Saturday', 'demo-news-review-sessions', 'Free review sessions for Data Structures and Database Systems start this month, online.', '<p>The Academics and Training Committee starts <strong>weekly review sessions</strong> for Data Structures and Database Systems. Sessions are online every Saturday morning.</p><p>Sample story for testing.</p>', null, null, null],
        ['news', '[Demo] MSW opens a quiet hour for students who need to talk', 'demo-news-quiet-hour', 'Members can book a private 30-minute talk with the MSW Committee through Ask for Help.', '<p>The Membership and Student Welfare Committee now keeps a <strong>quiet hour</strong> each week. Book it through Ask for Help; only the MSW Committee Head and the President read your concern.</p><p>Sample story for testing.</p>', null, null, null],
        ['event', '[Demo] General Assembly, AY 2026-2027', 'demo-event-general-assembly', 'Meet the Executive Board, hear the year plan, and vote on the activity calendar.', '<p>The <strong>General Assembly</strong> opens the academic year: officer introductions, the year plan, and a vote on the activity calendar.</p><p>Sample event for testing.</p>', $inDays(13, '09:00:00'), $inDays(13, '12:00:00'), 'NALLRC Function Hall, PUP Sta. Mesa'],
        ['event', '[Demo] Web development workshop: your first Laravel app', 'demo-event-web-workshop', 'A hands-on afternoon: routes, a database and a deployed app by 5 PM.', '<p>Bring a laptop. By the end you will have a small app with a database, deployed online.</p><p>Sample event for testing.</p>', $inDays(20, '13:00:00'), $inDays(20, '17:00:00'), 'https://example.com/agile-workshop'],
        ['event', '[Demo] Christmas outreach at a partner community', 'demo-event-outreach', 'Gift packing and a short computer-literacy session for kids, with the Research and Extension Committee.', '<p>Join the Research and Extension Committee for gift packing and a short computer-literacy session.</p><p>Sample event for testing; it already happened.</p>', $inDays(-25, '08:00:00'), $inDays(-25, '15:00:00'), 'Partner community center, Sta. Mesa'],
        ['publication', '[Demo] The Source Code, Issue 04: Coding in the open', 'demo-publication-issue-04', 'This issue: open-source projects by OU students, a feature on working while studying, and the comics page.', '<p>Issue 04 looks at <strong>open-source work by OU students</strong>, plus a feature on balancing work and an online degree.</p><p>Sample issue for testing.</p>', null, null, null],
        ['publication', '[Demo] Opinion: Online classes need offline backups', 'demo-publication-opinion-offline', 'Why every OU subject should have a downloadable module for students with weak connections.', '<p>An opinion piece arguing for <strong>downloadable modules</strong> in every OU subject.</p><p>Sample article for testing.</p>', null, null, null],
        ['publication', '[Demo] Feature: A day with the Logistics Committee', 'demo-publication-logistics-feature', 'From chairs to cables: what it takes to set up one General Assembly.', '<p>We followed the Logistics Committee for a day before the General Assembly.</p><p>Sample feature for testing.</p>', null, null, null],
        ['publication', '[Demo] Comics: The Semester in 6 Panels', 'demo-publication-comics', 'Enrollment, the first quiz, group projects and finals week, drawn by the TSC cartoonists.', '<p>Six panels about a semester at OU, by the cartoonists of The Source Code.</p><p>Sample comic strip for testing.</p>', null, null, null],
        ['announcement', '[Demo] Membership renewal closes December 15', 'demo-announcement-renewal', 'Renew your membership with your current COR before December 15.', '<p>Renew your membership by uploading your current COR before <strong>December 15</strong>.</p><p>Sample notice for testing.</p>', null, null, null],
        ['announcement', '[Demo] Interview schedules for committee applicants are out', 'demo-announcement-interviews', 'Check your email for your interview date and time from the MSW team.', '<p>The MSW team has emailed interview schedules to committee applicants.</p><p>Sample notice for testing.</p>', null, null, null],
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
