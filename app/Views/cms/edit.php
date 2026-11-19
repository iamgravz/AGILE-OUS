<?php
/**
 * @var \App\Core\View $this
 * @var array<string, mixed>|null $post
 */
$value = static fn (string $key): string => (string) old($key, $post[$key] ?? '');
$dateValue = static function (string $key) use ($post): string {
    $old = old($key);

    if ($old !== '') {
        return (string) $old;
    }

    return ($post[$key] ?? null) === null ? '' : date('Y-m-d\TH:i', strtotime($post[$key]));
};
$selectedType = $value('type') ?: 'news';
$typeError = field_error('type');
$bodyError = field_error('body');
$heading = $post === null ? 'New post' : 'Edit post';
?>
<?php $this->start('title') ?><?= e($heading) ?><?php $this->end() ?>
<?php $this->start('form_side') ?><?= $this->partial('partials/side_panel', ['blocks' => [['title' => 'Before you publish', 'items' => ['Nothing is public until the MSW Committee Head approves it.', 'Use a cover image in JPG or PNG.', 'Leave the address blank to make one from the title.']], ['title' => 'Events', 'text' => 'Events need a start date. Add a venue, or a full link for an online event.']]]) ?><?php $this->end() ?>
<?php $this->start('page_title') ?><?= e($heading) ?><?php $this->end() ?>
<?php $this->start('head') ?>
<link rel="stylesheet" href="<?= e(asset('vendor/quill/quill.snow.css')) ?>">
<script src="<?= e(asset('vendor/quill/quill.js')) ?>" defer></script>
<script src="<?= e(asset('js/cms-editor.js')) ?>" defer></script>
<?php $this->end() ?>

<?php if ($post !== null && $post['status'] === 'returned' && $post['review_remarks'] !== null) : ?>
    <div class="alert alert--warning" role="status">
        <?= icon('alert') ?>
        <p class="alert__text"><strong>Returned for changes:</strong> <?= e($post['review_remarks']) ?></p>
    </div>
<?php endif; ?>

<form class="card cms-form" method="post" enctype="multipart/form-data" action="<?= e($post === null ? route('cms.store') : route('cms.update', ['id' => $post['post_id']])) ?>" data-cms-form>
    <?= csrf_field() ?>
    <div class="card__body">
        <div class="field <?= $typeError !== null ? 'field--error' : '' ?>">
            <label class="field__label" for="type">Type <span class="field__required" aria-hidden="true">*</span></label>
            <select class="select" id="type" name="type" required data-cms-type>
                <?php foreach (\App\Services\CmsService::TYPES as $type) : ?>
                    <option value="<?= e($type) ?>"<?= $selectedType === $type ? ' selected' : '' ?>><?= e(ucfirst($type)) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($typeError !== null) : ?>
                <p class="field__error"><?= icon('alert') ?> <?= e($typeError) ?></p>
            <?php endif; ?>
        </div>
        <?= $this->partial('partials/form_field', ['name' => 'title', 'label' => 'Title', 'value' => $value('title'), 'autocomplete' => 'off']) ?>
        <?= $this->partial('partials/form_field', ['name' => 'slug', 'label' => 'Link name', 'value' => $value('slug'), 'autocomplete' => 'off', 'required' => false, 'help' => 'Made from the title if left blank. The page address will be /posts/your-link-name.']) ?>
        <div class="field <?= field_error('excerpt') !== null ? 'field--error' : '' ?>">
            <label class="field__label" for="excerpt">Excerpt</label>
            <textarea class="textarea cms-form__excerpt" id="excerpt" name="excerpt" maxlength="300" aria-describedby="excerpt-help"><?= e($value('excerpt')) ?></textarea>
            <p class="field__help" id="excerpt-help">A short summary for list pages, up to 300 characters. Required before submitting.</p>
            <?php if (field_error('excerpt') !== null) : ?>
                <p class="field__error"><?= icon('alert') ?> <?= e(field_error('excerpt')) ?></p>
            <?php endif; ?>
        </div>

        <fieldset class="field-group cms-form__event" data-cms-event>
            <legend class="field-group__legend">Event details</legend>
            <div class="grid grid--2">
                <?= $this->partial('partials/form_field', ['name' => 'event_start', 'label' => 'Starts', 'type' => 'datetime-local', 'value' => $dateValue('event_start'), 'required' => false]) ?>
                <?= $this->partial('partials/form_field', ['name' => 'event_end', 'label' => 'Ends', 'type' => 'datetime-local', 'value' => $dateValue('event_end'), 'required' => false]) ?>
            </div>
            <?= $this->partial('partials/form_field', ['name' => 'venue_or_link', 'label' => 'Venue or online link', 'value' => $value('venue_or_link'), 'required' => false, 'help' => 'Start and venue are required before submitting an event.']) ?>
        </fieldset>

        <div class="field <?= $bodyError !== null ? 'field--error' : '' ?>">
            <label class="field__label" for="body">Body</label>
            <div data-cms-editor hidden></div>
            <textarea class="textarea" id="body" name="body" rows="12" data-cms-body><?= e($value('body')) ?></textarea>
            <p class="field__error" data-cms-editor-error hidden role="alert"></p>
            <?php if ($bodyError !== null) : ?>
                <p class="field__error"><?= icon('alert') ?> <?= e($bodyError) ?></p>
            <?php endif; ?>
        </div>

        <?php if (($post['cover_image'] ?? null) !== null) : ?>
            <img class="cms-form__cover" src="<?= e(media_url($post['cover_image'])) ?>" alt="Current cover image" width="240" height="135">
        <?php endif; ?>
        <?= $this->partial('partials/upload_field', [
            'name' => 'cover_image',
            'label' => ($post['cover_image'] ?? null) !== null ? 'Replace cover image' : 'Cover image',
            'types' => ['jpg', 'jpeg', 'png'],
            'maxMb' => (new \App\Services\FileStorageService())->maxUploadMb(),
            'required' => false,
            'help' => 'Optional. JPG or PNG. Cover images are public once the post is published.',
        ]) ?>
    </div>
    <div class="card__footer cluster">
        <button type="submit" class="btn btn--secondary" name="intent" value="draft">Save draft</button>
        <button type="submit" class="btn btn--primary" name="intent" value="submit">Submit for approval</button>
    </div>
</form>
