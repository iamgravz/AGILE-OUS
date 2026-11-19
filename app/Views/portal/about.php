<?php
/**
 * @var \App\Core\View $this
 * @var string $mission
 * @var string $vision
 * @var list<array<string, mixed>> $committees
 */
// Name, position and committee only. The rest of each row is never printed.
$officers = (new \App\Models\MemberRepository())->search(['type' => 'officer', 'role_status' => 'active'], 1, 100)['data'];
?>
<?php $this->start('title') ?>About<?php $this->end() ?>
<?php $this->start('description') ?><?= e($mission) ?><?php $this->end() ?>
<?php $this->start('docs_group') ?>About<?php $this->end() ?>
<?php $this->start('docs_eyebrow') ?>PUP Open University System<?php $this->end() ?>
<?php $this->start('docs_title') ?>About AGILE<?php $this->end() ?>
<?php $this->start('docs_context') ?>
<div class="card">
    <div class="card__header"><h2 class="card__title">Quick facts</h2></div>
    <div class="card__body">
        <dl class="detail-list">
            <div><dt>Full name</dt><dd>Academic Guild of IT Learners and Experts</dd></div>
            <div><dt>University</dt><dd>PUP Open University System</dd></div>
            <div><dt>Program</dt><dd>BSIT</dd></div>
            <?php if ($committees !== []) : ?><div><dt>Committees</dt><dd><?= e(count($committees)) ?></dd></div><?php endif; ?>
            <div><dt>Contact</dt><dd><a href="mailto:<?= e(org_contact_email()) ?>"><?= e(org_contact_email()) ?></a></dd></div>
        </dl>
    </div>
</div>
<?php $this->end() ?>
<p class="portal-lead">The Academic Guild of IT Learners and Experts is the BSIT student organization of the PUP Open University System.</p>

<section class="portal-section">
    <h2>Our mission</h2>
    <p><?= e($mission) ?></p>
    <h2>Our vision</h2>
    <p><?= e($vision) ?></p>
</section>

<section class="portal-section" aria-labelledby="what-we-do">
    <h2 id="what-we-do">What AGILE does</h2>
    <ul class="portal-list">
        <li><strong>Membership:</strong> keeps member records for BSIT-OU students and issues digital IDs and certificates.</li>
        <li><strong>Recruitment:</strong> fills committee positions through HR requests, evaluations and interviews.</li>
        <li><strong>Student welfare:</strong> gives students a confidential way to ask for help with academic, financial and personal concerns.</li>
        <li><strong>Academic eligibility:</strong> checks each semester that officers meet the academic requirements for their roles.</li>
        <li><strong>News and events:</strong> posts announcements, activities and publications, each approved before it goes public.</li>
    </ul>
</section>

<section class="portal-section" aria-labelledby="officers">
    <h2 id="officers">Our officers</h2>
    <?php if ($officers === []) : ?>
        <div class="empty-state">
            <p class="empty-state__text">The officer list appears here once appointments are approved.</p>
            <a class="btn btn--secondary btn--sm" href="<?= e(route('positions')) ?>">See open positions</a>
        </div>
    <?php else : ?>
        <div class="card-grid card-grid--officers">
            <?php foreach ($officers as $officer) : ?>
                <?= $this->partial('portal/_officer_card', ['officer' => $officer]) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="portal-section" aria-labelledby="committees">
    <h2 id="committees">Our committees</h2>
    <?php if ($committees === []) : ?>
        <div class="empty-state"><p class="empty-state__text">The committee list appears here once the MSW Committee adds it.</p></div>
    <?php else : ?>
        <div class="card-grid">
            <?php foreach ($committees as $committee) : ?>
                <div class="card">
                    <div class="card__body">
                        <h3><?= e($committee['name']) ?></h3>
                        <p><?= e($committee['description'] ?? '') ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
