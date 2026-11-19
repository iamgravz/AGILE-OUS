<?php /** Static on purpose: this also shows when the database is down, so it reads nothing from it. */ ?>
<section class="card side-note">
    <div class="card__body">
        <h2 class="side-note__title">Where to go next</h2>
        <ul class="side-note__links">
            <li><a href="/">Home</a></li>
            <li><a href="/news">News</a></li>
            <li><a href="/positions">Open Positions</a></li>
            <li><a href="/track">Track Status</a></li>
            <li><a href="/ask-for-help">Ask for Help</a></li>
        </ul>
    </div>
</section>
<section class="card side-note">
    <div class="card__body">
        <h2 class="side-note__title">Still stuck?</h2>
        <p>Email the MSW Committee and tell us what you were trying to do.</p>
        <p><a href="mailto:<?= e(config('app.contact_email')) ?>"><?= e(config('app.contact_email')) ?></a></p>
    </div>
</section>
