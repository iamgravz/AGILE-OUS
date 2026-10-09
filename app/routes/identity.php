<?php
declare(strict_types=1);

use Agile\Auth;
use Agile\MemberIdentity;

if ($path==='/staff/identity' && $method==='GET') {
    $actor=Auth::requireRole([
        'msw_head','msw_member','committee_head','deputy_head',
        'executive_officer','source_editor','president',
    ]);
    $candidates=MemberIdentity::candidates($actor);
    $html='<p>Connect your existing staff login to your approved AGILE member record. '
       .'Your registered email and active organizational assignment must match. '
       .'This requires password confirmation and a separate reviewer; self-approval is prohibited.</p>';
    if (!$candidates) $html.='<p>No eligible, unlinked member record matches your account. Contact MSW for authorized corrections.</p>';
    foreach ($candidates as $member) {
        $html.='<form method="post" action="/staff/identity/request">'
            .formToken().'<input type="hidden" name="member_id" value="'.(int)$member['id'].'">'
            .'<p><b>'.escape($member['full_name']).'</b> — member #'.(int)$member['id'].'</p>'
            .'<label>Confirm staff account password<input type="password" name="password" autocomplete="current-password" required></label>'
            .'<button type="submit">Request Independent Identity Verification</button></form>';
    }
    page('Link Staff Account to Membership',$html);
}
if ($path==='/staff/identity/request' && $method==='POST') {
    $actor=Auth::requireRole([
        'msw_head','msw_member','committee_head','deputy_head',
        'executive_officer','source_editor','president'
    ]);
    verifyCsrf();
    try {
        MemberIdentity::request((int)($_POST['member_id']??0),$actor,(string)($_POST['password']??''));
        page('Identity Verification Requested',
            '<p>Your identity-link request has been recorded. A separate authorized reviewer must approve it. '
            .'No member privileges or account associations changed yet.</p>'
            .'<p><a href="/dashboard">Return to dashboard</a></p>');
    } catch (DomainException $e) {
        http_response_code(422);
        page('Identity Request Not Accepted','<p class="error">'.escape($e->getMessage()).'</p>');
    }
}
if ($path==='/staff/identity/review' && $method==='GET') {
    $actor=Auth::requireRole(['msw_head','admin']);
    $requests=MemberIdentity::pendingForReviewer($actor);
    $html='<p>Only the designated independent reviewer may link an identity. '
        .'Technical Admin may review only MSW Head identity links, without access to confidential case details.</p>';
    if (!$requests) $html.='<p>No identity requests are awaiting your review.</p>';
    foreach ($requests as $r) {
        $html.='<section><p><b>'.escape($r['full_name']).'</b> / '.escape($r['email'])
           .' · User role: '.escape($r['role'])
           .' · Member #'.(int)$r['member_id'].'</p>'
           .'<p>Requested: '.escape($r['requested_at']).' · Expires: '.escape($r['expires_at']).'</p>'
           .'<form method="post" action="/staff/identity/review">'.formToken()
           .'<input type="hidden" name="request_id" value="'.(int)$r['id'].'">'
           .'<label>Reviewer decision/evidence (minimum 20 characters)<textarea name="note" required minlength="20" maxlength="1000"></textarea></label>'
           .'<button name="decision" value="approve">Approve Verified Link</button>'
           .'<button name="decision" value="reject">Reject Request</button></form></section><hr>';
    }
    page('Review Membership Identity Links',$html);
}
if ($path==='/staff/identity/review' && $method==='POST') {
    $actor=Auth::requireRole(['msw_head','admin']);verifyCsrf();
    $decision=(string)($_POST['decision']??'');
    if (!in_array($decision,['approve','reject'],true)) {
        http_response_code(400);page('Invalid Decision','<p>Unknown reviewer decision.</p>');
    }
    try {
        MemberIdentity::review((int)($_POST['request_id']??0),$actor,
            $decision==='approve',(string)($_POST['note']??''));
        redirect('/staff/identity/review');
    } catch (DomainException $e) {
        http_response_code(422);
        page('Identity Review Not Accepted','<p class="error">'.escape($e->getMessage()).'</p>');
    }
}
