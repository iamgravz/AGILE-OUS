CREATE TABLE IF NOT EXISTS email_drafts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 author_id BIGINT UNSIGNED NOT NULL,
 recipient VARCHAR(190) NOT NULL,
 template_code VARCHAR(60) NOT NULL,
 subject VARCHAR(255) NOT NULL,
 message_text TEXT NOT NULL,
 status ENUM('draft','queued') NOT NULL DEFAULT 'draft',
 approved_by BIGINT UNSIGNED NULL,
 approved_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(author_id) REFERENCES users(id),
 FOREIGN KEY(approved_by) REFERENCES users(id),
 INDEX idx_email_drafts_status (status,created_at)
) ENGINE=InnoDB;
