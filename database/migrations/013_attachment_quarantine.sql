-- Phase 9: all existing private documents are quarantined until re-scanned.
-- No previous file is silently trusted or exposed.
ALTER TABLE private_attachments
    ADD COLUMN scan_status ENUM('quarantined','scanning','clean','infected','scan_error')
       NOT NULL DEFAULT 'quarantined',
    ADD COLUMN content_sha256 CHAR(64) NULL,
    ADD COLUMN scan_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN scan_started_at DATETIME NULL,
    ADD COLUMN scanned_at DATETIME NULL,
    ADD COLUMN scan_note VARCHAR(255) NULL,
    ADD INDEX idx_private_scan_queue (scan_status,scan_attempts,created_at);

CREATE TABLE IF NOT EXISTS security_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_key VARCHAR(100) NOT NULL,
    severity ENUM('info','warning','critical') NOT NULL,
    resource_type VARCHAR(60) NOT NULL,
    resource_id BIGINT UNSIGNED NULL,
    details VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_security_events_recent (created_at,severity),
    INDEX idx_security_event_type (event_key,created_at)
) ENGINE=InnoDB;
