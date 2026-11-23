<?php
/**
 * Every component in one page, rendered in the public and dashboard layouts. Example text stays in this view.
 *
 * @var \App\Core\View $this
 * @var string $layout public or dashboard
 */
$swatches = [
    'Theme colors' => ['color-page', 'color-surface', 'color-subtle', 'color-text', 'color-text-muted', 'color-link', 'color-border', 'color-border-input', 'primary', 'primary-hover', 'primary-soft', 'gold', 'gold-soft', 'gold-text'],
    'Chrome: dark ink in both themes' => ['ink', 'ink-2', 'ink-3', 'ink-text', 'ink-muted', 'ink-active', 'ink-border'],
    'Status' => ['neutral-bg', 'neutral-fg', 'info-bg', 'info-fg', 'warning-bg', 'warning-fg', 'success-bg', 'success-fg', 'danger-bg', 'danger-fg'],
];

$lifecycles = [
    'Membership application' => ['draft', 'submitted', 'under_review', 'approved', 'rejected', 'for_revision', 'withdrawn'],
    'Applicant document' => ['pending', 'verified', 'needs_correction', 'rejected'],
    'HR request' => ['submitted', 'approved', 'returned', 'cancelled'],
    'Vacancy' => ['draft', 'open', 'closed', 'filled', 'cancelled'],
    'Membership' => ['active'],
    'Officer role' => ['active', 'expired'],
    'Welfare case' => ['submitted', 'in_progress', 'referred', 'resolved', 'closed'],
    'Content post' => ['draft', 'for_approval', 'published', 'returned', 'archived'],
    'Eligibility screening' => ['pending', 'submitted', 'eligible', 'flagged', 'incomplete', 'confirmed_ineligible', 'cleared'],
    'Notification' => ['queued', 'sent', 'failed'],
    'Appointment' => ['prepared', 'approved', 'returned'],
    'Digital credential' => ['pending_approval', 'approved', 'returned', 'revoked'],
];

$icons = [
    'alert', 'archive', 'award', 'bell', 'briefcase', 'calendar', 'chart', 'check', 'check-circle', 'chevron-down',
    'chevron-left', 'chevron-right', 'clipboard', 'close', 'database', 'eye', 'eye-off', 'file', 'heart', 'home', 'inbox', 'info',
    'layers', 'list', 'lock', 'logout', 'mail', 'megaphone', 'menu', 'plus', 'search', 'settings', 'shield', 'trash',
    'upload', 'user', 'users', 'x-circle', 'moon', 'sun', 'arrow-up', 'arrow-down', 'arrow-right',
];

$rows = [
    ['Andrea Santos', 'Academic Committee', 'under_review', 'October 12, 2026'],
    ['Miguel Reyes', 'Membership and Student Welfare', 'for_revision', 'October 10, 2026'],
    ['Bianca Cruz', 'General member', 'approved', 'October 2, 2026'],
];
?>
<?php $this->start('title') ?>Style Guide: <?= e($layout) ?> layout<?php $this->end() ?>
<?php $this->start('head') ?><link rel="stylesheet" href="<?= e(asset('css/styleguide.css')) ?>"><?php $this->end() ?>
<?php $this->start('page_title') ?>Style Guide<?php $this->end() ?>
<?php $this->start('page_actions') ?><button type="button" class="btn btn--primary btn--sm" data-toast="Example page action" data-toast-tone="info"><?= icon('plus') ?> Page action</button><?php $this->end() ?>

