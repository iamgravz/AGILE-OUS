<?php
declare(strict_types=1);
use Agile\Auth;
use Agile\Member;

if($path==='/staff/members'&&$method==='GET'){
    $actor=Auth::requireRole(['msw_head']);
    $rows=db()->query('SELECT id,full_name,membership_type,membership_status,valid_until,user_id FROM members ORDER BY id DESC LIMIT 100')->fetchAll();
    $html='<h2>Approved Members</h2><p>Invitations are queued until an authorized Gmail transport is configured.</p>'
      .'<table><tr><th>Member</th><th>Type</th><th>Status</th><th>Account</th></tr>';
    foreach($rows as $m){
        $html.='<tr><td>'.escape($m['full_name']).'</td><td>'.escape($m['membership_type']).'</td>'
             .'<td>'.escape($m['membership_status']).'</td><td>';
        if($m['user_id']===null&&$m['membership_status']==='active'){
            $html.='<form method="post" action="/staff/members/invite">'.formToken()
               .'<input type="hidden" name="id" value="'.(int)$m['id'].'"><button>Send account invitation</button></form>';
        }else $html.=$m['user_id']===null?'No account':'Activated';
        $html.='</td></tr>';
    }
    page('Membership Records',$html.'</table>');
}
if($path==='/staff/members/invite'&&$method==='POST'){
    $actor=Auth::requireRole(['msw_head']);verifyCsrf();
    try{Member::sendInvitation((int)($_POST['id']??0),$actor);redirect('/staff/members');}
    catch(DomainException $e){http_response_code(422);page('Invitation Error','<p class="error">'.escape($e->getMessage()).'</p>');}
}
if($path==='/claim'&&$method==='GET'){
    $token=(string)($_GET['token']??'');
    if(!preg_match('/^[a-f0-9]{64}$/',$token)){http_response_code(400);page('Invalid Invitation','<p>Invalid activation link.</p>');}
    page('Activate Member Account','<form method="post" action="/claim">'.formToken()
      .'<input type="hidden" name="token" value="'.escape($token).'">'
      .'<label>New password (minimum 12 characters)<input type="password" name="password" minlength="12" required autocomplete="new-password"></label>'
      .'<button>Activate Account</button></form>');
}
if($path==='/claim'&&$method==='POST'){
    verifyCsrf();
    try{Member::claim((string)($_POST['token']??''),(string)($_POST['password']??''));
        page('Account Activated','<p>Account activated. <a href="/login">Sign in</a> using your member email.</p>');
    }catch(DomainException $e){http_response_code(422);page('Activation Failed','<p class="error">'.escape($e->getMessage()).'</p>');}
}
if($path==='/member/card'&&$method==='GET'){
    $actor=Auth::requireRole(['member','msw_head','msw_member','committee_head','deputy_head','executive_officer','source_editor','president']);
    $m=Member::own($actor);
    if(!$m){http_response_code(404);page('Member Not Found','<p>Account is not linked to a member record.</p>');}
    $url=rtrim(envValue('APP_URL','http://localhost:8000'),'/').'/member/verify?code='.rawurlencode($m['verification_token_hash']);
    $html='<h2>AGILE OUS Digital Member ID</h2><p><strong>'.escape($m['full_name']).'</strong></p>'
      .'<p>Membership: '.escape($m['membership_type']).' · Status: '.escape($m['membership_status']).'</p>'
      .'<p>Valid until: '.escape((string)($m['valid_until']??'No date assigned')).'</p>'
      .'<p>Verification URL: <a href="'.escape($url).'">'.escape($url).'</a></p>'
      .'<p>Verification is private until you opt in; the public lookup will otherwise return unavailable.</p>'
      .'<form method="post" action="/member/verification-visibility">'.formToken()
      .'<label><input type="checkbox" name="enabled" value="yes" style="display:inline;width:auto" '
      .((int)$m['public_verification_enabled']?'checked':'').'> Allow public verification of my name and active membership status</label>'
      .'<button>Save Privacy Preference</button></form>'
      .'<p><a href="/member/certificate">View printable membership certificate</a></p>'
      .'<p><a href="/member/academic">My semester verification and corrections</a></p>';
    page('My Membership',$html);
}
if($path==='/member/verification-visibility'&&$method==='POST'){
    $actor=Auth::requireRole(['member','msw_head','msw_member','committee_head','deputy_head','executive_officer','source_editor','president']);verifyCsrf();
    try{Member::setPublicVerification($actor,($_POST['enabled']??'')==='yes');redirect('/member/card');}
    catch(DomainException $e){http_response_code(422);page('Preference Error','<p class="error">'.escape($e->getMessage()).'</p>');}
}
if($path==='/member/verify'&&$method==='GET'){
    $card=Member::publicVerification((string)($_GET['code']??''));
    if(!$card){http_response_code(404);page('Verification Unavailable','<p>No publicly verifiable member record for this code.</p>');}
    page('AGILE Membership Verification','<p>Name: <b>'.escape($card['full_name']).'</b></p>'
      .'<p>Membership status: <b>'.($card['valid']?'Active and valid':'Inactive or expired').'</b></p>'
      .'<p>Category: '.escape($card['membership_type']).'</p><p>Do not rely on a screenshot alone; check the current record at this URL.</p>');
}
if($path==='/member/certificate'&&$method==='GET'){
    $actor=Auth::requireRole(['member','msw_head','msw_member','committee_head','deputy_head','executive_officer','source_editor','president']);$m=Member::own($actor);
    if(!$m){http_response_code(404);page('Certificate Unavailable','<p>Member account not linked.</p>');}
    page('Membership Certificate','<div style="border:5px double #b18430;padding:40px;text-align:center;margin:32px 0">'
      .'<h2 style="color:#70102d">AGILE OUS</h2><p>Certificate of Membership</p>'
      .'<h1>'.escape($m['full_name']).'</h1>'
      .'<p>Recognized as an AGILE OUS '.escape($m['membership_type']).' member</p>'
      .'<p>Membership status: '.escape($m['membership_status']).'</p>'
      .'<p>Certificate record ID: '.(int)$m['id'].'</p></div>'
      .'<p><button onclick="window.print()">Print Certificate</button></p>');
}
