<?php
/**
 * @var \App\Core\View $this
 * @var list<array{title: string, text: string}> $joinSteps
 */
?>
<section class="landing-section" id="how-to-join" aria-labelledby="home-join">
    <?= $this->partial('portal/landing/_head', ['id' => 'home-join', 'eyebrow' => 'Membership', 'title' => 'How to join']) ?>
    <ol class="join-steps" data-join>
        <?php foreach ($joinSteps as $index => $step) : ?>
            <li class="join-steps__step">
                <span class="join-steps__marker" aria-hidden="true"><?= e($index + 1) ?></span>
                <h3><?= e($step['title']) ?></h3>
                <p><?= e($step['text']) ?></p>
            </li>
        <?php endforeach; ?>
    </ol>
</section>
