<?php
declare(strict_types=1);
namespace Agile;

final class Auth {
    public static function user(): ?array {
        \startSession();
        if (empty($_SESSION['uid'])) { return null; }
        $q = \db()->prepare('SELECT id, email, display_name, role, is_active FROM users WHERE id = ? LIMIT 1');
        $q->execute([(int) $_SESSION['uid']]);
        $user = $q->fetch();
        if (!$user || !$user['is_active']) { self::logout(); return null; }
        return $user;
    }
    public static function requireRole(array $roles): array {
        $user = self::user();
        if (!$user) { \redirect('/login'); }
        if (!in_array($user['role'], $roles, true)) { http_response_code(403); exit('Access denied.'); }
        if (Mfa::required($user) && !Mfa::sessionVerified($user)) {
            \redirect(Mfa::enrolled((int)$user['id']) ? '/mfa/challenge' : '/mfa/setup');
        }
        return $user;
    }
    public static function login(string $email, string $password): bool {
        $email = strtolower(trim($email));
        $q = \db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $q->execute([$email]);
        $user = $q->fetch();
        if (!$user || !$user['is_active'] || !password_verify($password, $user['password_hash'])) { return false; }
        \startSession();
        session_regenerate_id(true);
        // A fresh password login must never reuse step-up verification from
        // any earlier login, even if the same browser remains signed in.
        unset($_SESSION['mfa_ok_uid'],$_SESSION['mfa_ok_role'],
              $_SESSION['mfa_at'],$_SESSION['mfa_last_seen']);
        $_SESSION['uid'] = (int) $user['id'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        \audit((int)$user['id'], 'login', 'user', (int)$user['id']);
        return true;
    }
    public static function logout(): void {
        \startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time()-3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
