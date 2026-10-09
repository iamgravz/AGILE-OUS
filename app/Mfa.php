<?php
declare(strict_types=1);
namespace Agile;

use DomainException;
use RuntimeException;

/**
 * Time-based one-time passwords (RFC 6238) with single-use recovery codes.
 * No vendor SDK or external TOTP provider. Secrets encrypted at rest.
 */
final class Mfa {
    private const STAFF_ROLES = [
        'msw_head','msw_member','president','admin','committee_head',
        'deputy_head','executive_officer','source_editor',
    ];
    private const ALPHABET='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const PERIOD=30;
    private const SESSION_SECONDS=8*3600;
    private const INACTIVITY_SECONDS=30*60;

    public static function required(array $actor): bool {
        return in_array((string)($actor['role']??''),self::STAFF_ROLES,true);
    }
    private static function key(): string {
        if (!extension_loaded('sodium')) throw new RuntimeException('PHP sodium extension is required for MFA.');
        $key=base64_decode(\envValue('MFA_KEY_B64'),true);
        if (!is_string($key)||strlen($key)!==SODIUM_CRYPTO_SECRETBOX_KEYBYTES)
            throw new RuntimeException('MFA encryption key not configured. Contact the technical administrator.');
        return $key;
    }
    public static function toBase32(string $binary): string {
        $bits='';
        foreach (str_split($binary) as $ch) $bits.=str_pad(decbin(ord($ch)),8,'0',STR_PAD_LEFT);
        $out='';
        for($i=0;$i<strlen($bits);$i+=5){
            $chunk=substr($bits,$i,5);
            $out.=self::ALPHABET[bindec(str_pad($chunk,5,'0',STR_PAD_RIGHT))];
        }
        return $out;
    }
    public static function fromBase32(string $code): string {
        $code=strtoupper(str_replace([' ','-','='],'',$code));
        if ($code===''||preg_match('/[^A-Z2-7]/',$code))
            throw new DomainException('Invalid authenticator secret.');
        $bits='';
        foreach(str_split($code) as $ch){
            $i=strpos(self::ALPHABET,$ch);
            $bits.=str_pad(decbin($i),5,'0',STR_PAD_LEFT);
        }
        $bytes='';
        for($i=0;$i+8<=strlen($bits);$i+=8)$bytes.=chr(bindec(substr($bits,$i,8)));
        return $bytes;
    }
    private static function encryptSecret(string $secret): string {
        $nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce.sodium_crypto_secretbox($secret,$nonce,self::key()));
    }
    private static function decryptSecret(string $encrypted): string {
        $blob=base64_decode($encrypted,true);
        if ($blob===false||strlen($blob)<SODIUM_CRYPTO_SECRETBOX_NONCEBYTES+SODIUM_CRYPTO_SECRETBOX_MACBYTES)
            throw new RuntimeException('Encrypted MFA credential is malformed.');
        $nonce=substr($blob,0,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $secret=sodium_crypto_secretbox_open(substr($blob,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),$nonce,self::key());
        if ($secret===false) throw new RuntimeException('MFA secret could not be decrypted.');
        return $secret;
    }

    public static function totp(string $base32,int $step): string {
        if ($step<0) throw new DomainException('Invalid TOTP counter.');
        $secret=self::fromBase32($base32);
        $mac=hash_hmac('sha1',pack('N2',intdiv($step,4294967296),$step%4294967296),$secret,true);
        $offset=ord($mac[19])&0x0f;
        $truncated=(unpack('N',substr($mac,$offset,4))[1]&0x7fffffff)%1000000;
        return str_pad((string)$truncated,6,'0',STR_PAD_LEFT);
    }
    public static function matchingStep(string $base32,string $code,?int $now=null): ?int {
        if (!preg_match('/^\d{6}$/D',$code))return null;
        $step=intdiv($now??time(),self::PERIOD);
        for($delta=-1;$delta<=1;$delta++){
            $candidate=$step+$delta;
            if($candidate>=0&&hash_equals(self::totp($base32,$candidate),$code))
                return $candidate;
        }
        return null;
    }
    public static function enrolled(int $userId): bool {
        $q=\db()->prepare('SELECT confirmed_at FROM mfa_credentials WHERE user_id=?');
        $q->execute([$userId]);
        return (bool)$q->fetchColumn();
    }
    public static function sessionVerified(array $user): bool {
        if (!self::required($user))return true;
        \startSession();
        $now=time();
        $valid=(int)($_SESSION['mfa_ok_uid']??0)===(int)$user['id']
          &&($_SESSION['mfa_ok_role']??'')===$user['role']
          &&$now-(int)($_SESSION['mfa_at']??0)<=self::SESSION_SECONDS
          &&$now-(int)($_SESSION['mfa_last_seen']??0)<=self::INACTIVITY_SECONDS
          &&self::enrolled((int)$user['id']);
        if($valid)$_SESSION['mfa_last_seen']=$now;
        return $valid;
    }
    public static function markSession(array $user): void {
        \startSession();
        session_regenerate_id(true);
        $_SESSION['mfa_ok_uid']=(int)$user['id'];
        $_SESSION['mfa_ok_role']=(string)$user['role'];
        $_SESSION['mfa_at']=time();
        $_SESSION['mfa_last_seen']=time();
        $_SESSION['csrf']=bin2hex(random_bytes(32));
    }
    public static function begin(array $user,string $password): string {
        if (!self::required($user))throw new DomainException('MFA enrollment is for staff accounts.');
        $q=\db()->prepare('SELECT password_hash,is_active,role FROM users WHERE id=?');
        $q->execute([(int)$user['id']]);$current=$q->fetch();
        if(!$current||!$current['is_active']||$current['role']!==$user['role']||
            !password_verify($password,$current['password_hash']))
            throw new DomainException('Account password could not be verified.');
        if(self::enrolled((int)$user['id']))throw new DomainException('Authenticator already enrolled.');
        $secret=self::toBase32(random_bytes(20));
        $encrypted=self::encryptSecret($secret);
        $q=\db()->prepare('INSERT INTO mfa_credentials(user_id,secret_ciphertext) VALUES (?,?)
          ON DUPLICATE KEY UPDATE secret_ciphertext=IF(confirmed_at IS NULL,VALUES(secret_ciphertext),secret_ciphertext)');
        $q->execute([(int)$user['id'],$encrypted]);
        \audit((int)$user['id'],'mfa.enrollment_started','user',(int)$user['id']);
        return $secret; // only returned to the immediate enrollment screen, never logged
    }
    private static function lockedOut(\PDO $pdo,int $userId): bool {
        $q=$pdo->prepare('SELECT COUNT(*) FROM mfa_attempts WHERE user_id=? AND attempted_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)');
        $q->execute([$userId]);
        return (int)$q->fetchColumn()>=5;
    }
    private static function failedAttempt(\PDO $pdo,int $userId): void {
        $pdo->prepare('INSERT INTO mfa_attempts(user_id) VALUES (?)')->execute([$userId]);
    }
    public static function confirm(array $user,string $code): array {
        if(!self::required($user))throw new DomainException('Only a staff account can enroll.');
        $id=(int)$user['id'];
        $pdo=\db();$pdo->beginTransaction();
        try{
            $q=$pdo->prepare('SELECT * FROM mfa_credentials WHERE user_id=? FOR UPDATE');
            $q->execute([$id]);$row=$q->fetch();
            if(!$row||$row['confirmed_at']!==null)throw new DomainException('No unconfirmed MFA enrollment.');
            if(self::lockedOut($pdo,$id))throw new DomainException('Too many verification attempts. Try again after 15 minutes.');
            $step=self::matchingStep(self::decryptSecret($row['secret_ciphertext']),trim($code));
            if($step===null){
                self::failedAttempt($pdo,$id);
                $pdo->commit();
                throw new DomainException('Invalid authenticator code.');
            }
            $codes=[];
            for($i=0;$i<8;$i++){
                $plain=strtoupper(bin2hex(random_bytes(12)));
                $codes[]=$plain;
                $pdo->prepare('INSERT INTO mfa_recovery_codes(user_id,code_hash) VALUES (?,?)')
                    ->execute([$id,hash('sha256',$plain)]);
            }
            $pdo->prepare('UPDATE mfa_credentials SET confirmed_at=NOW(),last_accepted_step=? WHERE user_id=? AND confirmed_at IS NULL')
                ->execute([$step,$id]);
            $pdo->prepare('DELETE FROM mfa_attempts WHERE user_id=?')->execute([$id]);
            \audit($id,'mfa.enrolled','user',$id);
            $pdo->commit();
            self::markSession($user);
            return $codes;
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    public static function challenge(array $user,string $input): bool {
        if(!self::required($user))throw new DomainException('Only staff accounts require this challenge.');
        $id=(int)$user['id'];$code=strtoupper(str_replace([' ','-'],'',trim($input)));
        $pdo=\db();$pdo->beginTransaction();
        try{
            $q=$pdo->prepare('SELECT * FROM mfa_credentials WHERE user_id=? FOR UPDATE');
            $q->execute([$id]);$row=$q->fetch();
            if(!$row||$row['confirmed_at']===null)throw new DomainException('Authenticator enrollment required.');
            if(self::lockedOut($pdo,$id))throw new DomainException('Too many verification attempts. Try again after 15 minutes.');
            $step=self::matchingStep(self::decryptSecret($row['secret_ciphertext']),$code);
            $valid=false;
            if($step!==null&&($row['last_accepted_step']===null||$step>(int)$row['last_accepted_step'])){
                $pdo->prepare('UPDATE mfa_credentials SET last_accepted_step=? WHERE user_id=?')->execute([$step,$id]);
                $valid=true;
            }elseif(preg_match('/^[A-F0-9]{24}$/D',$code)){
                $q=$pdo->prepare('SELECT id FROM mfa_recovery_codes WHERE user_id=? AND code_hash=? AND used_at IS NULL FOR UPDATE');
                $q->execute([$id,hash('sha256',$code)]);$recoveryId=$q->fetchColumn();
                if($recoveryId){
                    $pdo->prepare('UPDATE mfa_recovery_codes SET used_at=NOW() WHERE id=?')->execute([(int)$recoveryId]);
                    $valid=true;
                }
            }
            if(!$valid){
                self::failedAttempt($pdo,$id);
                $pdo->commit();
                return false;
            }
            $pdo->prepare('DELETE FROM mfa_attempts WHERE user_id=?')->execute([$id]);
            \audit($id,'mfa.challenge_passed','user',$id);
            $pdo->commit();
            self::markSession($user);
            return true;
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
}
