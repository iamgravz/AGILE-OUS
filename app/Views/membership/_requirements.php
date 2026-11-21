<?php
/**
 * AGILE's membership requirements: which documents each kind of member gives. MembershipService::requiredDocuments()
 * applies the same rules, so change both together.
 *
 * @var string|null $heading (optional) shown as an h2; leave it out to show the table alone
 */
$heading ??= null;
$rows = [
    ['General members', 'Current COR'],
    ['Executive Committee officers', 'Current COR and previous semester copy of grades'],
    ['Committee Heads, Deputy Heads and committee members', 'Current COR and previous semester copy of grades'],
    ['Year-Level Representatives', 'Current COR and previous semester copy of grades'],
    ['Applicants for any officer or committee position', 'Current COR and previous semester copy of grades'],
    ['1st year, 1st semester joiners', 'Current COR only (no grades yet)'],
    ['1st years joining in the 2nd semester', 'Current COR and 1st semester copy of grades'],
];
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
            <?php foreach ($rows as [$who, $documents]) : ?>
                <tr><td data-label="Who"><?= e($who) ?></td><td data-label="Required documents"><?= e($documents) ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="field__help">Files can be PDF, JPG or PNG, up to 5 MB each.</p>
