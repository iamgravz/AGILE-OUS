<?php

declare(strict_types=1);

// ID and certificate requests (Nov 23, 2026): members ask, and the MSW Committee Head uploads the file made from AGILE's
// own template, instead of the system drawing its own. Safe to run again.
return static function (PDO $db): void {
    $db->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS credential_requests (
            request_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            member_id INT UNSIGNED NOT NULL,
            type ENUM('digital_id','certificate') NOT NULL,
            note VARCHAR(255) NULL,
            status ENUM('requested','issued','declined','cancelled') NOT NULL DEFAULT 'requested',
            credential_id INT UNSIGNED NULL,
            remarks VARCHAR(255) NULL,
            handled_by INT UNSIGNED NULL,
            handled_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (request_id),
            KEY idx_credential_requests_member_id (member_id),
            KEY idx_credential_requests_status (status),
            KEY idx_credential_requests_credential_id (credential_id),
            KEY idx_credential_requests_handled_by (handled_by),
            CONSTRAINT fk_credential_requests_member FOREIGN KEY (member_id) REFERENCES members (member_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
            CONSTRAINT fk_credential_requests_credential FOREIGN KEY (credential_id) REFERENCES digital_credentials (credential_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
            CONSTRAINT fk_credential_requests_handled_by FOREIGN KEY (handled_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
};
