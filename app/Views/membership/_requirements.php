<?php
/**
 * AGILE's membership requirements: which documents each kind of member gives (MembershipService::REQUIREMENT_GROUPS).
 *
 * @var string|null $heading (optional) shown as an h2; leave it out to show the table alone
 */
$heading ??= null;
?>
<?php if ($heading !== null) : ?>
    <h2><?= e($heading) ?></h2>
<?php endif; ?>
<div class="table-wrap requirements-table">
    <table class="table table--stack">
        <caption class="sr-only">Required documents for each kind of member</caption>
        <thead>
            <tr><th scope="col">Who</th><th scope="col">Required documents</th></tr>
        </thead>
        <tbody>
            <?php foreach (\App\Services\MembershipService::REQUIREMENT_GROUPS as [$who, $documents]) : ?>
                <tr><td data-label="Who"><?= e($who) ?></td><td data-label="Required documents"><?= e($documents) ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="field__help">Files can be PDF, JPG or PNG, up to 5 MB each.</p>