<?php
$example = static function (string $theme, string $name): void { ?>
    <div class="sg-panel">
        <p class="eyebrow"><?= e($name) ?></p>
        <div class="stat-grid sg-panel__stats">
            <div class="stat-card">
                <div class="stat-card__head"><span class="stat-card__icon"><?= icon('clipboard') ?></span><p class="stat-card__label">New applications</p></div>
                <p class="stat-card__value">12 <span class="chip chip--up"><?= icon('arrow-up') ?>3</span></p>
                <p class="stat-card__sub">Example: 12 of 40 applications</p>
                <progress class="progress" value="12" max="40" aria-hidden="true"></progress>
            </div>
            <div class="stat-card">
                <div class="stat-card__head"><span class="stat-card__icon"><?= icon('users') ?></span><p class="stat-card__label">Daily example</p></div>
                <p class="stat-card__value">9</p>
                <p class="stat-card__sub">Sparkline, only from real daily counts</p>
                <svg class="sparkline" viewBox="0 0 70 24" preserveAspectRatio="none" aria-hidden="true"><?php foreach ([4, 8, 6, 12, 9, 16, 11] as $i => $h) : ?><rect x="<?= $i * 10 ?>" y="<?= 24 - $h ?>" width="7" height="<?= $h ?>" rx="1"/><?php endforeach; ?></svg>
            </div>
        </div>
        <div class="card sg-panel__card">
            <div class="card__header">
                <h3 class="card__title">Card title</h3>
                <div class="segmented" role="group" aria-label="Example range">
                    <button type="button" class="segmented__item" aria-pressed="false">3 months</button>
                    <button type="button" class="segmented__item" aria-pressed="true">6 months</button>
                </div>
            </div>
            <div class="card__body">
                <ul class="activity">
                    <li class="activity__item"><?= initials_avatar('Andrea Santos') ?><p class="activity__text"><strong>Andrea Santos</strong> submitted an application <span class="activity__time">2 min ago</span></p></li>
                    <li class="activity__item"><?= initials_avatar('Elena Ramos') ?><p class="activity__text"><strong>Elena Ramos</strong> approved a vacancy <span class="activity__time">1 hr ago</span></p></li>
                </ul>
            </div>
        </div>
        <p class="cluster sg-panel__row">
            <button type="button" class="btn btn--primary btn--sm"><?= icon('plus') ?> Primary</button>
            <button type="button" class="btn btn--secondary btn--sm">Outline</button>
            <button type="button" class="btn btn--danger btn--sm">Danger</button>
            <a href="#sg-both">A link</a>
        </p>
        <p class="cluster sg-panel__row">
            <?php foreach (['submitted', 'under_review', 'for_revision', 'approved', 'rejected', 'draft'] as $status) : ?><?= status_badge($status) ?><?php endforeach; ?>
        </p>
        <div class="table-wrap">
            <table class="table table--stack">
                <caption class="sr-only">Example table</caption>
                <thead><tr><th scope="col">Applicant</th><th scope="col">Status</th><th scope="col" class="num">Score</th></tr></thead>
                <tbody>
                    <tr><td data-label="Applicant">Andrea Santos</td><td data-label="Status"><?= status_badge('under_review') ?></td><td class="num" data-label="Score">87</td></tr>
                    <tr><td data-label="Applicant">Miguel Reyes</td><td data-label="Status"><?= status_badge('approved') ?></td><td class="num" data-label="Score">92</td></tr>
                </tbody>
            </table>
        </div>
        <p class="empty-state__text sg-panel__row">Muted text on this surface.</p>
        <div class="alert alert--success" role="presentation"><?= icon('check') ?><p class="alert__text">A success message.</p></div>
        <div class="alert alert--danger" role="presentation"><?= icon('alert') ?><p class="alert__text">A danger message.</p></div>
    </div>
<?php };
?>
<section class="sg-section" aria-labelledby="sg-glass">
    <h2 id="sg-glass">Glass</h2>
    <p class="sg-note">Only for the public header when scrolled, the home hero panel, the login, registration and Track Status cards, and the dashboard top bar. Backdrops of modals and menus blur too. Never on tables, form fields, reading text, welfare screens or credentials.</p>
    <div class="glass-stage sg-glass">
        <div class="sg-glass__row">
            <div class="glass sg-glass__box">
                <p class="eyebrow">.glass, light</p>
                <p>Dark text on frosted white. <a href="#sg-glass">A link</a></p>
                <input class="input" type="text" value="Fields stay solid" aria-label="Example field">
            </div>
            <div class="glass--dark sg-glass__box">
                <p class="eyebrow">.glass--dark</p>
                <p>Light text on frosted ink.</p>
                <button type="button" class="btn btn--on-dark btn--sm">A button</button>
            </div>
        </div>
    </div>
</section>

<section class="sg-section" aria-labelledby="sg-both">
    <h2 id="sg-both">Example panel</h2>
    <p class="sg-note">Example numbers stay on this page.</p>
    <div class="sg-panels">
        <?php $example('light', 'Light'); ?>
    </div>
</section>

