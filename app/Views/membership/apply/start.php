<?php
/**
 * The first page of applying, like the login page: Iskobot standing on the card that holds the form. Below the email
 * and student number: what they apply for (General Membership or a position the MSW Committee Head opened), its
 * requirements (a dropdown that opens), and the upload fields those requirements need: the COR always, the grades for
 * a position. The choice is kept until step 3, where it is already selected; the files join the application then.
 *
 * @var \App\Core\View $this
 * @var bool $forPosition they came from a position's page
 * @var list<array{position_id: int, title: string, committee_name: string, label: string, remaining: ?int}> $positions open for application
 */
$consentError = field_error('privacy_consent');
$choiceError = field_error('applying_for');
$corError = field_error('cor');
$gradesError = field_error('grades');
$uploadAgain = field_error('upload_again');
$requirementError = field_error('requirement');
$positionError = field_error('position_id');
$role = (string) old('applying_for', '');
$chosenPosition = (string) old('position_id', '');
$requirement = (string) old('requirement', '');
$maxMb = (new \App\Services\FileStorageService())->maxUploadMb();
$group = \App\Services\MembershipService::REQUIREMENT_GROUPS[$requirement] ?? null;
$requiredDocs = $group[1] ?? 'Choose the row that fits you.';
$gradesNeeded = (bool) ($group[2] ?? false);
$gradesLabel = $group[3] ?? 'Previous semester copy of grades';
// The first dropdown's choices and, for each, the open positions the second dropdown lists.
$roles = [
    'committee_head' => 'Committee Head',
    'deputy' => 'Deputy Committee Head',
    'committee_member' => 'Committee Member',
    'source_code' => 'The Source Code',
];
$byRole = array_fill_keys(array_keys($roles), []);

foreach ($positions as $position) {
    $byRole[$position['role_type']][] = $position;
}

