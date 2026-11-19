<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $user
 * @var array<string, mixed> $dash
 */
?>
<?php $this->start('title') ?>My committee's recruitment<?php $this->end() ?>
<?php $this->start('page_eyebrow') ?>Overview<?php $this->end() ?>
<?php $this->start('page_actions') ?><?= $this->partial('dashboard/_actions') ?><?php $this->end() ?>
<?php $this->start('page_title') ?>My committee's recruitment<?php $this->end() ?>
<?= $this->partial('dashboard/_cards', ['user' => $user, 'dash' => $dash]) ?>
<section class="card dashboard-section" aria-labelledby="dashboard-vacancies">
    <div class="card__header"><h2 class="card__title" id="dashboard-vacancies">My committee's open vacancies</h2></div>
    <div class="card__body">
        <?php if ($dash['vacancies'] === []) : ?>
            <p class="text-muted">Your committee has no open vacancies.</p>
        <?php else : ?>
            <ul class="case-list case-list--rows">
                <?php foreach ($dash['vacancies'] as $vacancy) : ?>
                    <li>
                        <a href="<?= e(route('committee_recruitment')) ?>"><?= e($vacancy['title']) ?></a>
                        <span class="timeline__meta"><?= e($vacancy['slots_filled']) ?> of <?= e($vacancy['slots']) ?> slots filled. Closes <?= e(date_ph($vacancy['closes_at'])) ?>.</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
<?= $this->partial('dashboard/_feeds', ['dash' => $dash]) ?>