<section class="sg-section">
    <h2>Type</h2>
    <p class="eyebrow">IBM Plex Mono 400: eyebrows, timestamps, table numbers</p>
    <h1>IBM Plex Sans 600, 28px</h1>
    <h2>22px section heading</h2>
    <h3>18px heading</h3>
    <p>IBM Plex Sans 400, 16px body text. <em>Italic 400.</em> <strong>Weight 600.</strong> Dates read <?= e(datetime_ph('2026-10-31 14:30:00')) ?>.</p>
    <p>Codes use IBM Plex Mono 500: <code>AGILE-2026-0001</code>. Press <kbd>/</kbd> to search.</p>
</section>

<p class="sg-note">Available only when <code>APP_ENV=local</code>. Example text on this page is not real data.</p>
<nav class="sg-layout-links" aria-label="Layouts">
    <a class="btn btn--secondary btn--sm" href="/styleguide"<?= $layout === 'public' ? ' aria-current="page"' : '' ?>>Public layout</a>
    <a class="btn btn--secondary btn--sm" href="/styleguide/dashboard"<?= $layout === 'dashboard' ? ' aria-current="page"' : '' ?>>Dashboard layout</a>
    <a class="btn btn--secondary btn--sm" href="/styleguide/auth">Auth layout</a>
</nav>

<section class="sg-section" aria-labelledby="sg-tokens">
    <h2 id="sg-tokens">Colors</h2>
    <?php foreach ($swatches as $group => $tokens) : ?>
        <h3><?= e($group) ?></h3>
        <ul class="sg-swatches">
            <?php foreach ($tokens as $token) : ?>
                <li class="sg-swatch">
                    <span class="sg-swatch__color sg-bg-<?= e($token) ?>"></span>
                    <span class="sg-swatch__name"><?= e($token) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endforeach; ?>
</section>

<section class="sg-section" aria-labelledby="sg-type">
    <h2 id="sg-type">Typography</h2>
    <h3>Heading level 3</h3>
    <p>Body text is 16px. <a href="#sg-type">Links are underlined</a>, so they do not rely on color alone. <code>code</code> uses the mono stack.</p>
    <p class="text-muted">Muted text is for supporting details.</p>
</section>

<section class="sg-section" aria-labelledby="sg-buttons">
    <h2 id="sg-buttons">Buttons</h2>
    <div class="stack">
        <div class="cluster">
            <button type="button" class="btn btn--primary">Primary</button>
            <button type="button" class="btn btn--secondary">Secondary</button>
            <button type="button" class="btn btn--ghost">Ghost</button>
            <button type="button" class="btn btn--danger">Danger</button>
        </div>
        <div class="cluster">
            <button type="button" class="btn btn--primary btn--sm">Primary sm</button>
            <button type="button" class="btn btn--secondary btn--sm">Secondary sm</button>
            <button type="button" class="btn btn--ghost btn--sm">Ghost sm</button>
            <button type="button" class="btn btn--danger btn--sm">Danger sm</button>
        </div>
        <div class="cluster">
            <button type="button" class="btn btn--primary" disabled>Disabled</button>
            <button type="button" class="btn btn--primary is-loading" aria-busy="true">Loading</button>
            <button type="button" class="btn btn--secondary is-loading" aria-busy="true">Loading</button>
            <a class="btn btn--secondary" href="#sg-buttons"><?= icon('upload') ?> Link with icon</a>
        </div>
    </div>
</section>

