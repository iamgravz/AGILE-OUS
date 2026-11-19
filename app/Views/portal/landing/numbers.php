<?php /** @var list<array{label: string, value: int}> $numbers */ ?>
<section class="landing-band landing-numbers" aria-label="AGILE in numbers">
    <dl class="landing-numbers__grid" data-numbers>
        <?php foreach ($numbers as $number) : ?>
            <div>
                <dt><?= e($number['label']) ?></dt>
                <dd data-count="<?= e($number['value']) ?>"><?= e($number['value']) ?></dd>
            </div>
        <?php endforeach; ?>
    </dl>
</section>
