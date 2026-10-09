<?php
declare(strict_types=1);
namespace Agile;

use DomainException;
use RuntimeException;

final class Messaging {
    /** A unique event key ensures retries or repeated UI submits cannot send duplicates. */
    public static function enqueue(string $key,string $recipient,string $subject,string $html): void {
        if (!preg_match('/^[a-zA-Z0-9_.:-]{4,160}$/',$key)||
            !filter_var($recipient,FILTER_VALIDATE_EMAIL)||strlen($recipient)>190||
            mb_strlen($subject)<3||mb_strlen($subject)>255||mb_strlen($html)>20000) {
            throw new DomainException('Invalid notification details.');
        }
        $q=\db()->prepare('INSERT IGNORE INTO notification_outbox (event_key,recipient,subject,body) VALUES (?,?,?,?)');
        $q->execute([$key,strtolower($recipient),$subject,$html]);
    }
    public static function notify(int $userId,string $key,string $title,string $body): void {
        $q=\db()->prepare('INSERT IGNORE INTO notifications(user_id,event_key,title,body) VALUES(?,?,?,?)');
        $q->execute([$userId,$key,mb_substr($title,0,255),mb_substr($body,0,1000)]);
    }
    public static function branded(string $heading,string $message): string {
        return '<!doctype html><html><body style="background:#faf7f5;padding:24px;font-family:Arial,sans-serif">'
          . '<div style="max-width:640px;margin:auto;background:#fff;border:1px solid #e2d3d8;border-radius:8px">'
          . '<div style="background:#70102d;color:#fff;padding:20px;font-size:22px;font-weight:bold">AGILE OUS</div>'
          . '<div style="padding:24px"><h2 style="color:#70102d">'.\escape($heading).'</h2>'
          . '<p style="line-height:1.6">'.nl2br(\escape($message)).'</p></div>'
          . '<div style="background:#f5ece1;padding:14px;color:#633f26;font-size:13px">'
          . 'Membership and Student Welfare Committee · AGILE OUS · PUP Open University System</div>'
          . '</div></body></html>';
    }
    public static function staffMessages(int $staffId): array {
        $q=\db()->prepare('SELECT id,event_key,title,body,created_at,read_at FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 50');
        $q->execute([$staffId]); return $q->fetchAll();
    }
    public static function markRead(int $id,int $staffId): void {
        $q=\db()->prepare('UPDATE notifications SET read_at=NOW() WHERE id=? AND user_id=?');
        $q->execute([$id,$staffId]);
    }
    /** Actual sending only when Gmail OAuth credentials are explicitly configured. */
    public static function sendGmail(string $recipient,string $subject,string $html): string {
        if (\envValue('MAIL_TRANSPORT','disabled')!=='gmail') throw new RuntimeException('Gmail transport disabled.');
        foreach (['GMAIL_CLIENT_ID','GMAIL_CLIENT_SECRET','GMAIL_REFRESH_TOKEN','GMAIL_SENDER'] as $name) {
            if (\envValue($name)==='') throw new RuntimeException('Gmail OAuth configuration incomplete.');
        }
        if (!extension_loaded('curl')) throw new RuntimeException('PHP cURL extension is required for Gmail.');
        $post=http_build_query([
           'client_id'=>\envValue('GMAIL_CLIENT_ID'),'client_secret'=>\envValue('GMAIL_CLIENT_SECRET'),
           'refresh_token'=>\envValue('GMAIL_REFRESH_TOKEN'),'grant_type'=>'refresh_token'
        ]);
        $ch=curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$post,
            CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded']]);
        $result=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data=is_string($result)?json_decode($result,true):null;
        if ($status!==200||!is_array($data)||empty($data['access_token'])) {
            throw new RuntimeException('Gmail token refresh failed (credentials or service).');
        }
        $sender=\envValue('GMAIL_SENDER');
        if (!filter_var($sender,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Invalid sender.');
        $encodedSubject='=?UTF-8?B?'.base64_encode($subject).'?=';
        $message="From: AGILE OUS <".$sender.">\r\nTo: ".$recipient.
                 "\r\nSubject: ".$encodedSubject.
                 "\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n".$html;
        $raw=rtrim(strtr(base64_encode($message),'+/','-_'),'=');
        $ch=curl_init('https://gmail.googleapis.com/gmail/v1/users/me/messages/send');
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(['raw'=>$raw],JSON_THROW_ON_ERROR),
            CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_HTTPHEADER=>[
                'Content-Type: application/json','Authorization: Bearer '.$data['access_token']
            ]]);
        $res=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        curl_close($ch);
        $payload=is_string($res)?json_decode($res,true):null;
        if ($status<200||$status>=300||!is_array($payload)||empty($payload['id'])) {
            // After the POST, we cannot safely assume Gmail did not accept the message.
            throw new MailDeliveryUncertain('Gmail submission result could not be confirmed.');
        }
        return (string)$payload['id']; // submitted to Gmail, not delivered or read
    }
    public static function work(int $limit=10): array {
        return MailQueue::work($limit);
    }
}