<section class="sg-section" aria-labelledby="sg-forms">
    <h2 id="sg-forms">Form fields</h2>
    <form class="card" method="get" action="<?= e(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)) ?>#sg-forms" data-no-guard>
        <div class="card__body">
            <p class="text-muted"><span class="field__required" aria-hidden="true">*</span> marks a required field.</p>
            <div class="grid grid--2">
                <div class="field">
                    <label class="field__label" for="sg-name">Full name <span class="field__required" aria-hidden="true">*</span></label>
                    <input class="input" id="sg-name" type="text" autocomplete="off" aria-describedby="sg-name-help" required>
                    <p class="field__help" id="sg-name-help">Enter your name as it appears on your student record.</p>
                </div>
                <div class="field field--error">
                    <label class="field__label" for="sg-email">Email <span class="field__required" aria-hidden="true">*</span></label>
                    <input class="input" id="sg-email" type="email" value="not-an-email" aria-invalid="true" aria-describedby="sg-email-error" autocomplete="off">
                    <p class="field__error" id="sg-email-error"><?= icon('alert') ?> Enter a valid email address.</p>
                </div>
                <div class="field">
                    <label class="field__label" for="sg-committee">Committee</label>
                    <select class="select" id="sg-committee">
                        <option>Choose a committee</option>
                        <option>Academic</option>
                        <option>Membership and Student Welfare</option>
                    </select>
                </div>
                <div class="field field--error">
                    <label class="field__label" for="sg-position">Position <span class="field__required" aria-hidden="true">*</span></label>
                    <select class="select" id="sg-position" aria-invalid="true" aria-describedby="sg-position-error">
                        <option>Choose a position</option>
                    </select>
                    <p class="field__error" id="sg-position-error"><?= icon('alert') ?> Choose a position.</p>
                </div>
                <div class="field">
                    <label class="field__label" for="sg-disabled">Disabled input</label>
                    <input class="input" id="sg-disabled" type="text" value="Cannot be changed" disabled>
                </div>
                <div class="field">
                    <fieldset class="field-group">
                        <legend class="field-group__legend">Preferred contact</legend>
                        <label class="check"><input type="radio" name="sg_contact" checked> Email</label>
                        <label class="check"><input type="radio" name="sg_contact"> Phone</label>
                    </fieldset>
                </div>
            </div>
            <div class="field">
                <label class="field__label" for="sg-message">Message</label>
                <textarea class="textarea" id="sg-message" aria-describedby="sg-message-help"></textarea>
                <p class="field__help" id="sg-message-help">Share only the details needed to help you.</p>
            </div>
            <div class="field">
                <label class="check"><input type="checkbox"> I have read the Privacy Notice and agree to the processing of my data under RA 10173.</label>
            </div>
            <div class="field">
                <label class="field__label" for="sg-file">Supporting document</label>
                <div class="dropzone">
                    <input class="dropzone__input" id="sg-file" type="file" accept=".pdf,.jpg,.jpeg,.png" multiple data-file-preview="sg-file-preview" aria-describedby="sg-file-help">
                    <div class="dropzone__body">
                        <?= icon('upload') ?>
                        <span><strong>Choose a file</strong> or drop it here</span>
                    </div>
                </div>
                <p class="field__help" id="sg-file-help">PDF, JPG or PNG. Images show a thumbnail.</p>
                <ul class="file-preview" id="sg-file-preview"></ul>
            </div>
            <div class="field field--error">
                <label class="field__label" for="sg-file-error">Document with an error</label>
                <div class="dropzone">
                    <input class="dropzone__input" id="sg-file-error" type="file" data-file-preview="sg-file-error-preview" aria-invalid="true" aria-describedby="sg-file-error-text">
                    <div class="dropzone__body">
                        <?= icon('upload') ?>
                        <span><strong>Choose a file</strong> or drop it here</span>
                    </div>
                </div>
                <p class="field__error" id="sg-file-error-text"><?= icon('alert') ?> This file type is not allowed.</p>
                <ul class="file-preview" id="sg-file-error-preview"></ul>
            </div>
        </div>
        <div class="card__footer cluster">
            <button type="button" class="btn btn--primary" data-toast="Example only. Nothing was saved." data-toast-tone="success">Save</button>
            <button type="reset" class="btn btn--ghost">Reset</button>
        </div>
    </form>
</section>

<section class="sg-section" aria-labelledby="sg-cards">
    <h2 id="sg-cards">Card</h2>
    <div class="grid grid--2">
        <article class="card">
            <div class="card__header"><h3 class="card__title">Card with header and footer</h3></div>
            <div class="card__body"><p>Cards group related content.</p></div>
            <div class="card__footer"><a class="btn btn--secondary btn--sm" href="#sg-cards">View details</a></div>
        </article>
        <article class="card">
            <div class="card__body">
                <h3>Vacancy</h3>
                <p>Cards hold real objects only: a post, a vacancy, a credential or a case summary.</p>
            </div>
        </article>
    </div>
</section>

