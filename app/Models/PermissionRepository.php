<?php

declare(strict_types=1);

namespace App\Models;

final class PermissionRepository extends BaseRepository
{
    protected string $table = 'permissions';
    protected string $primaryKey = 'permission_id';

    /** @return list<array<string, mixed>> */
    public function allByCode(): array
    {
        return $this->pdo()->query('SELECT permission_id, code, description FROM permissions ORDER BY code')->fetchAll();
    }

    /** @return list<string> */
    public function codesForRole(int $roleId): array
    {
        $statement = $this->pdo()->prepare('SELECT p.code FROM role_permissions rp JOIN permissions p ON p.permission_id = rp.permission_id WHERE rp.role_id = ? ORDER BY p.code');
        $statement->execute([$roleId]);

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** @return list<string> */
    public function extraForUser(int $userId): array
    {
        $statement = $this->pdo()->prepare('SELECT p.code FROM user_permissions up JOIN permissions p ON p.permission_id = up.permission_id WHERE up.user_id = ? ORDER BY p.code');
        $statement->execute([$userId]);

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** @param list<string> $codes */
    public function setForRole(int $roleId, array $codes): void
    {
        $this->pdo()->prepare('DELETE FROM role_permissions WHERE role_id = ?')->execute([$roleId]);
        $insert = $this->pdo()->prepare('INSERT INTO role_permissions (role_id, permission_id) SELECT ?, permission_id FROM permissions WHERE code = ?');

        foreach ($codes as $code) {
            $insert->execute([$roleId, $code]);
        }
    }

    /** @param list<string> $codes */
    public function setExtraForUser(int $userId, array $codes): void
    {
        $this->pdo()->prepare('DELETE FROM user_permissions WHERE user_id = ?')->execute([$userId]);
        $insert = $this->pdo()->prepare('INSERT INTO user_permissions (user_id, permission_id) SELECT ?, permission_id FROM permissions WHERE code = ?');

        foreach ($codes as $code) {
            $insert->execute([$userId, $code]);
        }
    }

    /**
     * Codes from the user's role plus any extra grants to that user.
     *
     * @return list<string>
     */
    public function codesForUser(int $userId): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT p.code
             FROM users u
             JOIN role_permissions rp ON rp.role_id = u.role_id
             JOIN permissions p ON p.permission_id = rp.permission_id
             WHERE u.user_id = ?
             UNION
             SELECT p.code
             FROM user_permissions up
             JOIN permissions p ON p.permission_id = up.permission_id
             WHERE up.user_id = ?'
        );
        $statement->execute([$userId, $userId]);

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }
}
