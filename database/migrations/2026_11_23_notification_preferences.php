<?php

declare(strict_types=1);

// Notification preferences (Nov 23, 2026): which optional notices a person gets, by channel. No row means all on.
return static function (PDO $db): void {
    $db->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS notification_preferences (
            user_id INT UNSIGNED NOT NULL,
            category VARCHAR(30) NOT NULL,
            email TINYINT(1) NOT NULL DEFAULT 1,
            in_app TINYINT(1) NOT NULL DEFAULT 1,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, category),
            CONSTRAINT fk_notification_preferences_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
};
