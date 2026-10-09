<?php
declare(strict_types=1);

use Agile\Auth;
use Agile\Mfa;

if ($path==='/mfa/setup' && $method==='GET') {
    $actor=Auth::user();
    if (!$actor || !Mfa::required($actor)) {\redirect('/login');}
    if (Mfa::enrolled((int)$actor['id'])) {\redirect('/mfa/challenge');}
    page('Enable Staff Multi-Factor Authentication',
        '<p>For all AGILE OUS organizational staff, password-only login is not sufficient. '
        .'Use an authenticator app with a time-based 6-digit code. '
        .'A system administrator must securely configure the MFA encryption key before enrollment.</p>'
        .'<form method="post" action="/mfa/enroll">'.formToken()
        .'<label>Confirm your current password<input type="password" name="password" required autocomplete="current-password"></label>'
        .'<button>Generate Authenticator Secret</button></form>'
        .'<p>Already started? Enter your password again to generate a fresh setup secret. '
        .'Previous unconfirmed enrollment will be superseded.</p>'
        .'<form method="post" action="/logout">'.formToken().'<button>Sign Out</button></form>');
}
if ($path==='/mfa/enroll' && $method==='POST') {
    $actor=Auth::user();
    if (!$actor || !Mfa::required($actor)) {http_response_code(403);page('Access Denied','<p>Staff login required.</p>');}
    verifyCsrf();
    try {
        $secret=Mfa::begin($actor,(string)($_POST['password']??''));
        // On-screen only: the secret must never be written into the DB in plaintext
        // or echoed in a URL, log or e-mail.
        $html='<p>Enter this secret manually into your authenticator app. '
            .'Keep it private; it will not be shown again:</p>'
            .'<p><code id="mfa-secret">'.escape($secret).'</code></p>'
            .'<p>Choose "time-based" and 6 digits.</p>'
            .'<form method="post" action="/mfa/confirm">'.formToken()
            .'<label>Current authenticator code<input name="code" inputmode="numeric" pattern="[0-9]{6}"'
            .' autocomplete="one-time-code" required maxlength="6"></label>'
            .'<button>Confirm and Enable MFA</button></form>'
            .'<p>If you leave this screen, restart setup from the MFA enrollment page.</p>';
        page('Complete MFA Enrollment',$html);
    } catch (DomainException|RuntimeException $e) {
        http_response_code(422);
        page('Authenticator Enrollment Unavailable','<p class="error">'.escape($e->getMessage()).'</p>'
            .'<p><a href="/mfa/setup">Try again</a></p>');
    }
}
if ($path==='/mfa/confirm' && $method==='POST') {
    $actor=Auth::user();
    if (!$actor || !Mfa::required($actor)) {http_response_code(403);page('Access Denied','<p>Staff login required.</p>');}
    verifyCsrf();
    try {
        $recovery=Mfa::confirm($actor,(string)($_POST['code']??''));
        $html='<p class="notice">Your authenticator is active. '
             .'Save each recovery code in a secure password manager now. '
             .'Each code can be used only once and will never be shown again.</p>'
             .'<ul>';
        foreach($recovery as $code)$html.='<li><code>'.escape($code).'</code></li>';
        $html.='</ul><p>Store these offline. Do not email or screenshot them.</p>'
             .'<p><a href="/dashboard">Go to Dashboard</a></p>';
        page('Staff MFA Enabled — Save Recovery Codes',$html);
    } catch(DomainException|RuntimeException $e) {
        http_response_code(422);
        page('MFA Confirmation Failed','<p class="error">'.escape($e->getMessage()).'</p>'
             .'<p><a href="/mfa/setup">Restart setup</a></p>');
    }
}
if ($path==='/mfa/challenge' && $method==='GET') {
    $actor=Auth::user();
    if (!$actor || !Mfa::required($actor)) {\redirect('/login');}
    if (!Mfa::enrolled((int)$actor['id'])) {\redirect('/mfa/setup');}
    if(Mfa::sessionVerified($actor)) {\redirect('/dashboard');}
    page('Staff Authenticator Verification',
        '<p>To access AGILE OUS staff records, verify your second factor.</p>'
        .'<form method="post" action="/mfa/verify">'.formToken()
        .'<label>6-digit code or one-time recovery code'
        .'<input type="text" name="code" autocomplete="one-time-code" maxlength="32" required></label>'
        .'<button>Verify Secure Login</button></form>'
        .'<p>Lost your authenticator and recovery codes? Contact your authorized administrator '
        .'for a documented identity-recovery review; do not create another privileged account.</p>'
        .'<form method="post" action="/logout">'.formToken().'<button>Sign Out</button></form>');
}
if ($path==='/mfa/verify' && $method==='POST') {
    $actor=Auth::user();
    if (!$actor || !Mfa::required($actor)) {http_response_code(403);page('Access Denied','<p>Staff login required.</p>');}
    verifyCsrf();
    try {
        if(Mfa::challenge($actor,(string)($_POST['code']??''))) {
            \redirect(match($actor['role']) {
                'committee_head'=>'/staff/hr',
                'source_editor','executive_officer'=>'/staff/content',
                default=>'/dashboard'
            });
        }
        http_response_code(401);
        page('MFA Verification Failed','<p class="error">Invalid or already-used authenticator or recovery code.</p>'
            .'<p><a href="/mfa/challenge">Try again</a></p>');
    } catch(DomainException|RuntimeException $e) {
        http_response_code(422);
        page('MFA Verification Unavailable','<p class="error">'.escape($e->getMessage()).'</p>'
            .'<p><a href="/mfa/challenge">Return to verification</a></p>');
    }
}
