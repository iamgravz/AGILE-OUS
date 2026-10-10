-- Phase 7: Staff-managed welfare cases; never import real data before privacy approval.
ALTER TABLE welfare_cases
 ADD COLUMN encrypted_narrative MEDIUMTEXT NULL,
 ADD COLUMN priority ENUM('normal','high') NOT NULL DEFAULT 'normal';
CREATE TABLE IF NOT EXISTS welfare_case_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 case_id BIGINT UNSIGNED NOT NULL,
 actor_id BIGINT UNSIGNED NOT NULL,
 action ENUM('created','status_changed','assigned') NOT NULL,
 prior_status VARCHAR(32) NULL,
 next_status VARCHAR(32) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (case_id) REFERENCES welfare_cases(id) ON DELETE RESTRICT,
 FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE RESTRICT,
 INDEX idx_welfare_events_case (case_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
