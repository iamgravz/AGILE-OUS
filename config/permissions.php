<?php

declare(strict_types=1);

// Default role-permission mapping from PROJECT_CONTEXT.md section 6. The guest role has no permissions.
return [
    'application.submit' => [
        'description' => 'Submit a membership application',
        'roles' => ['general_member'],
    ],
    'service_request.submit' => [
        'description' => 'Send a service request to the MSW Committee',
        'roles' => ['general_member', 'committee_head', 'execomm', 'msw_member', 'msw_head', 'president'],
    ],
    'service_request.manage' => [
        'description' => 'Handle service requests',
        'roles' => ['msw_member', 'msw_head'],
    ],
    'welfare.submit' => [
        'description' => 'Submit a welfare concern',
        'roles' => ['general_member', 'committee_head', 'execomm', 'msw_member', 'msw_head', 'president'],
    ],
    'profile.manage_own' => [
        'description' => 'Manage own profile, digital ID and certificate',
        'roles' => ['general_member', 'committee_head', 'execomm', 'msw_member', 'msw_head', 'president'],
    ],
    'hr_request.submit' => [
        'description' => 'Submit an HR request for own committee',
        'roles' => ['committee_head', 'msw_head'],
    ],
    'hr_request.approve' => [
        'description' => 'Approve or return HR requests',
        'roles' => ['msw_head'],
    ],
    'vacancy.manage' => [
        'description' => 'Create and manage vacancies',
        'roles' => ['msw_head'],
    ],
    'document.verify' => [
        'description' => 'Verify applicant documents',
        'roles' => ['msw_member', 'msw_head'],
    ],
    'application.update_status' => [
        'description' => 'Update application status',
        'roles' => ['msw_member', 'msw_head'],
    ],
    'evaluation.submit' => [
        'description' => 'Submit an applicant evaluation',
        'roles' => ['committee_head', 'msw_member', 'msw_head'],
    ],
    'evaluation.view' => [
        'description' => 'Review evaluations and approved applications',
        'roles' => ['committee_head', 'msw_member', 'msw_head'],
    ],
    'event.attendance' => [
        'description' => 'Check members in at events and export attendance',
        'roles' => ['committee_head', 'msw_member', 'msw_head'],
    ],
    'interview.manage' => [
        'description' => 'Schedule and record interviews',
        'roles' => ['msw_member', 'msw_head'],
    ],
    'account.approve' => [
        'description' => 'Approve or reject new accounts (BR-21)',
        'roles' => ['msw_head'],
    ],
    'application.decide' => [
        'description' => 'Approve or reject applications. Committee heads: own committee only (BR-20)',
        'roles' => ['committee_head', 'msw_head'],
    ],
    'application.return' => [
        'description' => 'Return applications for revision',
        'roles' => ['msw_member', 'msw_head'],
    ],
    'appointment.prepare' => [
        'description' => 'Prepare appointment records',
        'roles' => ['msw_member', 'msw_head'],
    ],
    'appointment.approve' => [
        'description' => 'Approve appointments',
        'roles' => ['msw_head'],
    ],
    'member.view' => [
        'description' => 'View member records',
        'roles' => ['msw_member', 'msw_head'],
    ],
    'welfare.view' => [
        'description' => 'View welfare cases and details',
        'roles' => ['msw_head', 'president'],
    ],
    'welfare.handle' => [
        'description' => 'Handle welfare cases: notes, referrals and follow-ups',
        'roles' => ['msw_head', 'president'],
    ],
    'welfare.assign_close' => [
        'description' => 'Assign, reassign and close welfare cases',
        'roles' => ['msw_head'],
    ],
    'content.draft' => [
        'description' => 'Draft news, events, publications and announcements',
        'roles' => ['committee_head', 'msw_member', 'msw_head'],
    ],
    'content.publish' => [
        'description' => 'Approve and publish content',
        'roles' => ['msw_head'],
    ],
    'email.compose' => [
        'description' => 'Compose email drafts',
        'roles' => ['msw_member', 'msw_head'],
    ],
    'email_template.manage' => [
        'description' => 'Manage email templates',
        'roles' => ['msw_head'],
    ],
    'credential.approve' => [
        'description' => 'Approve or return digital IDs and certificates (BR-19)',
        'roles' => ['msw_head'],
    ],
    'credential.reissue' => [
        'description' => "Revoke a member's digital ID and certificate and issue new ones",
        'roles' => ['msw_head'],
    ],
    'eligibility.manage' => [
        'description' => 'Manage terms, verify eligibility, decide, and use AI extraction',
        'roles' => ['msw_head'],
    ],
    'eligibility.decide_msw_head' => [
        'description' => "Verify and decide the MSW Head's own eligibility screening only",
        'roles' => ['president'],
    ],
    'dashboard.view_all' => [
        'description' => 'View read-only dashboards across all modules',
        'roles' => ['execomm', 'msw_head', 'president'],
    ],
    'report.export' => [
        'description' => 'Generate and export reports',
        'roles' => ['msw_head'],
    ],
    'admin.users' => [
        'description' => 'Manage users and roles',
        'roles' => ['msw_head'],
    ],
    'admin.settings' => [
        'description' => 'Manage system settings',
        'roles' => ['msw_head'],
    ],
    'audit.view' => [
        'description' => 'View audit logs',
        'roles' => ['msw_head'],
    ],
    'backup.manage' => [
        'description' => 'Manage database backups',
        'roles' => ['msw_head'],
    ],
];
