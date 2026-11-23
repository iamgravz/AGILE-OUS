<?php
/**
 * One event's attendance: check members in (member number, or a QR scanner typing the ID's link), see who came.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $event
 * @var list<array<string, mixed>> $registrants
 */
$id = (int) $event['post_id'];
$attended = count(array_filter($registrants, static fn (array $r): bool => $r['attended_at'] !== null));
?>
<?php $this->start('title') ?>Attendance: <?= e($event['title']) ?><?php $this->end() ?>
<?php $this->start('page_title') ?><?= e($event['title']) ?><?php $this->end() ?>
<?php $this->start('page_actions') ?>
<a class="btn btn--secondary btn--sm" href="<?= e(route('events.attendance.export', ['id' => $id])) ?>">Export CSV</a>
<a class="btn btn--ghost btn--sm" href="<?= e(route('posts.show', ['slug' => $event['slug']])) ?>">View event page</a>
<?php $this->end() ?>

<p class="timeline__meta">
    <?= $event['event_start'] !== null ? e(datetime_ph($event['event_start'])) . ' · ' : '' ?><?= e((string) $event['venue_or_link']) ?>
</p>

<div class="account-sections">
    <form class="plain-section" method="post" action="<?= e(route('events.attendance.check_in', ['id' => $id])) ?>">
        <?= csrf_field() ?>
        <div class="card__header"><h2 class="card__title">Check in</h2></div>
        <div class="card__body attendance-checkin">
            <div class="field">
                <label class="field__label" for="member">Member number or ID QR</label>
                <input class="input" id="member" name="member" type="text" maxlength="255" autocomplete="off" autofocus required aria-describedby="member-help">
                <p class="field__help" id="member-help">Type the member number, or click here and scan the QR on their AGILE ID. Members who did not register are added as walk-ins.</p>
            </div>
            <button type="submit" class="btn btn--primary">Check in</button>
        </div>
    </form>

    <section class="plain-section" aria-labelledby="registrants-title">
        <div class="card__header"><h2 class="card__title" id="registrants-title">Registrants: <?= $attended ?> of <?= count($registrants) ?> checked in</h2></div>
        <div class="card__body">
            <?php if ($registrants === []) : ?>
                <p class="text-muted">No one has registered yet.</p>
            <?php else : ?>
                <div class="table-wrap">
                    <table class="table table--stack">
                        <caption class="sr-only">Registrants</caption>
                        <thead><tr><th scope="col">Member</th><th scope="col">Year and section</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                        <tbody>
                            <?php foreach ($registrants as $r) : ?>
                                <tr>
                                    <td data-label="Member"><?= e($r['last_name'] . ', ' . $r['first_name']) ?> <span class="text-muted"><?= e($r['member_no']) ?></span></td>
                                    <td data-label="Year and section"><?= e(trim((string) $r['year_level'] . ' ' . (string) $r['section'])) ?: '<span class="text-muted">Not set</span>' ?></td>
                                    <td data-label="Status">
                                        <?php if ($r['attended_at'] !== null) : ?>
                                            <span class="badge badge--success">Checked in<?= (int) $r['walk_in'] === 1 ? ' (walk-in)' : '' ?></span>
                                            <span class="timeline__meta"><?= e(time_ph($r['attended_at'])) ?><?= $r['checked_in_by_name'] !== null ? ' by ' . e($r['checked_in_by_name']) : '' ?></span>
                                        <?php else : ?>
                                            <span class="badge badge--neutral">Registered</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Actions">
                                        <?php if ($r['attended_at'] !== null) : ?>
                                            <form method="post" action="<?= e(route('events.attendance.undo', ['id' => $id, 'registrationId' => (int) $r['registration_id']])) ?>">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn--ghost btn--sm">Undo</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
