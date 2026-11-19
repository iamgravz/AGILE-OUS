<?php

declare(strict_types=1);

namespace App\Models;

final class SettingRepository extends BaseRepository
{
    protected string $table = 'settings';
    protected string $primaryKey = 'setting_key';

    public function set(string $key, string $value, int $userId): void
    {
        $this->pdo()->prepare(
            'INSERT INTO settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)'
        )->execute([$key, $value, $userId]);
    }

    public function value(string $key, ?string $default = null): ?string
    {
        return $this->findBy(['setting_key' => $key])[0]['setting_value'] ?? $default;
    }
}
