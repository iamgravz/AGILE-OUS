<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed> $post
 * @var bool $reviewing the route already required content.publish
 */
$id = (int) $post['post_id'];
$remarksError = field_error('remarks');
?>
<?php $this->start('title') ?><?= e($reviewing ? 'Review post' : 'View post') ?><?php $this->end() ?>
<?php $this->start('page_title') ?><?= e($reviewing ? 'Review post' : 'View post') ?><?php $this->end() ?>
<?php $this->start('page_actions') ?>
<?= status_badge($post['status']) ?>
<?php if (\App\Services\CmsService::isEditable($post['status'])) : ?>
    <a class="btn btn--secondary btn--sm" href="<?= e(route('cms.edit', ['id' => $id])) ?>">Edit</a>
<?php endif; ?>
<?php if ($post['status'] === 'published') : ?>
    <a class="btn btn--secondary btn--sm" href="<?= e(route('posts.show', ['slug' => $post['slug']])) ?>">Open public page</a>
<?php endif; ?>
<?php $this->end() ?>

<?php $hasSide = $post['review_remarks'] !== null || ($reviewing && in_array($post['status'], ['for_approval', 'published'], true)); ?>
<div class="<?= $hasSide ? 'detail-layout' : '' ?>">
    <?php if ($hasSide) : ?>
    <aside class="detail-layout__side" aria-label="Review">
        <?php if ($post['review_remarks'] !== null) : ?>
            <div class="card cms-remarks">
                <div class="card__body"><strong>Reviewer's remarks:</strong> <?= e($post['review_remarks']) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($reviewing && $post['status'] === 'for_approval') : ?>
            <form class="plain-section cms-review" method="post" action="<?= e(route('cms.publish', ['id' => $id])) ?>">
                <?= csrf_field() ?>
                <div class="card__body">
                    <div class="field <?= $remarksError !== null ? 'field--error' : '' ?>">
                        <label class="field__label" for="remarks">Remarks <span class="field__required" aria-hidden="true">*</span></label>
                        <textarea class="textarea cms-form__excerpt" id="remarks" name="remarks" maxlength="2000" required aria-describedby="remarks-help<?= $remarksError !== null ? ' remarks-error' : '' ?>"></textarea>
                        <p class="field__help" id="remarks-help">Required for both decisions. The author sees these remarks if you return the post.</p>
                        <?php if ($remarksError !== null) : ?>
                            <p class="field__error" id="remarks-error"><?= icon('alert') ?> <?= e($remarksError) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="cluster">
                        <button type="submit" class="btn btn--primary">Publish post</button>
                        <button type="submit" class="btn btn--secondary" formaction="<?= e(route('cms.return', ['id' => $id])) ?>">Return to author</button>
                    </div>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($reviewing && $post['status'] === 'published') : ?>
            <form class="cms-remarks" method="post" action="<?= e(route('cms.archive', ['id' => $id])) ?>" data-confirm="The post will no longer be public. Archived posts cannot be published again." data-confirm-title="Archive this post?" data-confirm-action="Archive">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn--danger btn--sm">Archive post</button>
            </form>
        <?php endif; ?>
    </aside>
    <?php endif; ?>
    <div class="detail-layout__primary">
        <h2 class="cms-preview__heading">Preview: how this looks on the public page</h2>
        <div class="cms-preview">
            <?= $this->partial('cms/_article', ['post' => $post]) ?>
        </div>
    </div>
</div>
