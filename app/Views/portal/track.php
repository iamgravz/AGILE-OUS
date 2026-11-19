<?php
/**
 * @var \App\Core\View $this
 * @var array{type: string, status: string, submitted_at: string, timeline: list<array{time: string, status: ?string, note: ?string}>} $result (optional)
 * @var string $error (optional)
 * @var string $notice (optional)
 * @var array<string, string> $fieldErrors (optional)
 * @var string $code (optional)
 * @var string $email (optional)
 */
$result ??= null;
$error ??= null;
$fieldErrors ??= [];
$formatTime = static fn (string $time): string => datetime_ph($time);
$fields = [
    'code' => ['label' => 'Tracking or case code', 'type' => 'text', 'value' => $code ?? '', 'autocomplete' => 'off', 'help' => 'The 12-character code from your confirmation email.'],
    'email' => ['label' => 'Email', 'type' => 'email', 'value' => $email ?? '', 'autocomplete' => 'email', 'help' => 'The email you used when you submitted.'],
];
?>
<?php $this->start('title') ?>Track your application or concern<?php $this->end() ?>
<?php $this->start('description') ?>Check the status of your AGILE application or welfare concern using your code and email.<?php $this->end() ?>
<?php $glassForm = $result === null && current_user_id() === null; ?>
<?php if ($glassForm) : ?>
    <?php $this->start('no_flash') ?>1<?php $this->end() ?>
    <?php $this->start('hero') ?>
    <section class="glass-stage">
        <div class="container">
            <?= $this->partial('partials/flash') ?>
