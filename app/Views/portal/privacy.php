<?php /** @var \App\Core\View $this */ ?>
<?php $this->start('title') ?>Privacy Notice<?php $this->end() ?>
<?php $this->start('description') ?>How AGILE collects, uses and protects your personal data under the Data Privacy Act of 2012 (RA 10173).<?php $this->end() ?>
<?php $this->start('docs_group') ?>Legal<?php $this->end() ?>
<?php $this->start('docs_eyebrow') ?>Republic Act No. 10173<?php $this->end() ?>
<?php $this->start('docs_title') ?>Privacy Notice<?php $this->end() ?>
<?php $this->start('docs_updated') ?>Last updated November 1, 2026<?php $this->end() ?>
<?php $this->start('docs_context') ?>
<div class="card">
    <div class="card__header"><h2 class="card__title">At a glance</h2></div>
    <div class="card__body">
        <ul class="docs-card__list">
            <li>We collect only what your application, concern or screening needs.</li>
            <li>Welfare cases are encrypted, and only the MSW Committee Head and the President can open them.</li>
            <li>Uploaded files stay off the public website, and sensitive access is logged.</li>
            <li>We share data outside AGILE only with your agreement, for a referral you were told about, or when the law requires it.</li>
            <li>You can ask to see, correct or remove your data, and you can complain to the National Privacy Commission.</li>
        </ul>
        <h3 class="docs-card__heading">Questions about your data?</h3>
        <p><a href="mailto:<?= e(org_contact_email()) ?>"><?= e(org_contact_email()) ?></a></p>
    </div>
</div>
<?php $this->end() ?>
<div class="article__body">
        <p>The Academic Guild of IT Learners and Experts (AGILE) respects your privacy. This notice explains how this system handles your personal data under the Data Privacy Act of 2012.</p>

        <h2>What we collect</h2>
        <ul>
            <li><strong>Account details:</strong> your name, email, PUP student number, contact number and optional profile photo.</li>
            <li><strong>Membership and recruitment details:</strong> your application answers, uploaded documents, evaluations and interview results.</li>
            <li><strong>Welfare concerns:</strong> what you choose to tell us in a concern, and any attachments. Guests also give a name, an email and an optional student number.</li>
            <li><strong>Eligibility details:</strong> for officers, the grades and proof of enrollment submitted for screening each semester.</li>
            <li><strong>System records:</strong> logs of logins and sensitive actions, including IP address and browser type, for security.</li>
        </ul>

        <h2>Why we use it</h2>
        <ul>
            <li>To process your membership application, any position you apply for, and the member account created when you are approved.</li>
            <li>To respond to and follow up on welfare concerns.</li>
            <li>To issue digital IDs and certificates, and to check officers' academic eligibility.</li>
            <li>To send you notices about your applications, concerns and member account.</li>
            <li>To keep the system secure and to prepare reports for the organization.</li>
        </ul>
        <p>We process your data with your consent, which you give when you submit a form, and collect only what we need for these purposes.</p>

        <h2>Who can see it</h2>
        <ul>
            <li>Membership and recruitment records are limited to the MSW Committee and to evaluators, according to their roles.</li>
            <li>Welfare cases can be seen only by the MSW Committee Head and the President, and every view is logged.</li>
            <li>We share your data outside AGILE only when you agree, when it is needed to handle a referral you were told about, or when the law requires it.</li>
        </ul>
        <p>Automated tools may suggest values from uploaded documents, but a person always confirms them. A tool never approves, rejects or disqualifies anyone. If AGILE turns this feature on, the eligibility documents a reviewer chooses are sent to an outside AI provider to be read, and the page where you submit them says so first.</p>

        <h2>How we protect it</h2>
        <p>Passwords are stored as secure hashes. Welfare case text is encrypted. Uploaded files are kept outside the public website and are released only after a permission check, and access to sensitive records is logged.</p>

        <h2>How long we keep it</h2>
        <p>We keep your data only as long as it is needed for the purposes above and for the organization's records, or as the law requires.</p>
        <ul class="portal-list">
            <li>Your member record and history stay while you are a member, because they prove your membership and the positions you held.</li>
            <li>The COR and copy of grades you upload are moved to a protected archive about a semester after your application or candidacy is decided. Only authorized MSW Committee staff can open them.</li>
            <li>Welfare concerns stay encrypted for as long as they are kept.</li>
            <li>You may ask the MSW Committee to correct your data, or to remove data AGILE no longer needs, at <a href="mailto:<?= e(org_contact_email()) ?>"><?= e(org_contact_email()) ?></a>.</li>
        </ul>

        <h2>Your rights</h2>
        <p>Under the Data Privacy Act, you have the right to:</p>
        <ul>
            <li>be informed about how your data is used;</li>
            <li>access your data, and correct anything inaccurate;</li>
            <li>object to processing, and withdraw your consent;</li>
            <li>have your data removed or blocked, when the law allows;</li>
            <li>get a copy of your data in a portable format;</li>
            <li>claim damages and file a complaint with the National Privacy Commission.</li>
        </ul>

        <h2>Contact us</h2>
        <p>For questions or requests about your data, email <a href="mailto:<?= e(org_contact_email()) ?>"><?= e(org_contact_email()) ?></a>.</p>
</div>