<section class="sg-section" aria-labelledby="sg-table">
    <h2 id="sg-table">Table</h2>
    <p class="sg-note">Stacks into cards below 768px.</p>
    <form class="table-toolbar" method="get" action="#sg-table" data-no-guard>
        <div class="field">
            <label class="field__label" for="sg-search">Search</label>
            <input class="input" id="sg-search" type="search" autocomplete="off">
        </div>
        <div class="field">
            <label class="field__label" for="sg-filter">Status</label>
            <select class="select" id="sg-filter">
                <option>All statuses</option>
                <option>Under Review</option>
            </select>
        </div>
    </form>
    <div class="table-wrap">
        <table class="table table--stack">
            <caption class="sr-only">Example applications</caption>
            <thead>
                <tr>
                    <th scope="col">Applicant</th>
                    <th scope="col">Applying for</th>
                    <th scope="col">Status</th>
                    <th scope="col">Submitted</th>
                    <th scope="col">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as [$name, $target, $status, $date]) : ?>
                    <tr>
                        <td data-label="Applicant"><?= e($name) ?></td>
                        <td data-label="Applying for"><?= e($target) ?></td>
                        <td data-label="Status"><?= status_badge($status) ?></td>
                        <td data-label="Submitted"><?= e($date) ?></td>
                        <td data-label="Action"><a class="btn btn--ghost btn--sm" href="#sg-table">View<span class="sr-only"> <?= e($name) ?></span></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="sg-section" aria-labelledby="sg-badges">
    <h2 id="sg-badges">Status badges</h2>
    <p class="sg-note">Every status in section 8, grouped by lifecycle. Color always comes with text.</p>
    <dl class="sg-badge-groups">
        <?php foreach ($lifecycles as $lifecycle => $statuses) : ?>
            <div>
                <dt><?= e($lifecycle) ?></dt>
                <dd>
                    <?php foreach ($statuses as $status) : ?>
                        <?= status_badge($status) ?>
                    <?php endforeach; ?>
                </dd>
            </div>
        <?php endforeach; ?>
    </dl>
</section>

<section class="sg-section" aria-labelledby="sg-alerts">
    <h2 id="sg-alerts">Alerts and flash messages</h2>
    <?= $this->partial('partials/alert', ['tone' => 'success', 'message' => 'Your application was submitted.']) ?>
    <?= $this->partial('partials/alert', ['tone' => 'info', 'message' => 'Screening for this term opens next week.']) ?>
    <?= $this->partial('partials/alert', ['tone' => 'warning', 'message' => 'One of your documents needs correction.']) ?>
    <?= $this->partial('partials/alert', ['tone' => 'danger', 'message' => 'We could not save your changes.']) ?>
</section>

<section class="sg-section" aria-labelledby="sg-modal">
    <h2 id="sg-modal">Confirm modal</h2>
    <p class="sg-note">Add <code>data-confirm</code> to a form or submit button. Cancel keeps you here. Confirm submits the form, and the double-submit guard then shows the loading state until the page reloads.</p>
    <form class="cluster" method="get" action="<?= e(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)) ?>" data-confirm="This example rejects the application and cannot be undone." data-confirm-title="Reject this application?" data-confirm-action="Reject">
        <input type="hidden" name="example" value="confirmed">
        <button type="submit" class="btn btn--danger"><?= icon('trash') ?> Reject application</button>
    </form>
</section>

<section class="sg-section" aria-labelledby="sg-tabs">
    <h2 id="sg-tabs">Tabs</h2>
    <p class="sg-note">Tab moves to the selected tab. Arrow keys switch tabs.</p>
    <div data-tabs>
        <div class="tabs__list" role="tablist" aria-label="Application sections">
            <button type="button" class="tabs__tab" role="tab" id="sg-tab-1" aria-selected="true" aria-controls="sg-panel-1">Details</button>
            <button type="button" class="tabs__tab" role="tab" id="sg-tab-2" aria-selected="false" aria-controls="sg-panel-2" tabindex="-1">Documents</button>
            <button type="button" class="tabs__tab" role="tab" id="sg-tab-3" aria-selected="false" aria-controls="sg-panel-3" tabindex="-1">Status history</button>
        </div>
        <div class="tabs__panel" role="tabpanel" id="sg-panel-1" aria-labelledby="sg-tab-1"><p>Details panel.</p></div>
        <div class="tabs__panel" role="tabpanel" id="sg-panel-2" aria-labelledby="sg-tab-2" hidden><p>Documents panel.</p></div>
        <div class="tabs__panel" role="tabpanel" id="sg-panel-3" aria-labelledby="sg-tab-3" hidden><p>Status history panel.</p></div>
    </div>
</section>

