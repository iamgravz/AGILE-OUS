<?php

declare(strict_types=1);

// Document version history (Nov 23, 2026): a re-uploaded application document keeps its earlier files, each with the
// review it had (needs correction and why), instead of deleting them. Safe to run again.
return static function (PDO $db): void {
    $has = (int) $db->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'application_documents' AND COLUMN_NAME = 'version_no'"
    )->fetchColumn();

    if ($has === 0) {
        $db->exec('ALTER TABLE application_documents ADD COLUMN version_no SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER size_bytes');
    }

    $db->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS document_versions (
            version_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            document_id INT UNSIGNED NOT NULL,
            version_no SMALLINT UNSIGNED NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            stored_path VARCHAR(255) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            size_bytes INT UNSIGNED NOT NULL,
            verification_status ENUM('pending','verified','needs_correction','rejected') NOT NULL,
            verification_remarks TEXT NULL,
            uploaded_at DATETIME NOT NULL,
            replaced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (version_id),
            UNIQUE KEY uq_document_versions_document_version (document_id, version_no),
            CONSTRAINT fk_document_versions_document FOREIGN KEY (document_id) REFERENCES application_documents (document_id) ON DELETE RESTRICT ON UPDATE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
};
