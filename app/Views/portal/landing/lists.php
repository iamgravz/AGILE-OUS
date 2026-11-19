<?php
/**
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $vacancies at most 3
 * @var list<array<string, mixed>> $events at most 3
 */
?>
<section class="landing-section" aria-labelledby="home-lists" data-reveal>
    <?= $this->partial('portal/landing/_head', ['id' => 'home-lists', 'eyebrow' => 'What is open', 'title' => 'Positions and events']) ?>
    <div class="landing-columns">
        <div>
            <div class="landing-columns__head">
                <h3>Open positions</h3>
                <a href="<?= e(route('positions')) ?>">View all positions</a>
            </div>
            <?php if ($vacancies === []) : ?>
                <p>No open positions this semester. Watch the Announcements page for the next recruitment. You can still apply for general membership.</p>
            <?php else : ?>
                <ul class="landing-rows">
                    <?php foreach ($vacancies as $vacancy) : ?>
                        <?php $left = max(0, (int) $vacancy['slots'] - (int) $vacancy['slots_filled']); ?>
                        <li>
                            <a href="<?= e(route('positions.show', ['id' => $vacancy['vacancy_id']])) ?>"><?= e($vacancy['title']) ?></a>
                            <span class="landing-rows__meta"><?= e(implode(' · ', array_filter([(string) ($vacancy['committee_name'] ?? ''), $left . ($left === 1 ? ' slot' : ' slots'), 'closes ' . date_ph($vacancy['closes_at'])], static fn (string $part): bool => $part !== ''))) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <div>
            <div class="landing-columns__head">
                <h3>Upcoming events</h3>
                <a href="<?= e(route('events')) ?>">View all events</a>
            </div>
            <?php if ($events === []) : ?>
                <p>No upcoming events yet. Dates will appear here once a committee posts them.</p>
            <?php else : ?>
                <ul class="landing-rows">
                    <?php foreach ($events as $event) : ?>
                        <?php $venue = (string) $event['venue_or_link']; ?>
                        <li>
                            <a href="<?= e(route('posts.show', ['slug' => $event['slug']])) ?>"><?= e($event['title']) ?></a>
                            <span class="landing-rows__meta"><?= e(date_ph($event['event_start']) . ', ' . time_ph($event['event_start'])) ?><?= $venue === '' ? '' : ' · ' . e(preg_match('#^https?://#i', $venue) === 1 ? 'Online' : $venue) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</section>