$slots = static fn (array $p): string => $p['remaining'] === null ? '' : ' (' . $p['remaining'] . ' ' . ($p['remaining'] === 1 ? 'slot' : 'slots') . ' left)';
?>
<?php $this->start('title') ?>Apply for membership<?php $this->end() ?>
<?php $this->start('description') ?>Apply for AGILE membership. You do not need an account. We confirm your email with a one-time code.<?php $this->end() ?>
<?php $this->start('head') ?>
<link rel="stylesheet" href="<?= e(asset('css/robot.css')) ?>">
<script src="<?= e(asset('js/apply-start.js')) ?>" defer></script>
<?php $this->end() ?>
<?php $this->start('no_flash') ?>1<?php $this->end() ?>
<?php $this->start('hero') ?>
<section class="glass-stage">
    <div class="container">
        <?= $this->partial('partials/flash') ?>
        <div class="form-layout">
            <div class="form-layout__main robot-stage apply-start">
                <h1>Apply for membership</h1>
                <?php if ($forPosition) : ?>
                    <p class="portal-lead">Officer positions are open to AGILE members. If you are a member, please <a href="<?= e(route('login')) ?>">log in</a>. If not, apply here first.</p>
                <?php else : ?>
                    <p class="portal-lead">You do not need an account. We send a 6-digit code to confirm that this email is yours.</p>
                <?php endif; ?>

                <?= $this->partial('partials/robot', ['bubble' => "Hi! I'm Iskobot. Let's get you into AGILE!"]) ?>

                <form class="card glass robot-card" method="post" enctype="multipart/form-data" action="<?= e(route('apply.code')) ?>">
                    <?= csrf_field() ?>
                    <div class="card__body">
                        <?= $this->partial('partials/form_field', ['name' => 'first_name', 'label' => 'First name', 'value' => (string) old('first_name'), 'autocomplete' => 'given-name']) ?>
                        <?= $this->partial('partials/form_field', ['name' => 'last_name', 'label' => 'Last name', 'value' => (string) old('last_name'), 'autocomplete' => 'family-name']) ?>
                        <?= $this->partial('partials/form_field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => (string) old('email'), 'autocomplete' => 'email']) ?>
                        <?= $this->partial('partials/form_field', ['name' => 'student_no', 'label' => 'PUP student number', 'value' => (string) old('student_no'), 'autocomplete' => 'off', 'help' => 'For example, 2024-xxxxx-MN-0.']) ?>

                        <div class="field <?= $choiceError !== null ? 'field--error' : '' ?>">
                            <label class="field__label" for="applying_for">Applying for <span class="field__required" aria-hidden="true">*</span></label>
                            <select class="select" id="applying_for" name="applying_for" required data-applying-select<?= $choiceError !== null ? ' aria-invalid="true" aria-describedby="applying-for-error"' : '' ?>>
                                <option value="">Choose one</option>
                                <option value="general" data-kind="general"<?= $role === 'general' ? ' selected' : '' ?>>General Membership</option>
                                <?php foreach ($roles as $key => $label) : ?>
                                    <option value="<?= e($key) ?>" data-kind="position"<?= $byRole[$key] === [] ? ' disabled' : '' ?><?= $role === $key ? ' selected' : '' ?>><?= e($label) ?><?= $byRole[$key] === [] ? ' (none open)' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($choiceError !== null) : ?>
                                <p class="field__error" id="applying-for-error"><?= icon('alert') ?> <?= e($choiceError) ?></p>
                            <?php endif; ?>
                        </div>

                        <?php // The 7 committees, or the posts in The Source Code. Grouped by the first dropdown, which shows only its own group. ?>
                        <div class="field <?= $positionError !== null ? 'field--error' : '' ?>" data-position-field<?= $role === 'general' ? ' hidden' : '' ?>>
                            <label class="field__label" for="position_id"><span data-position-label><?= $role === 'source_code' ? 'Post in The Source Code' : 'Committee' ?></span> <span class="field__required" aria-hidden="true">*</span></label>
                            <select class="select" id="position_id" name="position_id" data-position-select<?= $positionError !== null ? ' aria-invalid="true" aria-describedby="position-error"' : '' ?>>
                                <option value="">Choose one</option>
                                <?php foreach ($roles as $key => $label) : ?>
                                    <?php if ($byRole[$key] !== []) : ?>
                                        <optgroup label="<?= e($label) ?>" data-role="<?= e($key) ?>">
                                            <?php foreach ($byRole[$key] as $position) : ?>
                                                <option value="<?= e($position['position_id']) ?>"<?= $chosenPosition === (string) $position['position_id'] ? ' selected' : '' ?>><?= e($key === 'source_code' ? $position['title'] : $position['committee_name']) ?><?= e($slots($position)) ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($positionError !== null) : ?>
                                <p class="field__error" id="position-error"><?= icon('alert') ?> <?= e($positionError) ?></p>
                            <?php endif; ?>
                        </div>

                        <?php // AGILE's requirements table as a dropdown: the row that fits them sets the documents and upload fields below. ?>
                        <div class="field <?= $requirementError !== null ? 'field--error' : '' ?>">
                            <label class="field__label" for="requirement">Requirements <span class="field__required" aria-hidden="true">*</span></label>
                            <select class="select" id="requirement" name="requirement" required data-requirement-select aria-describedby="requirement-docs<?= $requirementError !== null ? ' requirement-error' : '' ?>"<?= $requirementError !== null ? ' aria-invalid="true"' : '' ?>>
                                <option value="">Which one are you?</option>
                                <?php foreach (\App\Services\MembershipService::REQUIREMENT_GROUPS as $key => [$who, $documents, $needsGrades, $rowGradesLabel]) : ?>
                                    <option value="<?= e($key) ?>" data-documents="<?= e($documents) ?>" data-grades="<?= $needsGrades ? '1' : '0' ?>" data-grades-label="<?= e($rowGradesLabel ?? '') ?>"<?= $requirement === $key ? ' selected' : '' ?>><?= e($who) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($requirementError !== null) : ?>
                                <p class="field__error" id="requirement-error"><?= icon('alert') ?> <?= e($requirementError) ?></p>
                            <?php endif; ?>
                        </div>

                        <?php
                        // Branching: nothing to upload until a Requirements row is chosen. Then the COR for every row, and the
                        // grades only for the rows that ask for them. data-pending hides this only when JavaScript runs (.js),
                        // so without it the fields still show and the server checks them.
                        ?>
                        <div class="apply-uploads" data-uploads<?= $requirement === '' ? ' data-pending' : '' ?>>
                        <p class="apply-required-docs" id="requirement-docs"><strong>Required documents:</strong> <span data-required-docs><?= e($requiredDocs) ?></span></p>

                        <?php if ($uploadAgain !== null) : ?>
                            <p class="field__error"><?= icon('alert') ?> <?= e($uploadAgain) ?></p>
                        <?php endif; ?>

                        <div class="field <?= $corError !== null ? 'field--error' : '' ?>" data-upload="cor">
                            <label class="field__label" for="cor">Current COR (Certificate of Registration) <span class="field__required" aria-hidden="true">*</span></label>
                            <input class="input apply-upload" id="cor" name="cor" type="file" accept=".pdf,.jpg,.jpeg,.png" required aria-describedby="cor-help<?= $corError !== null ? ' cor-error' : '' ?>"<?= $corError !== null ? ' aria-invalid="true"' : '' ?>>
                            <p class="field__help" id="cor-help">PDF, JPG or PNG, up to <?= e($maxMb) ?> MB.</p>
                            <?php if ($corError !== null) : ?>
                                <p class="field__error" id="cor-error"><?= icon('alert') ?> <?= e($corError) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="field <?= $gradesError !== null ? 'field--error' : '' ?>" data-upload="grades"<?= $requirement !== '' && !$gradesNeeded ? ' hidden' : '' ?>>
                            <label class="field__label" for="grades"><span data-grades-label><?= e($gradesLabel) ?></span> <span class="field__required" aria-hidden="true">*</span></label>
                            <input class="input apply-upload" id="grades" name="grades" type="file" accept=".pdf,.jpg,.jpeg,.png"<?= $gradesNeeded ? ' required' : '' ?> aria-describedby="grades-help<?= $gradesError !== null ? ' grades-error' : '' ?>"<?= $gradesError !== null ? ' aria-invalid="true"' : '' ?>>
                            <p class="field__help" id="grades-help">PDF, JPG or PNG, up to <?= e($maxMb) ?> MB.</p>
                            <?php if ($gradesError !== null) : ?>
                                <p class="field__error" id="grades-error"><?= icon('alert') ?> <?= e($gradesError) ?></p>
                            <?php endif; ?>
                        </div>
                        </div>

                        <div class="hp-field" aria-hidden="true">
                            <label for="website">Leave this field empty</label>
                            <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                        </div>
                        <div class="field <?= $consentError !== null ? 'field--error' : '' ?>">
                            <label class="check">
                                <input type="checkbox" name="privacy_consent" value="1" required<?= $consentError !== null ? ' aria-invalid="true" aria-describedby="privacy-consent-error"' : '' ?>>
                                <span>I have read the <a href="<?= e(route('privacy')) ?>">Privacy Notice</a> and agree to the processing of my personal data under the Data Privacy Act of 2012 (RA 10173). <span class="field__required" aria-hidden="true">*</span></span>
                            </label>
                            <?php if ($consentError !== null) : ?>
                                <p class="field__error" id="privacy-consent-error"><?= icon('alert') ?> <?= e($consentError) ?></p>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="btn btn--primary btn--block">Send my code</button>
                        <p class="login-page__links">
                            <a href="<?= e(route('login')) ?>">Already a member? Log in</a>
                            <a href="<?= e(route('track')) ?>">Already applied? Track it</a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
<?php $this->end() ?>
