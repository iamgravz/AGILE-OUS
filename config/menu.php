<?php

declare(strict_types=1);

// Paths are plain strings because most of these routes arrive in later steps, and error pages must render
// even when the router failed to load. A null permission means any signed-in user.
return [
    'public' => [
        ['label' => 'Home', 'url' => '/'],
        ['label' => 'About', 'url' => '/about'],
        ['label' => 'Committees', 'url' => '/committees'],
        ['label' => 'News', 'url' => '/news'],
        ['label' => 'Events', 'url' => '/events'],
        ['label' => 'Publications', 'url' => '/publications'],
        ['label' => 'Open Positions', 'url' => '/positions'],
        ['label' => 'Track Status', 'url' => '/track'],
        ['label' => 'Ask for Help', 'url' => '/ask-for-help'],
    ],
    'dashboard' => [
        [
            'heading' => 'Dashboard',
            'items' => [
                ['label' => 'Dashboard', 'url' => '/dashboard', 'icon' => 'home', 'permission' => null],
                ['label' => 'All Modules', 'url' => '/dashboards', 'icon' => 'chart', 'permission' => 'dashboard.view_all'],
            ],
        ],
        [
            'heading' => 'My Account',
            'items' => [
                ['label' => 'My Account', 'url' => '/account', 'icon' => 'user', 'permission' => null],
                ['label' => 'My Submissions', 'url' => '/my-submissions', 'icon' => 'inbox', 'permission' => null],
            ],
        ],
        [
            'heading' => 'Membership',
            'items' => [
                ['label' => 'My Applications', 'url' => '/my-applications', 'icon' => 'clipboard', 'permission' => 'application.submit'],
                ['label' => 'Run for office', 'url' => '/elections/candidacy', 'icon' => 'award', 'permission' => 'welfare.submit'],
                ['label' => 'Members', 'url' => '/members', 'icon' => 'users', 'permission' => 'member.view'],
                // Manual mode (config/credentials.php): members request, the MSW Committee Head uploads from the template.
                ['label' => 'ID and certificate requests', 'url' => '/credentials/requests', 'icon' => 'award', 'permission' => 'credential.approve', 'badge' => 'credential_requests'],
            ],
        ],
        [
            'heading' => 'Recruitment',
            'items' => [
                ['label' => 'HR Requests', 'url' => '/recruitment/hr-requests', 'icon' => 'file', 'permission' => 'hr_request.submit'],
                ['label' => 'Vacancies', 'url' => '/recruitment/vacancies', 'icon' => 'briefcase', 'permission' => 'vacancy.manage'],
                ['label' => 'Open for application', 'url' => '/admin/open-positions', 'icon' => 'clipboard', 'permission' => 'admin.settings'],
                ['label' => 'Applications', 'url' => '/applications', 'icon' => 'inbox', 'permission' => 'evaluation.view'],
                ['label' => 'Applications to my committee', 'url' => '/applications/my-committee', 'icon' => 'inbox', 'permission' => 'application.decide'],
                ['label' => 'My Committee', 'url' => '/recruitment/committee', 'icon' => 'users', 'permission' => 'hr_request.submit'],
                ['label' => 'Interviews', 'url' => '/recruitment/interviews', 'icon' => 'calendar', 'permission' => 'interview.manage'],
                ['label' => 'Appointments', 'url' => '/recruitment/appointments', 'icon' => 'award', 'permission' => 'appointment.prepare'],
            ],
        ],
        [
            'heading' => 'Welfare',
            'items' => [
                ['label' => 'Ask for Help', 'url' => '/welfare/new', 'icon' => 'heart', 'permission' => 'welfare.submit'],
                ['label' => 'My Welfare Cases', 'url' => '/my-welfare-cases', 'icon' => 'inbox', 'permission' => 'welfare.submit'],
                ['label' => 'Welfare Queue', 'url' => '/welfare', 'icon' => 'shield', 'permission' => 'welfare.view'],
            ],
        ],
        [
            'heading' => 'Eligibility',
            'items' => [
                ['label' => 'Eligibility Screening', 'url' => '/eligibility', 'icon' => 'alert', 'permission' => 'eligibility.manage'],
                ['label' => 'MSW Head Screening', 'url' => '/eligibility/review', 'icon' => 'alert', 'permission' => 'eligibility.decide_msw_head'],
                ['label' => 'My Eligibility', 'url' => '/my-eligibility', 'icon' => 'clipboard', 'permission' => 'profile.manage_own'],
            ],
        ],
        [
            'heading' => 'Content',
            'items' => [
                ['label' => 'Content', 'url' => '/cms', 'icon' => 'megaphone', 'permission' => 'content.draft'],
            ],
        ],
        [
            'heading' => 'Communications',
            'items' => [
                ['label' => 'Email Drafts', 'url' => '/emails', 'icon' => 'mail', 'permission' => 'email.compose'],
                ['label' => 'Email Templates', 'url' => '/email-templates', 'icon' => 'layers', 'permission' => 'email_template.manage'],
            ],
        ],
        [
            'heading' => 'Reports',
            'items' => [
                ['label' => 'Reports', 'url' => '/reports', 'icon' => 'chart', 'permission' => 'report.export'],
            ],
        ],
        [
            'heading' => 'Administration',
            'items' => [
                ['label' => 'New accounts for approval', 'url' => '/accounts/approvals', 'icon' => 'users', 'permission' => 'account.approve', 'badge' => 'accounts'],
                ['label' => 'Users', 'url' => '/admin/users', 'icon' => 'users', 'permission' => 'admin.users'],
                ['label' => 'Candidacies', 'url' => '/admin/candidacies', 'icon' => 'clipboard', 'permission' => 'admin.users'],
                ['label' => 'Elected officers', 'url' => '/admin/elected-officers', 'icon' => 'users', 'permission' => 'admin.users'],
                ['label' => 'Roles and permissions', 'url' => '/admin/roles', 'icon' => 'shield', 'permission' => 'admin.users'],
                ['label' => 'Committees and positions', 'url' => '/admin/committees', 'icon' => 'layers', 'permission' => 'admin.settings'],
                ['label' => 'Settings', 'url' => '/admin/settings', 'icon' => 'settings', 'permission' => 'admin.settings'],
                ['label' => 'Audit log', 'url' => '/admin/audit-logs', 'icon' => 'list', 'permission' => 'audit.view'],
                ['label' => 'Document archive', 'url' => '/admin/archive', 'icon' => 'database', 'permission' => 'backup.manage'],
                ['label' => 'System health', 'url' => '/admin/system-health', 'icon' => 'database', 'permission' => 'backup.manage'],
            ],
        ],
    ],
];
