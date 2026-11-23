<?php

declare(strict_types=1);

// The wording of every email: code => [subject, body]. TemplateRenderer adds the banner, signature and footer when it sends,
// and fills {contact_email} from Settings. Each template only uses values its caller passes, because a missing one fails the email.
// Welfare templates never include case details, because email is not a confidential channel.

$body = static function (string $text, array $details = [], ?array $action = null, string $after = '', string $dear = '{name}'): string {
    $html = "<p>Dear {$dear},</p><p>Greetings from the AGILE OUS Membership and Student Welfare Committee!</p><p>{$text}</p>";

    if ($details !== []) {
        $lines = [];

        foreach ($details as $label => $value) {
            $lines[] = "{$label}: <strong>{$value}</strong>";
        }

        $html .= '<p class="details">' . implode('<br>', $lines) . '</p>';
    }

    if ($action !== null) {
        $html .= '<p><a class="button" href="{link}">' . $action[0] . '</a></p>';

        if (isset($action[1])) {
            $html .= "<p>{$action[1]}</p>";
        }
    }

    if ($after !== '') {
        $html .= "<p>{$after}</p>";
    }

    return $html . '<p>For assistance, contact <a href="mailto:{contact_email}">{contact_email}</a>.</p>';
};

return [
    'ACCOUNT_SETUP' => [
        'Set your AGILE password',
        $body('Your AGILE account is ready, and this email address is your login. Please set your password to start using it.', [], ['Set my password', 'This link works once and expires in 72 hours. If it has expired, choose "Forgot your password?" on the login page.']),
    ],
    'ACCOUNTS_FOR_APPROVAL' => [
        'New AGILE accounts waiting for approval',
        $body('New member accounts are waiting for your approval. Please review them so these members can log in.', ['Accounts waiting' => '{count}'], ['Review the accounts']),
    ],
    'ACCOUNT_APPROVED' => [
        'Your AGILE account was approved',
        $body('The MSW Committee Head approved your AGILE account. You can now log in with the email and password you registered.', [], ['Log in to AGILE']),
    ],
    'ACCOUNT_REJECTED' => [
        'An update on your AGILE account',
        $body('The MSW Committee Head did not approve your AGILE account. After the issue below is fixed, you may register again with your PUP student number.', ['Reason' => '{remarks}']),
    ],
    'CREDENTIALS_FOR_APPROVAL' => [
        'AGILE IDs and certificates waiting for approval',
        $body('Digital IDs and certificates are waiting for your approval. Members receive them only after you approve them.', ['Items waiting' => '{count}'], ['Review the IDs and certificates']),
    ],
    'CREDENTIAL_READY' => [
        'Your AGILE digital ID and certificate are ready',
        $body('The MSW Committee Head approved your AGILE digital ID and certificate.', [], ['{link_text}']),
    ],
    'CREDENTIAL_REQUESTED' => [
        'A member asked for their AGILE {type}',
        $body('A member asked for their {type}. Make it from the AGILE template and upload it on ID and certificate requests.', [], ['Open the requests']),
    ],
    'CREDENTIAL_UPLOADED' => [
        'Your AGILE {type} is ready',
        $body('The MSW Committee uploaded your {type}.', [], ['{link_text}']),
    ],
    'CREDENTIAL_DECLINED' => [
        'An update on your AGILE {type} request',
        $body('The MSW Committee could not prepare your {type} yet.', ['Reason' => '{remarks}'], ['Open My Account']),
    ],
    'MEMBER_REGISTRATION_CODE' => [
        'Your AGILE registration code',
        $body('Please use the code below to register your AGILE member account. Enter it on the registration page within 15 minutes.', ['Registration code' => '{code}'], null, 'If you did not ask for this code, you can ignore this message.', 'AGILE Member'),
    ],
    'MEMBER_REGISTRATION_REMINDER' => [
        'Create your AGILE account',
        $body('Your AGILE membership was approved. Please create your AGILE account at {register_url} using your PUP student number.', ['Member number' => '{member_no}', 'PUP student number' => '{student_no}'], ['Create my account']),
    ],
    'MEMBER_REGISTERED' => [
        'We received your AGILE account registration',
        $body('We received your account registration. The MSW Committee Head will review it, and we will email you when you can log in. No further action is required from you at this time.', ['Member number' => '{member_no}', 'Status' => 'Pending Review']),
    ],
    'APP_READY_FOR_DECISION' => [
        'An AGILE application is waiting for your decision ({tracking_code})',
        $body('An application is under review and all its required documents are verified. It is now waiting for your decision.', ['Tracking code' => '{tracking_code}'], ['Open the application']),
    ],
    'APPLICATION_LINK' => [
        'Your AGILE application link',
        $body('{message}', [], ['{action}', 'This link opens this application only, works once and expires in 72 hours. If you did not ask for it, you can ignore this message.']),
    ],
    'PASSWORD_RESET' => [
        'Reset your AGILE password',
        $body('We received a request to reset your AGILE password.', [], ['Reset my password', 'This link works once and expires in 60 minutes. If you did not ask for this, you can ignore this message and your password will stay the same.']),
    ],
    'GUEST_EMAIL_CODE' => [
        'Your AGILE verification code',
        $body('Please use the code below to confirm your email address. Enter it on the AGILE form within 15 minutes.', ['Verification code' => '{code}'], null, 'If you did not ask for this code, you can ignore this message.', 'Student'),
    ],
    'APP_SUBMITTED' => [
        'We received your AGILE application ({tracking_code})',
        $body('Thank you for your application. We received it and recorded it for review. No further action is required from you at this time unless additional information is requested.', ['Tracking code' => '{tracking_code}', 'Status' => 'Submitted'], ['Track my application']),
    ],
    'APP_FOR_REVISION' => [
        'Your AGILE application needs changes ({tracking_code})',
        $body('Thank you for your application. To continue its verification, please update it as described below. You do not need to apply again.', ['Tracking code' => '{tracking_code}', 'Status' => 'For Revision', 'Remarks' => '{remarks}'], ['Update my application'], 'Upload clear, complete documents. Academic records are for authorized verification only. Submission does not automatically mean acceptance or appointment.'),
    ],
    'APP_APPROVED' => [
        'Your AGILE application was approved',
        $body('We are pleased to inform you that your application was approved. {next_step}', ['Tracking code' => '{tracking_code}', 'Member number' => '{member_no}', 'Status' => 'Approved'], ['{link_text}']),
    ],
    'APP_WAITLISTED' => [
        'You are on the AGILE waiting list ({tracking_code})',
        $body('Thank you for your application. You qualified, but there is no open slot right now, so you are on our waiting list. We will email you as soon as a decision is made. You do not need to apply again.', ['Tracking code' => '{tracking_code}', 'Status' => 'Waitlisted', 'Remarks' => '{remarks}']),
    ],
    'APP_REJECTED' => [
        'An update on your AGILE application ({tracking_code})',
        $body('After review, we are unable to approve your application at this time. Thank you for your interest in AGILE.', ['Tracking code' => '{tracking_code}', 'Status' => 'Rejected', 'Remarks' => '{remarks}']),
    ],
    'INTERVIEW_INVITE' => [
        'Your AGILE interview schedule',
        $body('We are pleased to invite you to an interview as part of your application.', ['Tracking code' => '{tracking_code}', 'Date and time' => '{scheduled_at}', 'Mode' => '{mode}', 'Meeting link / venue' => '{location}'], null, 'Please be available a few minutes before your scheduled interview time.'),
    ],
    'INTERVIEW_REMINDER' => [
        'Reminder: your AGILE interview is tomorrow',
        $body('This is a reminder of your interview as part of your application.', ['Tracking code' => '{tracking_code}', 'Date and time' => '{scheduled_at}', 'Mode' => '{mode}', 'Meeting link / venue' => '{location}'], null, 'Please be available a few minutes before your scheduled interview time.'),
    ],
    'APPOINTMENT_NOTICE' => [
        'Your AGILE appointment as {position}',
        $body('We are pleased to inform you that your appointment was approved. You can view the details on your member profile.', ['Position' => '{position}', 'Effective date' => '{effective_date}'], ['Open my member profile']),
    ],
    'ROLE_EXPIRATION_REMINDER' => [
        'Submit your eligibility documents by {deadline}',
        $body('You have not yet submitted your grades and proof of enrollment for this semester. Your officer role may expire if the requirements are not met.', ['Position' => '{position}'], ['Submit my documents', 'Submission deadline: <strong>{deadline}</strong>']),
    ],
    'ROLE_EXPIRED' => [
        'Your AGILE officer role has ended',
        $body('Your officer role has expired following the academic eligibility review. You remain an Active General Member of AGILE, and a new digital ID will be ready after the MSW Committee Head reviews it.', ['Position' => '{position}', 'Remarks' => '{remarks}']),
    ],
    'WELFARE_RECEIVED' => [
        'We received your concern ({case_code})',
        $body('We received your concern. It will be handled confidentially. No further action is required from you at this time unless additional information is requested.', ['Case code' => '{case_code}'], ['Track my concern']),
    ],
    'WELFARE_UPDATE' => [
        'There is an update on your case ({case_code})',
        $body('There is an update on your case. You can see any details on the tracking page.', ['Case code' => '{case_code}', 'Status' => '{status}'], ['Track my concern']),
    ],
    'WELFARE_CLOSED' => [
        'Your case has been closed ({case_code})',
        $body('Your case has been closed. Closed cases cannot be reopened, but you may submit a new concern and mention this case code. Thank you for reaching out to AGILE.', ['Case code' => '{case_code}', 'Status' => 'Closed']),
    ],
    'WELFARE_URGENT_ALERT' => [
        'Urgent welfare case needs acknowledgment ({case_code})',
        $body('A welfare case needs your attention and must be acknowledged within 24 hours.', ['Case code' => '{case_code}'], ['Open the case']),
    ],
    'ELIGIBILITY_REQUEST' => [
        'Submit your eligibility documents for {term}',
        $body('Academic eligibility screening is now open. Please upload your grades and proof of official enrollment. Academic records are for authorized verification only.', ['Term' => '{term}'], ['Submit my documents', 'Submission deadline: <strong>{deadline}</strong>']),
    ],
    'ELIGIBILITY_FLAGGED' => [
        'Your eligibility screening for {term} was flagged',
        $body('Your eligibility screening was flagged for review. A final decision will be made after the review, and you will be notified of the result. No further action is required from you at this time unless additional information is requested.', ['Term' => '{term}', 'Status' => 'Flagged']),
    ],
    'ELIGIBILITY_RESULT' => [
        'Your eligibility result for {term}',
        $body('The review of your eligibility screening is complete.', ['Term' => '{term}', 'Status' => '{status}', 'Remarks' => '{remarks}']),
    ],
    'CONTENT_FOR_APPROVAL' => [
        'Content waiting for your approval: {title}',
        $body('A post was submitted and is waiting for your approval before it can be published.', ['Title' => '{title}', 'Submitted by' => '{author}'], ['Review the content']),
    ],
    'CONTENT_RETURNED' => [
        'Your content was returned: {title}',
        $body('Your post was returned for changes. Please update it and submit it again.', ['Title' => '{title}', 'Remarks' => '{remarks}'], ['Edit the post']),
    ],
    'HR_REQUEST_SUBMITTED' => [
        'New HR request from {committee}',
        $body('A Human Resource Request has been received and recorded. Please review the manpower requirement and supporting details.', ['Committee' => '{committee}', 'Position' => '{position}', 'Slots requested' => '{slots}', 'Status' => 'Pending Review'], ['Review the request']),
    ],
    'VACANCY_CLOSED' => [
        'Vacancy closed: {title}',
        $body('A vacancy for your committee is now closed.', ['Vacancy' => '{title}', 'Slots filled' => '{slots_filled} of {slots}', 'Status' => 'Closed'], ['View the request']),
    ],

    // Not in the section 10 list, but the jobs and recruitment flow it describes need them.
    'WELFARE_FOLLOWUP_REMINDER' => [
        'Follow-up due for case {case_code}',
        $body('A follow-up on a welfare case assigned to you is due.', ['Case code' => '{case_code}', 'Follow-up due' => '{due_at}'], ['Open the case']),
    ],
    'APP_PENDING_REMINDER' => [
        'Applications waiting for review',
        $body('Some applications have been waiting for more than 3 days. Please review them.', ['Applications waiting' => '{count}'], ['Review the applications']),
    ],
    'APP_DOCUMENT_CORRECTION' => [
        'A document in your AGILE application needs correction ({tracking_code})',
        $body('Thank you for your application. To continue its verification, a document needs correction. You do not need to apply again. You can upload a new copy when the application is returned to you for revision.', ['Tracking code' => '{tracking_code}', 'Document' => '{document}', 'Remarks' => '{remarks}'], ['View my application'], 'Upload clear, complete documents. Academic records are for authorized verification only. Submission does not automatically mean acceptance or appointment.'),
    ],
    'HR_REQUEST_APPROVED' => [
        'Your HR request for {position} was approved',
        $body('Your Human Resource Request was approved. The vacancy will be published on the portal. No further action is required from you at this time.', ['Position' => '{position}', 'Slots' => '{slots}', 'Status' => 'Approved'], ['View the request']),
    ],
    'ANNOUNCEMENT' => [
        '{subject}',
        '<p>Dear {name},</p><p>Greetings from the AGILE OUS Membership and Student Welfare Committee!</p>{body_html}<p>For assistance, contact <a href="mailto:{contact_email}">{contact_email}</a>.</p>',
    ],
    'HR_REQUEST_RETURNED' => [
        'Your HR request for {position} was returned',
        $body('Your Human Resource Request was returned. Please update it and submit it again.', ['Position' => '{position}', 'Status' => 'Returned', 'Remarks' => '{remarks}'], ['Update the request']),
    ],
];
