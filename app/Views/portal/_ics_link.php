<?php
/**
 * "Add to calendar": the .ics file travels inside the link, so it needs no route and no JavaScript.
 *
 * @var array<string, mixed> $post an event with event_start
 */
$utc = static fn (string $value): string => gmdate('Ymd\THis\Z', (int) strtotime($value));
$text = static fn (string $value): string => str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\\;', '\\,', '\\n', '\\n'], $value);
$start = (int) strtotime($post['event_start']);
$host = (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'agile');
$lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//AGILE//Events//EN',
    'BEGIN:VEVENT',
    'UID:' . $post['slug'] . '@' . $host,
    'DTSTAMP:' . gmdate('Ymd\THis\Z'),
    'DTSTART:' . $utc($post['event_start']),
    'DTEND:' . ($post['event_end'] !== null ? $utc($post['event_end']) : gmdate('Ymd\THis\Z', $start + 3600)),
    'SUMMARY:' . $text($post['title']),
];

if ((string) $post['venue_or_link'] !== '') {
    $lines[] = 'LOCATION:' . $text((string) $post['venue_or_link']);
}

$lines[] = 'URL:' . url(route('posts.show', ['slug' => $post['slug']]));
$lines[] = 'END:VEVENT';
$lines[] = 'END:VCALENDAR';

// The format allows 75 bytes a line. A longer line continues on the next, after one space.
$folded = array_map(static fn (string $line): string => rtrim(chunk_split($line, 73, "\r\n "), "\r\n "), $lines);
$file = implode("\r\n", $folded) . "\r\n";
?>
<a class="btn btn--secondary btn--sm" href="data:text/calendar;charset=utf-8,<?= e(rawurlencode($file)) ?>" download="<?= e($post['slug']) ?>.ics">Add to calendar</a>
