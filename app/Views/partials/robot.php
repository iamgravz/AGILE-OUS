<?php
/**
 * Iskobot, standing on the .robot-card that follows it (css/robot.css). The card's ::before and ::after are its hands.
 * Put both inside a .robot-stage. Decorative, so screen readers skip it.
 *
 * @var string $bubble what Iskobot says
 */
?>
<div class="robot" aria-hidden="true">
    <div class="robot__bubble"><?= e($bubble) ?></div>
    <div class="robot__antenna"><span class="robot__bulb"></span><span class="robot__stem"></span></div>
    <div class="robot__head">
        <span class="robot__eye"><span class="robot__pupil"></span></span>
        <span class="robot__eye"><span class="robot__pupil"></span></span>
    </div>
</div>
