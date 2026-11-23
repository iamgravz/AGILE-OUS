<?php

declare(strict_types=1);

use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\GuestMiddleware;

return static function (Router $router): void {
    $router->get('/', 'PortalController@home', 'home');
    $router->get('/about', 'PortalController@about', 'about');
    $router->get('/committees', 'PortalController@committees', 'committees');
    $router->get('/committees/{id}', 'PortalController@committee', 'committees.show');
    $router->get('/news', 'PortalController@news', 'news');
    $router->get('/events', 'PortalController@events', 'events');
    $router->get('/publications', 'PortalController@publications', 'publications');
    $router->get('/announcements', 'PortalController@announcements', 'announcements');
    $router->get('/positions', 'PortalController@positions', 'positions');
    $router->get('/positions/{id}', 'PortalController@position', 'positions.show');
    $router->get('/ask-for-help', 'PortalController@askForHelp', 'ask_for_help');
    $router->get('/welfare/guest', 'GuestWelfareController@show', 'welfare.guest');
    $router->post('/welfare/guest/code', 'GuestWelfareController@requestCode', 'welfare.guest.code');
    $router->post('/welfare/guest/resend', 'GuestWelfareController@resend', 'welfare.guest.resend');
    $router->post('/welfare/guest/verify', 'GuestWelfareController@verify', 'welfare.guest.verify');
    $router->post('/welfare/guest/restart', 'GuestWelfareController@restart', 'welfare.guest.restart');
    $router->post('/welfare/guest/submit', 'GuestWelfareController@submit', 'welfare.guest.submit');
    $router->get('/welfare/guest/submitted', 'GuestWelfareController@submitted', 'welfare.guest.submitted');
    $router->get('/privacy', 'PortalController@privacy', 'privacy');
    $router->get('/track', 'TrackController@show', 'track');
    $router->post('/track', 'TrackController@lookup');
    $router->post('/track/edit-link', 'TrackController@editLink', 'track.edit_link');
    $router->get('/verify/member/{token}', 'VerifyController@member', 'verify.member');
    $router->get('/verify/member/{token}/id', 'VerifyController@memberId', 'verify.member_id');
    $router->get('/verify/{token}', 'VerifyController@show', 'verify');

    $router->group(['middleware' => [GuestMiddleware::class]], static function (Router $router): void {
        $router->get('/register', 'AuthController@showRegister', 'register');
        $router->post('/register', 'AuthController@requestRegisterCode');
        $router->post('/register/verify', 'AuthController@verifyRegister', 'register.verify');
        $router->post('/register/password', 'AuthController@registerPassword', 'register.password');
        $router->post('/register/resend', 'AuthController@resendRegister', 'register.resend');
        $router->post('/register/restart', 'AuthController@restartRegister', 'register.restart');
        $router->get('/login', 'AuthController@showLogin', 'login');
        $router->post('/login', 'AuthController@login');
        $router->get('/forgot-password', 'AuthController@showForgot', 'password.forgot');
        $router->post('/forgot-password', 'AuthController@forgot');
        $router->get('/reset-password', 'AuthController@showReset', 'password.reset');
        $router->post('/reset-password', 'AuthController@reset');
        $router->get('/account/setup', 'AuthController@showSetup', 'account.setup');
        $router->post('/account/setup', 'AuthController@setup');
    });

    $router->get('/posts/{slug}', 'PortalController@post', 'posts.show');
    $router->get('/media/{id}', 'MediaController@show', 'media.show');

    $router->get('/cms', 'CmsController@index', 'cms')->middleware('perm:content.draft');
    $router->get('/cms/create', 'CmsController@create', 'cms.create')->middleware('perm:content.draft');
    $router->post('/cms', 'CmsController@store', 'cms.store')->middleware('perm:content.draft');
    $router->post('/cms/media', 'CmsController@uploadImage', 'cms.media')->middleware('perm:content.draft');
    $router->get('/cms/{id}', 'CmsController@show', 'cms.show')->middleware('perm:content.draft');
    $router->post('/cms/{id}', 'CmsController@update', 'cms.update')->middleware('perm:content.draft');
    $router->get('/cms/{id}/edit', 'CmsController@edit', 'cms.edit')->middleware('perm:content.draft');
    $router->get('/cms/{id}/review', 'CmsController@review', 'cms.review')->middleware('perm:content.publish');
    $router->post('/cms/{id}/publish', 'CmsController@publish', 'cms.publish')->middleware('perm:content.publish');
    $router->post('/cms/{id}/return', 'CmsController@returnToAuthor', 'cms.return')->middleware('perm:content.publish');
    $router->post('/cms/{id}/archive', 'CmsController@archive', 'cms.archive')->middleware('perm:content.publish');

    $router->get('/applications', 'ApplicationsController@index', 'applications')->middleware('perm:evaluation.view');
    $router->get('/applications/my-committee', 'ApplicationsController@committee', 'applications.committee')->middleware('perm:application.decide');
    $router->get('/applications/{id}', 'ApplicationsController@show', 'applications.show')->middleware('perm:evaluation.view');
    $router->get('/applications/{id}/drawer', 'ApplicationsController@drawer', 'applications.drawer')->middleware('perm:evaluation.view');
    $router->post('/applications/{id}/documents/{documentId}/verify', 'ApplicationsController@verifyDocument', 'applications.verify_document')->middleware('perm:document.verify');
    $router->post('/applications/{id}/under-review', 'ApplicationsController@markUnderReview', 'applications.under_review')->middleware('perm:application.update_status');
    $router->post('/applications/{id}/return', 'ApplicationsController@returnForRevision', 'applications.return')->middleware('perm:application.return');
    $router->post('/applications/{id}/approve', 'ApplicationsController@approve', 'applications.approve')->middleware('perm:application.decide');
    $router->post('/applications/{id}/reject', 'ApplicationsController@reject', 'applications.reject')->middleware('perm:application.decide');
    $router->post('/applications/{id}/waitlist', 'ApplicationsController@waitlist', 'applications.waitlist')->middleware('perm:application.decide');

    $router->get('/credentials/requests', 'CredentialRequestsController@index', 'credentials.requests')->middleware('perm:credential.approve');
    $router->get('/credentials/requests/{id}/photo', 'CredentialRequestsController@photo', 'credentials.requests.photo')->middleware('perm:credential.approve');
    $router->post('/credentials/requests/{id}/upload', 'CredentialRequestsController@upload', 'credentials.requests.upload')->middleware('perm:credential.approve');
    $router->post('/credentials/requests/{id}/decline', 'CredentialRequestsController@decline', 'credentials.requests.decline')->middleware('perm:credential.approve');
    $router->get('/credentials/approvals', 'CredentialApprovalsController@index', 'credentials.approvals')->middleware('perm:credential.approve');
    $router->post('/credentials/approvals/approve', 'CredentialApprovalsController@approve', 'credentials.approve')->middleware('perm:credential.approve');
    $router->post('/credentials/approvals/approve-all', 'CredentialApprovalsController@approveAll', 'credentials.approve_all')->middleware('perm:credential.approve');
    $router->post('/credentials/approvals/{id}/return', 'CredentialApprovalsController@returnForCorrection', 'credentials.return')->middleware('perm:credential.approve');
    $router->post('/credentials/approvals/{id}/regenerate', 'CredentialApprovalsController@regenerate', 'credentials.regenerate')->middleware('perm:credential.approve');

    $router->get('/accounts/approvals', 'AccountApprovalsController@index', 'accounts.approvals')->middleware('perm:account.approve');
    $router->post('/accounts/approvals/approve-selected', 'AccountApprovalsController@approveSelected', 'accounts.approve_selected')->middleware('perm:account.approve');
    $router->post('/accounts/approvals/{id}/approve', 'AccountApprovalsController@approve', 'accounts.approve')->middleware('perm:account.approve');
    $router->post('/accounts/approvals/{id}/reject', 'AccountApprovalsController@reject', 'accounts.reject')->middleware('perm:account.approve');

    $router->get('/members', 'MembersController@index', 'members')->middleware('perm:member.view');
    $router->get('/members/{id}', 'MembersController@show', 'members.show')->middleware('perm:member.view');
    $router->post('/members/{id}/resend-registration', 'MembersController@resendRegistration', 'members.resend_registration')->middleware('perm:admin.users');
    $router->post('/members/{id}/committee', 'MembersController@committee', 'members.committee')->middleware('perm:admin.settings');
    $router->post('/members/{id}/reissue', 'MembersController@reissue', 'members.reissue')->middleware('perm:credential.reissue');

    $router->get('/recruitment/hr-requests', 'HrRequestsController@index', 'hr_requests')->middleware('perm:hr_request.submit');
    $router->get('/recruitment/hr-requests/create', 'HrRequestsController@create', 'hr_requests.create')->middleware('perm:hr_request.submit');
    $router->post('/recruitment/hr-requests/create', 'HrRequestsController@store')->middleware('perm:hr_request.submit');
    $router->get('/recruitment/hr-requests/{id}', 'HrRequestsController@show', 'hr_requests.show')->middleware('perm:hr_request.submit');
    $router->get('/recruitment/hr-requests/{id}/edit', 'HrRequestsController@edit', 'hr_requests.edit')->middleware('perm:hr_request.submit');
    $router->post('/recruitment/hr-requests/{id}/edit', 'HrRequestsController@update')->middleware('perm:hr_request.submit');
    $router->post('/recruitment/hr-requests/{id}/cancel', 'HrRequestsController@cancel', 'hr_requests.cancel')->middleware('perm:hr_request.submit');
    $router->post('/recruitment/hr-requests/{id}/approve', 'HrRequestsController@approve', 'hr_requests.approve')->middleware('perm:hr_request.approve');
    $router->post('/recruitment/hr-requests/{id}/return', 'HrRequestsController@returnToRequester', 'hr_requests.return')->middleware('perm:hr_request.approve');

    $router->get('/recruitment/vacancies', 'VacanciesController@index', 'vacancies')->middleware('perm:vacancy.manage');
    $router->get('/recruitment/vacancies/create', 'VacanciesController@create', 'vacancies.create')->middleware('perm:vacancy.manage');
    $router->post('/recruitment/vacancies/create', 'VacanciesController@store')->middleware('perm:vacancy.manage');
    $router->get('/recruitment/vacancies/{id}', 'VacanciesController@show', 'vacancies.show')->middleware('perm:vacancy.manage');
    $router->get('/recruitment/vacancies/{id}/edit', 'VacanciesController@edit', 'vacancies.edit')->middleware('perm:vacancy.manage');
    $router->post('/recruitment/vacancies/{id}/edit', 'VacanciesController@update')->middleware('perm:vacancy.manage');
    $router->post('/recruitment/vacancies/{id}/publish', 'VacanciesController@publish', 'vacancies.publish')->middleware('perm:vacancy.manage');
    $router->post('/recruitment/vacancies/{id}/reopen', 'VacanciesController@reopen', 'vacancies.reopen')->middleware('perm:vacancy.manage');
    $router->post('/recruitment/vacancies/{id}/cancel', 'VacanciesController@cancel', 'vacancies.cancel')->middleware('perm:vacancy.manage');
    $router->post('/applications/{id}/interviews', 'InterviewsController@schedule', 'applications.interviews')->middleware('perm:interview.manage');
    $router->post('/applications/{id}/interviews/{interviewId}/reschedule', 'InterviewsController@reschedule', 'applications.interviews.reschedule')->middleware('perm:interview.manage');
    $router->post('/applications/{id}/interviews/{interviewId}/result', 'InterviewsController@recordResult', 'applications.interviews.result')->middleware('perm:interview.manage');
    $router->post('/applications/{id}/assessment', 'InterviewsController@assess', 'applications.assessment')->middleware('perm:interview.manage');
    $router->post('/applications/{id}/interviews/{interviewId}/checklist', 'InterviewsController@checklist', 'applications.interviews.checklist')->middleware('perm:interview.manage');
    $router->get('/recruitment/committee', 'EvaluationsController@committee', 'committee_recruitment')->middleware('perm:hr_request.submit');
    $router->get('/recruitment/interviews', 'InterviewsController@index', 'interviews')->middleware('perm:interview.manage');

    $router->get('/recruitment/appointments', 'AppointmentsController@index', 'appointments')->middleware('perm:appointment.prepare');
    $router->get('/recruitment/appointments/prepare', 'AppointmentsController@showPrepare', 'appointments.prepare')->middleware('perm:appointment.prepare');
    $router->post('/recruitment/appointments/prepare', 'AppointmentsController@prepare')->middleware('perm:appointment.prepare');
    $router->get('/recruitment/appointments/{id}', 'AppointmentsController@show', 'appointments.show')->middleware('perm:appointment.prepare');
    $router->post('/recruitment/appointments/{id}/approve', 'AppointmentsController@approve', 'appointments.approve')->middleware('perm:appointment.approve');
    $router->post('/recruitment/appointments/{id}/return', 'AppointmentsController@returnAppointment', 'appointments.return')->middleware('perm:appointment.approve');

    $router->get('/welfare/new', 'WelfareController@create', 'welfare.new')->middleware('perm:welfare.submit');
    $router->post('/welfare/new', 'WelfareController@store')->middleware('perm:welfare.submit');

    $router->get('/welfare', 'WelfareCasesController@index', 'welfare.queue')->middleware('perm:welfare.view');
    $router->get('/welfare/{id}', 'WelfareCasesController@show', 'welfare.show')->middleware('perm:welfare.view');
    $router->post('/welfare/{id}/acknowledge', 'WelfareCasesController@acknowledge', 'welfare.acknowledge')->middleware('perm:welfare.handle');
    $router->post('/welfare/{id}/notes', 'WelfareCasesController@addNote', 'welfare.notes')->middleware('perm:welfare.handle');
    $router->post('/welfare/{id}/referrals', 'WelfareCasesController@addReferral', 'welfare.referrals')->middleware('perm:welfare.handle');
    $router->post('/welfare/{id}/referrals/{referralId}', 'WelfareCasesController@updateReferral', 'welfare.referrals.update')->middleware('perm:welfare.handle');
    $router->post('/welfare/{id}/followups', 'WelfareCasesController@addFollowup', 'welfare.followups')->middleware('perm:welfare.handle');
    $router->post('/welfare/{id}/followups/{followupId}/complete', 'WelfareCasesController@completeFollowup', 'welfare.followups.complete')->middleware('perm:welfare.handle');
    $router->post('/welfare/{id}/classify', 'WelfareCasesController@classify', 'welfare.classify')->middleware('perm:welfare.assign_close');
    $router->post('/welfare/{id}/assign', 'WelfareCasesController@assign', 'welfare.assign')->middleware('perm:welfare.assign_close');
    $router->post('/welfare/{id}/resolve', 'WelfareCasesController@resolve', 'welfare.resolve')->middleware('perm:welfare.assign_close');
    $router->post('/welfare/{id}/close', 'WelfareCasesController@close', 'welfare.close')->middleware('perm:welfare.assign_close');
    $router->post('/welfare/{id}/attachments/{attachmentId}/disposition', 'WelfareCasesController@setDisposition', 'welfare.disposition')->middleware('perm:welfare.assign_close');

    $router->get('/eligibility', 'EligibilityController@index', 'eligibility')->middleware('perm:eligibility.manage');
    $router->post('/eligibility/terms', 'EligibilityController@storeTerm', 'eligibility.terms')->middleware('perm:eligibility.manage');
    $router->post('/eligibility/terms/{id}/current', 'EligibilityController@makeCurrent', 'eligibility.terms.current')->middleware('perm:eligibility.manage');
    $router->get('/eligibility/start', 'EligibilityController@showStart', 'eligibility.start')->middleware('perm:eligibility.manage');
    $router->post('/eligibility/start', 'EligibilityController@start')->middleware('perm:eligibility.manage');
    $router->post('/eligibility/review/{id}/suggest', 'EligibilityController@suggest', 'eligibility.review.suggest')->middleware('perm:eligibility.manage');

    $router->get('/admin/users', 'AdminUsersController@index', 'admin.users')->middleware('perm:admin.users');
    $router->get('/admin/users/create', 'AdminUsersController@create', 'admin.users.create')->middleware('perm:admin.users');
    $router->post('/admin/users/create', 'AdminUsersController@store')->middleware('perm:admin.users');
    $router->get('/admin/users/{id}', 'AdminUsersController@show', 'admin.users.show')->middleware('perm:admin.users');
    $router->post('/admin/users/{id}/details', 'AdminUsersController@details', 'admin.users.details')->middleware('perm:admin.users');
    $router->post('/admin/users/{id}/role', 'AdminUsersController@role', 'admin.users.role')->middleware('perm:admin.users');
    $router->post('/admin/users/{id}/status', 'AdminUsersController@status', 'admin.users.status')->middleware('perm:admin.users');
    $router->post('/admin/users/{id}/unlock', 'AdminUsersController@unlock', 'admin.users.unlock')->middleware('perm:admin.users');
    $router->post('/admin/users/{id}/reset-password', 'AdminUsersController@resetPassword', 'admin.users.reset_password')->middleware('perm:admin.users');
    $router->post('/admin/users/{id}/permissions', 'AdminUsersController@permissions', 'admin.users.permissions')->middleware('perm:admin.users');
    $router->get('/admin/roles', 'AdminUsersController@roles', 'admin.roles')->middleware('perm:admin.users');
    $router->post('/admin/roles', 'AdminUsersController@saveRoles')->middleware('perm:admin.users');

    $router->get('/admin/archive', 'DocumentArchiveController@index', 'admin.archive')->middleware('perm:backup.manage');
    $router->post('/admin/archive', 'DocumentArchiveController@run')->middleware('perm:backup.manage');
    $router->get('/admin/candidacies', 'CandidaciesController@index', 'admin.candidacies')->middleware('perm:admin.users');
    $router->post('/admin/candidacies/filing', 'CandidaciesController@filing', 'admin.candidacies.filing')->middleware('perm:admin.users');
    $router->post('/admin/candidacies/{id}/decide', 'CandidaciesController@decide', 'admin.candidacies.decide')->middleware('perm:admin.users');
    $router->post('/admin/candidacies/{id}/elected', 'CandidaciesController@elected', 'admin.candidacies.elected')->middleware('perm:admin.users');
    $router->get('/admin/elected-officers', 'ElectedOfficersController@index', 'admin.elected_officers')->middleware('perm:admin.users');
    $router->post('/admin/elected-officers', 'ElectedOfficersController@store')->middleware('perm:admin.users');
    $router->get('/admin/open-positions', 'OpenPositionsController@index', 'admin.open_positions')->middleware('perm:admin.settings');
    $router->post('/admin/open-positions', 'OpenPositionsController@save')->middleware('perm:admin.settings');

    $router->get('/admin/committees', 'AdminCommitteesController@index', 'admin.committees')->middleware('perm:admin.settings');
    $router->post('/admin/committees', 'AdminCommitteesController@store')->middleware('perm:admin.settings');
    $router->get('/admin/committees/{id}', 'AdminCommitteesController@show', 'admin.committees.show')->middleware('perm:admin.settings');
    $router->post('/admin/committees/{id}', 'AdminCommitteesController@update')->middleware('perm:admin.settings');
    $router->post('/admin/committees/{id}/delete', 'AdminCommitteesController@delete', 'admin.committees.delete')->middleware('perm:admin.settings');
    $router->post('/admin/committees/{id}/head', 'AdminCommitteesController@head', 'admin.committees.head')->middleware('perm:admin.settings');
    $router->post('/admin/positions', 'AdminCommitteesController@storePosition', 'admin.positions')->middleware('perm:admin.settings');
    $router->get('/admin/positions/{id}', 'AdminCommitteesController@showPosition', 'admin.positions.show')->middleware('perm:admin.settings');
    $router->post('/admin/positions/{id}', 'AdminCommitteesController@updatePosition')->middleware('perm:admin.settings');
    $router->post('/admin/positions/{id}/delete', 'AdminCommitteesController@deletePosition', 'admin.positions.delete')->middleware('perm:admin.settings');

    $router->get('/admin/settings', 'AdminSystemController@settings', 'admin.settings')->middleware('perm:admin.settings');
    $router->post('/admin/settings', 'AdminSystemController@saveSettings')->middleware('perm:admin.settings');

    // Read-only, apart from an export that is itself logged. No route changes or removes an audit row.
    $router->get('/admin/audit-logs', 'AdminSystemController@auditLogs', 'admin.audit_logs')->middleware('perm:audit.view');
    $router->post('/admin/audit-logs/export', 'AdminSystemController@exportAuditLogs', 'admin.audit_logs.export')->middleware('perm:audit.view');

    $router->get('/admin/system-health', 'SystemHealthController@show', 'admin.system_health')->middleware('perm:backup.manage');
    $router->post('/admin/system-health/retry-failed', 'SystemHealthController@retryFailed', 'admin.system_health.retry')->middleware('perm:backup.manage');

    $router->get('/email-templates', 'EmailAdminController@templates', 'email_templates')->middleware('perm:email_template.manage');
    $router->get('/email-templates/{id}', 'EmailAdminController@editTemplate', 'email_templates.edit')->middleware('perm:email_template.manage');
    $router->post('/email-templates/{id}', 'EmailAdminController@updateTemplate')->middleware('perm:email_template.manage');
    $router->post('/email-templates/{id}/preview', 'EmailAdminController@previewTemplate', 'email_templates.preview')->middleware('perm:email_template.manage');
    $router->post('/email-templates/{id}/test', 'EmailAdminController@testTemplate', 'email_templates.test')->middleware('perm:email_template.manage');

    $router->get('/emails', 'EmailAdminController@drafts', 'emails')->middleware('perm:email.compose');
    $router->post('/emails', 'EmailAdminController@storeDraft')->middleware('perm:email.compose');
    $router->get('/emails/create', 'EmailAdminController@createDraft', 'emails.create')->middleware('perm:email.compose');
    $router->get('/emails/{id}', 'EmailAdminController@showDraft', 'emails.show')->middleware('perm:email.compose');
    $router->post('/emails/{id}', 'EmailAdminController@updateDraft')->middleware('perm:email.compose');
    $router->post('/emails/{id}/send', 'EmailAdminController@sendDraft', 'emails.send')->middleware('perm:email.compose');

    $router->get('/dashboards', 'DashboardController@overview', 'dashboards')->middleware('perm:dashboard.view_all');
    $router->get('/reports', 'ReportsController@index', 'reports')->middleware('perm:report.export');
    $router->post('/reports/export', 'ReportsController@export', 'reports.export')->middleware('perm:report.export');

    // Public (BR-18). Applicants prove themselves with an emailed code or link, and members with their login, so
    // MembershipService makes the check on every request.
    $router->get('/apply', 'ApplyController@start', 'apply');
    $router->post('/apply/code', 'ApplyController@requestCode', 'apply.code');
    $router->post('/apply/resend', 'ApplyController@resend', 'apply.resend');
    $router->post('/apply/verify', 'ApplyController@verify', 'apply.verify');
    $router->post('/apply/restart', 'ApplyController@restart', 'apply.restart');
    $router->get('/apply/continue', 'ApplyController@showLink', 'apply.continue');
    $router->post('/apply/continue', 'ApplyController@openLink');
    $router->get('/apply/withdraw', 'ApplyController@showWithdraw', 'apply.withdraw');
    $router->post('/apply/withdraw', 'ApplyController@withdraw');
    $router->get('/apply/new', 'ApplyController@showNew', 'apply.new');
    $router->post('/apply/new', 'ApplyController@createNew');
    $router->get('/apply/{id}/step/{step}', 'ApplyController@show', 'apply.step');
    $router->post('/apply/{id}/step/{step}', 'ApplyController@save');
    $router->post('/apply/{id}/documents/{type}', 'ApplyController@uploadDocument', 'apply.document');
    $router->post('/apply/{id}/documents/{documentId}/remove', 'ApplyController@removeDocument', 'apply.document_remove');
    $router->get('/apply/files/{id}', 'FileController@applicantDocument', 'apply.document_file');
    $router->post('/apply/{id}/submit', 'ApplyController@submit', 'apply.submit');
    $router->get('/apply/{id}/submitted', 'ApplyController@success', 'apply.success');

    $router->group(['middleware' => [AuthMiddleware::class]], static function (Router $router): void {
        // Own records only, so someone appointed to a role without application.submit keeps their history.
        $router->get('/my-applications', 'MyApplicationsController@index', 'my_applications');
        $router->get('/my-applications/{id}', 'MyApplicationsController@show', 'my_applications.show');
        $router->post('/my-applications/{id}/withdraw', 'MyApplicationsController@withdraw', 'my_applications.withdraw');
        // Own records only, like applications. A linked guest case stays visible whatever the role.
        $router->get('/my-welfare-cases', 'WelfareController@index', 'my_welfare_cases');
        $router->get('/my-welfare-cases/{id}', 'WelfareController@show', 'my_welfare_cases.show');
        // Run for office (Article VI). Members only; CandidacyService checks membership, and the file route its owner.
        $router->get('/elections/candidacy', 'CandidaciesController@form', 'candidacy');
        $router->post('/elections/candidacy', 'CandidaciesController@file');
        $router->post('/elections/candidacy/{id}/withdraw', 'CandidaciesController@withdraw', 'candidacy.withdraw');
        $router->get('/elections/candidacy/{id}/files/{kind}', 'CandidaciesController@document', 'candidacy.document');
        $router->get('/my-eligibility', 'EligibilityController@mine', 'my_eligibility');
        $router->post('/my-eligibility/documents', 'EligibilityController@submitDocuments', 'my_eligibility.documents');
        // Two permissions can reach these (BR-10), so EligibilityService makes the check, per screening.
        $router->get('/eligibility/review', 'EligibilityController@reviews', 'eligibility.review');
        $router->get('/eligibility/review/{id}', 'EligibilityController@review', 'eligibility.review.show');
        $router->post('/eligibility/review/{id}/verify', 'EligibilityController@verify', 'eligibility.review.verify');
        $router->post('/eligibility/review/{id}/decide', 'EligibilityController@decide', 'eligibility.review.decide');
        $router->get('/search', 'SearchController@index', 'search');
        $router->post('/logout', 'AuthController@logout', 'logout');
        $router->get('/dashboard', 'DashboardController@index', 'dashboard');
        $router->get('/notifications', 'NotificationController@index', 'notifications');
        $router->get('/notifications/latest', 'NotificationController@latest', 'notifications.latest');
        $router->post('/notifications/read-all', 'NotificationController@markAllRead', 'notifications.read_all');
        $router->post('/notifications/{id}/read', 'NotificationController@markRead', 'notifications.read');
        $router->get('/account', 'AccountController@show', 'account');
        $router->post('/account/contact', 'AccountController@updateContact', 'account.contact');
        $router->post('/account/credential-requests', 'CredentialRequestsController@request', 'account.credential_request');
        $router->post('/account/credential-requests/{id}/cancel', 'CredentialRequestsController@cancel', 'account.credential_request.cancel');
        $router->post('/account/photo', 'AccountController@updatePhoto', 'account.photo');
        $router->post('/account/password', 'AccountController@changePassword', 'account.password');
        $router->get('/files/{type}/{id}', 'FileController@show', 'files.show');
        $router->get('/my-submissions', 'MySubmissionsController@index', 'my_submissions');
    });

    // Must not exist outside local development: the styleguide pages. (The passwordless demo login was removed, Nov 23, 2026.)
    if (config('app.env') === 'local') {
        $router->get('/styleguide', 'StyleguideController@public', 'styleguide');
        $router->get('/styleguide/dashboard', 'StyleguideController@dashboard', 'styleguide.dashboard');
        $router->get('/styleguide/auth', 'StyleguideController@auth', 'styleguide.auth');
        $router->get('/styleguide/upload', 'StyleguideController@showUpload', 'styleguide.upload');
        $router->post('/styleguide/upload', 'StyleguideController@upload');
    }

    // Temporary pages for sidebar items not built yet. They stay last because the first matching route wins,
    // so a real route declared above replaces its placeholder. Remove this block when the last one is gone.
    $router->get('/admin', 'PlaceholderController@show')->middleware('perm:admin.users');

    foreach (config('menu.dashboard', []) as $group) {
        foreach ($group['items'] as $item) {
            if ($item['permission'] !== null) {
                $router->get($item['url'], 'PlaceholderController@show')->middleware('perm:' . $item['permission']);
            }
        }
    }
};