<?php endif; ?>
<div class="form-layout">
<div class="track-page form-layout__main">
    <h1>Track your application or concern</h1>

    <?php if ($result !== null) : ?>
        <span data-no-glass hidden></span>
        <div class="card track-result">
            <div class="card__body">
                <dl class="detail-list">
                    <div><dt>Type</dt><dd><?= e($result['type']) ?></dd></div>
                    <div><dt>Current status</dt><dd><?= status_badge($result['status']) ?></dd></div>
                    <div><dt>Submitted</dt><dd><?= e($formatTime($result['submitted_at'])) ?></dd></div>
                </dl>
            </div>
        </div>
        <?php if ($result['timeline'] !== []) : ?>
            <h2>Updates</h2>
            <ol class="timeline">
                <?php foreach ($result['timeline'] as $entry) : ?>
                    <li class="timeline__item">
                        <?php if ($entry['status'] !== null) : ?>
                            <?= status_badge($entry['status']) ?>
                        <?php endif; ?>
                        <?php if ($entry['note'] !== null) : ?>
                            <p class="timeline__note"><?= nl2br(e($entry['note'])) ?></p>
                        <?php endif; ?>
                        <p class="timeline__meta"><?= e($formatTime($entry['time'])) ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
        <?php if (isset($notice)) : ?>
            <?= $this->partial('partials/alert', ['tone' => 'info', 'message' => $notice]) ?>
        <?php endif; ?>
        <?php if (($result['accepted_by'] ?? null) !== null) : ?>
            <p>Accepted by the <?= e($result['accepted_by']) ?> Committee.</p>
        <?php endif; ?>
        <?php if ($result['may_request_link'] ?? false) : ?>
            <p>This application was returned for revision. For your security, we email you a link to edit it.</p>
            <form method="post" action="<?= e(route('track.edit_link')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="code" value="<?= e($result['code']) ?>">
                <input type="hidden" name="email" value="<?= e($email ?? '') ?>">
                <button type="submit" class="btn btn--primary">Email me an edit link</button>
            </form>
        <?php elseif ($result['may_register'] ?? false) : ?>
            <p>Approved. Create your account at <a href="<?= e(route('register')) ?>">/register</a> with your student number.</p>
        <?php elseif ($result['may_request_withdrawal'] ?? false) : ?>
            <p>You can withdraw this application while it is still Submitted. For your security, we email you a link to do it.</p>
            <form method="post" action="<?= e(route('track.edit_link')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="code" value="<?= e($result['code']) ?>">
                <input type="hidden" name="email" value="<?= e($email ?? '') ?>">
                <button type="submit" class="btn btn--secondary">Email me a withdrawal link</button>
            </form>
        <?php elseif ($result['type'] === 'Membership application' && $result['status'] === 'waitlisted') : ?>
            <p>You are on the waiting list: you qualified, but there is no open slot right now. We will email you when a decision is made. You do not need to apply again.</p>
        <?php elseif ($result['type'] === 'Membership application' && $result['status'] === 'for_revision') : ?>
            <p>This application was returned for revision. <a href="<?= e(route('login')) ?>">Log in</a> and open My Applications to change it.</p>
        <?php endif; ?>
        <?php if ($result['type'] === 'Welfare concern' && $result['status'] === 'closed') : ?>
            <div class="alert alert--info" role="status">
                <?= icon('info') ?>
                <p class="alert__text">This case is closed. Submit a new concern if you still need help.</p>
            </div>
            <p><a class="btn btn--primary" href="<?= e(route('welfare.guest')) ?>?related=<?= e($result['code']) ?>">Submit a new concern</a></p>
        <?php endif; ?>
        <p class="track-again"><a href="<?= e(route('track')) ?>">Track another code</a></p>
    <?php elseif (current_user_id() !== null) : ?>
        <div class="card">
            <div class="card__body">
                <p>You are logged in, so you can follow your applications and concerns from your account.</p>
                <div class="cluster">
                    <a class="btn btn--primary" href="<?= e(route('my_applications')) ?>">My Applications</a>
                    <a class="btn btn--secondary" href="<?= e(route('my_welfare_cases')) ?>">My Welfare Cases</a>
                </div>
            </div>
        </div>
    <?php else : ?>
        <p class="portal-lead">Enter your code and email to see the status of your membership application or welfare concern.</p>
        <?php if ($error !== null) : ?>
            <?= $this->partial('partials/alert', ['tone' => 'danger', 'message' => $error]) ?>
        <?php endif; ?>
        <form class="card glass" method="post" action="<?= e(route('track')) ?>">
            <?= csrf_field() ?>
            <div class="card__body">
                <?php foreach ($fields as $name => $field) : ?>
                    <?php $fieldError = $fieldErrors[$name] ?? null; ?>
                    <div class="field <?= $fieldError !== null ? 'field--error' : '' ?>">
                        <label class="field__label" for="<?= e($name) ?>"><?= e($field['label']) ?> <span class="field__required" aria-hidden="true">*</span></label>
                        <input class="input" id="<?= e($name) ?>" name="<?= e($name) ?>" type="<?= e($field['type']) ?>" value="<?= e($field['value']) ?>" autocomplete="<?= e($field['autocomplete']) ?>" required aria-describedby="<?= e($name) ?>-help<?= $fieldError !== null ? ' ' . e($name) . '-error' : '' ?>"<?= $fieldError !== null ? ' aria-invalid="true"' : '' ?>>
                        <p class="field__help" id="<?= e($name) ?>-help"><?= e($field['help']) ?></p>
                        <?php if ($fieldError !== null) : ?>
                            <p class="field__error" id="<?= e($name) ?>-error"><?= icon('alert') ?> <?= e($fieldError) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <button type="submit" class="btn btn--primary">Check status</button>
                <p class="field__help track-privacy">We use your email only to confirm that this record is yours. See our <a href="<?= e(route('privacy')) ?>">Privacy Notice</a>.</p>
            </div>
        </form>
    <?php endif; ?>
</div>
<aside class="form-layout__side" aria-label="What each status means"><?= $this->partial('partials/side_panel', ['blocks' => [
    ['title' => 'Application statuses', 'items' => ['Submitted: we received it.', 'Under review: the committee is checking it.', 'For revision: please correct what is asked. We email you a link.', 'Approved: you can register your account.', 'Rejected: the email says why.', 'Withdrawn: you cancelled it.']],
    ['title' => 'Welfare statuses', 'items' => ['Submitted: we received your concern.', 'In progress: a handler is working on it.', 'Referred: it was passed to the right office.', 'Resolved: action was taken.', 'Closed: the case is finished.']],
    ['title' => 'Lost your code?', 'text' => 'It is in the email we sent when you submitted. Ask the MSW Committee if you cannot find it.', 'contact' => true],
]]) ?></aside>
</div>
<?php if ($glassForm) : ?>
        </div>
    </section>
    <?php $this->end() ?>
<?php endif; ?>
