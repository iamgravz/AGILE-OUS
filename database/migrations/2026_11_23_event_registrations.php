<?php

declare(strict_types=1);

// Event registration and attendance (Nov 23, 2026): members register for a published event, officers check them in.
// An event can close registration or cap its slots. Safe to run again.
return static function (PDO $db): void {
    $has = static fn (string $column): bool => (int) $db->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'content_posts' AND COLUMN_NAME = '{$column}'"
    )->fetchColumn() > 0;

    if (!$has('registration_open')) {
        $db->exec('ALTER TABLE content_posts ADD COLUMN registration_open TINYINT(1) NOT NULL DEFAULT 1 AFTER venue_or_link');
    }

    if (!$has('capacity')) {
        $db->exec('ALTER TABLE content_posts ADD COLUMN capacity SMALLINT UNSIGNED NULL AFTER registration_open');
    }

    $db->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS event_registrations (
            registration_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id INT UNSIGNED NOT NULL,
            member_id INT UNSIGNED NOT NULL,
            status ENUM('registered','cancelled') NOT NULL DEFAULT 'registered',
            registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            cancelled_at DATETIME NULL,
            attended_at DATETIME NULL,
            checked_in_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (registration_id),
            UNIQUE KEY uq_event_registrations_post_member (post_id, member_id),
            KEY idx_event_registrations_member_id (member_id),
            KEY idx_event_registrations_checked_in_by (checked_in_by),
            CONSTRAINT fk_event_registrations_post FOREIGN KEY (post_id) REFERENCES content_posts (post_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
            CONSTRAINT fk_event_registrations_member FOREIGN KEY (member_id) REFERENCES members (member_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
            CONSTRAINT fk_event_registrations_checked_in_by FOREIGN KEY (checked_in_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
};
