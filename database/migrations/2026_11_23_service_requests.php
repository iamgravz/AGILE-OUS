<?php

declare(strict_types=1);

// Service requests (Nov 23, 2026): a member asks the MSW Committee for something that is not a welfare concern (a
// record correction, a document, a question, an account problem), and the two sides reply in one thread.
return static function (PDO $db): void {
    $db->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS service_requests (
            request_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            type ENUM('record_update','document','inquiry','technical','other') NOT NULL,
            subject VARCHAR(150) NOT NULL,
            status ENUM('open','in_progress','resolved','closed','cancelled') NOT NULL DEFAULT 'open',
            assigned_to INT UNSIGNED NULL,
            resolved_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (request_id),
            KEY idx_service_requests_user_id (user_id),
            KEY idx_service_requests_status (status, created_at),
            KEY idx_service_requests_assigned_to (assigned_to),
            CONSTRAINT fk_service_requests_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
            CONSTRAINT fk_service_requests_assigned_to FOREIGN KEY (assigned_to) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    $db->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS service_request_messages (
            message_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            request_id INT UNSIGNED NOT NULL,
            author_id INT UNSIGNED NOT NULL,
            from_staff TINYINT(1) NOT NULL DEFAULT 0,
            body TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (message_id),
            KEY idx_service_request_messages_request (request_id, created_at),
            KEY idx_service_request_messages_author_id (author_id),
            CONSTRAINT fk_service_request_messages_request FOREIGN KEY (request_id) REFERENCES service_requests (request_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
            CONSTRAINT fk_service_request_messages_author FOREIGN KEY (author_id) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
};
