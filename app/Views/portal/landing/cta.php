<?php /** @var bool $showJoinLink */ ?>
<div class="landing-band landing-cta">
    <h2 id="home-cta">Be part of AGILE</h2>
    <p>General membership is always open.</p>
    <div class="cluster">
        <a class="btn btn--primary" href="<?= e(route('apply')) ?>">Apply for membership</a>
        <?php if ($showJoinLink) : ?><a class="landing-cta__link" href="#how-to-join">See how to join</a><?php endif; ?>
    </div>
</div>