<section class="sg-section" aria-labelledby="sg-stepper">
    <h2 id="sg-stepper">Stepper</h2>
    <ol class="stepper">
        <li class="stepper__step stepper__step--done">
            <span class="stepper__marker"><?= icon('check') ?></span>
            <span><span class="sr-only">Completed: </span>Vacancy</span>
        </li>
        <li class="stepper__step stepper__step--done">
            <span class="stepper__marker"><?= icon('check') ?></span>
            <span><span class="sr-only">Completed: </span>Details</span>
        </li>
        <li class="stepper__step stepper__step--current" aria-current="step">
            <span class="stepper__marker">3</span>
            <span>Documents</span>
        </li>
        <li class="stepper__step">
            <span class="stepper__marker">4</span>
            <span>Review</span>
        </li>
    </ol>
</section>

<section class="sg-section" aria-labelledby="sg-timeline">
    <h2 id="sg-timeline">Timeline</h2>
    <ol class="timeline">
        <li class="timeline__item timeline__item--current">
            <?= status_badge('under_review') ?>
            <p class="timeline__meta">Oct 14, 2026, 9:30 AM by the MSW Committee</p>
            <p class="timeline__note">Documents are being checked.</p>
        </li>
        <li class="timeline__item">
            <?= status_badge('submitted') ?>
            <p class="timeline__meta">October 12, 2026, 3:12 PM</p>
        </li>
        <li class="timeline__item">
            <?= status_badge('draft') ?>
            <p class="timeline__meta">Oct 11, 2026, 8:45 PM</p>
        </li>
    </ol>
</section>

<section class="sg-section" aria-labelledby="sg-pagination">
    <h2 id="sg-pagination">Pagination</h2>
    <nav aria-label="Example pagination">
        <ul class="pagination__list">
            <li><a class="pagination__link" href="#sg-pagination" aria-label="Previous page"><?= icon('chevron-left') ?></a></li>
            <li><a class="pagination__link" href="#sg-pagination">1</a></li>
            <li><a class="pagination__link" href="#sg-pagination" aria-current="page">2</a></li>
            <li><a class="pagination__link" href="#sg-pagination">3</a></li>
            <li><span class="pagination__gap">&hellip;</span></li>
            <li><a class="pagination__link" href="#sg-pagination">9</a></li>
            <li><a class="pagination__link" href="#sg-pagination" aria-label="Next page"><?= icon('chevron-right') ?></a></li>
        </ul>
    </nav>
</section>

<section class="sg-section" aria-labelledby="sg-empty">
    <h2 id="sg-empty">Empty state</h2>
    <div class="empty-state">
        <p class="empty-state__text">Applications appear here after applicants submit them.</p>
        <a class="btn btn--secondary" href="#sg-empty">Example action</a>
    </div>
</section>

<section class="sg-section" aria-labelledby="sg-toast">
    <h2 id="sg-toast">Toast</h2>
    <div class="cluster">
        <button type="button" class="btn btn--secondary" data-toast="Saved successfully." data-toast-tone="success">Success toast</button>
        <button type="button" class="btn btn--secondary" data-toast="A new update is available." data-toast-tone="info">Info toast</button>
        <button type="button" class="btn btn--secondary" data-toast="Check this before continuing." data-toast-tone="warning">Warning toast</button>
        <button type="button" class="btn btn--secondary" data-toast="That did not work." data-toast-tone="danger">Danger toast</button>
    </div>
</section>

<section class="sg-section" aria-labelledby="sg-notifications">
    <h2 id="sg-notifications">Notification dropdown</h2>
    <p class="sg-note">The dashboard top bar uses the same component.</p>
    <div class="cluster">
        <?= $this->partial('partials/notification_dropdown', [
            'id' => 'sg-notifications-filled',
            'class' => 'dropdown--static',
            'notifications' => [
                ['title' => 'Your application needs revision', 'time' => '10 minutes ago', 'link' => '#sg-notifications', 'unread' => true],
                ['title' => 'Your document was verified', 'time' => 'October 12, 2026', 'link' => '#sg-notifications', 'unread' => false],
            ],
        ]) ?>
        <?= $this->partial('partials/notification_dropdown', ['id' => 'sg-notifications-empty', 'class' => 'dropdown--static']) ?>
    </div>
</section>

<section class="sg-section" aria-labelledby="sg-icons">
    <h2 id="sg-icons">Icons</h2>
    <ul class="sg-icons">
        <?php foreach ($icons as $name) : ?>
            <li><?= icon($name) ?> <?= e($name) ?></li>
        <?php endforeach; ?>
    </ul>
</section>
