<?php
/**
 * Event attendance: the published events with how many registered and how many came.
 *
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $events
 */
?>
<?php $this->start('title') ?>Event attendance<?php $this->end() ?>
<?php $this->start('page_title') ?>Event attendance<?php $this->end() ?>

<?php if ($events === []) : ?>
    <div class="empty-state">
        <p class="empty-state__text">Published events appear here. Members register from the event page.</p>
        <?php if (can('content.draft')) : ?><a class="btn btn--secondary btn--sm" href="<?= e(route('cms')) ?>">Go to Content</a><?php endif; ?>
    </div>
<?php else : ?>
    <div class="table-wrap">
        <table class="table table--stack">
            <caption class="sr-only">Events</caption>
            <thead><tr><th scope="col">Event</th><th scope="col">Date</th><th scope="col">Registered</th><th scope="col">Checked in</th></tr></thead>
            <tbody>
                <?php foreach ($events as $event) : ?>
                    <tr>
                        <td data-label="Event"><a href="<?= e(route('events.attendance.show', ['id' => (int) $event['post_id']])) ?>"><?= e($event['title']) ?></a></td>
                        <td data-label="Date"><?= $event['event_start'] !== null ? e(datetime_ph($event['event_start'])) : '<span class="text-muted">Not set</span>' ?></td>
                        <td data-label="Registered"><?= (int) $event['registered'] ?><?= $event['capacity'] !== null ? ' / ' . (int) $event['capacity'] : '' ?><?= (int) $event['registration_open'] === 1 ? '' : ' <span class="text-muted">(closed)</span>' ?></td>
                        <td data-label="Checked in"><?= (int) $event['attended'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
