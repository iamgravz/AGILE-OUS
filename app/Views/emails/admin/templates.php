<?php
/**
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $templates
 */
?>
<?php $this->start('title') ?>Email templates<?php $this->end() ?>
<?php $this->start('page_title') ?>Email templates<?php $this->end() ?>

<p class="text-muted">The emails the system sends on its own. Open one to change its subject and wording.</p>
<div class="table-wrap">
    <table class="table table--stack">
        <caption class="sr-only">Email templates</caption>
        <thead><tr><th scope="col">Code</th><th scope="col">Subject</th><th scope="col">Last updated</th></tr></thead>
        <tbody>
            <?php foreach ($templates as $template) : ?>
                <tr>
                    <td data-label="Code"><a href="<?= e(route('email_templates.edit', ['id' => (int) $template['template_id']])) ?>"><code><?= e($template['code']) ?></code></a></td>
                    <td data-label="Subject"><span class="truncate truncate--wide" title="<?= e($template['subject']) ?>"><?= e($template['subject']) ?></span></td>
                    <td data-label="Last updated"><?= e(date_ph($template['updated_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
