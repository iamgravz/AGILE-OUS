-- Phase 5: queue messages for review; never send without explicit approval.
CREATE TABLE IF NOT EXISTS recruitment_notification_drafts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NOT NULL,
 kind ENUM('interview_invite','approved','rejected') NOT NULL,
 subject VARCHAR(190) NOT NULL,
 body TEXT NOT NULL,
 status ENUM('draft','approved','cancelled') NOT NULL DEFAULT 'draft',
 created_by BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (application_id) REFERENCES membership_applications(id),
 FOREIGN KEY (created_by) REFERENCES users(id),
 INDEX idx_notification_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
