<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** Attempt counters in the rate_limits table. Keys are hashed, so they hold no email or IP address. */
final class RateLimiter
{
    /** Counts one attempt and returns true when the key is now over its limit. */
    public function hit(string $key, int $max, int $windowMinutes): bool
    {
        $now = date('Y-m-d H:i:s');
        $cutoff = date('Y-m-d H:i:s', time() - $windowMinutes * 60);

        // attempts is assigned first, so it still sees the old window_start.
        $statement = Database::connection()->prepare(
            'INSERT INTO rate_limits (rl_key, attempts, window_start) VALUES (?, 1, ?)
             ON DUPLICATE KEY UPDATE
                attempts = IF(window_start <= ?, 1, attempts + 1),
                window_start = IF(window_start <= ?, ?, window_start)'
        );
        $statement->execute([$this->storedKey($key), $now, $cutoff, $cutoff, $now]);

        return $this->tooMany($key, $max, $windowMinutes);
    }

    /** Section 11's default of 5 attempts in 15 minutes applies unless another limit is given. */
    public function tooMany(string $key, int $max = 5, int $windowMinutes = 15): bool
    {
        $statement = Database::connection()->prepare('SELECT attempts FROM rate_limits WHERE rl_key = ? AND window_start > ?');
        $statement->execute([$this->storedKey($key), date('Y-m-d H:i:s', time() - $windowMinutes * 60)]);

        return (int) $statement->fetchColumn() >= $max;
    }

    public function clear(string $key): void
    {
        Database::connection()->prepare('DELETE FROM rate_limits WHERE rl_key = ?')->execute([$this->storedKey($key)]);
    }

    private function storedKey(string $key): string
    {
        return hash('sha256', $key);
    }
}
