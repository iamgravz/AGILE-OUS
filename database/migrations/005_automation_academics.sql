CREATE TABLE IF NOT EXISTS notification_outbox (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 event_key VARCHAR(160) NOT NULL UNIQUE,
 recipient VARCHAR(190) NOT NULL,
 subject VARCHAR(255) NOT NULL,
 body TEXT NOT NULL,
 status ENUM('queued','processing','submitted','failed','disabled') NOT NULL DEFAULT 'queued',
 attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 last_error VARCHAR(500) NULL,
 provider_reference VARCHAR(255) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 processed_at DATETIME NULL,
 INDEX idx_outbox_due (status,next_attempt_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS notifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 event_key VARCHAR(160) NOT NULL,
 title VARCHAR(255) NOT NULL,
 body VARCHAR(1000) NOT NULL,
 read_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(id),
 UNIQUE KEY uq_personal_notification (user_id,event_key)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS academic_terms (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 label VARCHAR(100) NOT NULL UNIQUE,
 starts_on DATE NOT NULL,
 ends_on DATE NOT NULL,
 deadline_on DATE NOT NULL,
 state ENUM('planned','active','closed') NOT NULL DEFAULT 'planned'
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS eligibility_policies (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 policy_version VARCHAR(80) NOT NULL UNIQUE,
 criteria_json JSON NOT NULL,
 is_approved BOOLEAN NOT NULL DEFAULT FALSE,
 approved_by BIGINT UNSIGNED NULL,
 approved_at DATETIME NULL,
 FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS academic_verifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 term_id BIGINT UNSIGNED NOT NULL,
 member_id BIGINT UNSIGNED NOT NULL,
 policy_id BIGINT UNSIGNED NULL,
 declared_grades_json JSON NULL,
 automated_flag ENUM('not_checked','no_flags','review_required') NOT NULL DEFAULT 'not_checked',
 verified_result ENUM('pending','eligible','ineligible','needs_more_information') NOT NULL DEFAULT 'pending',
 reviewer_id BIGINT UNSIGNED NULL,
 reviewer_note VARCHAR(1000) NULL,
 reviewed_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (term_id) REFERENCES academic_terms(id),
 FOREIGN KEY (member_id) REFERENCES members(id),
 FOREIGN KEY (policy_id) REFERENCES eligibility_policies(id),
 FOREIGN KEY (reviewer_id) REFERENCES users(id),
 UNIQUE KEY uq_term_member (term_id,member_id),
 INDEX idx_academic_pending (term_id,verified_result)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS automation_runs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 job_key VARCHAR(160) NOT NULL UNIQUE,
 job_type VARCHAR(80) NOT NULL,
 status ENUM('started','completed','failed') NOT NULL,
 result_summary VARCHAR(1000) NULL,
 executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS private_attachments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 storage_key VARCHAR(80) NOT NULL UNIQUE,
 owner_type ENUM('membership_application','welfare_case','academic_verification') NOT NULL,
 owner_id BIGINT UNSIGNED NOT NULL,
 original_name VARCHAR(255) NOT NULL,
 mime_type VARCHAR(100) NOT NULL,
 byte_size INT UNSIGNED NOT NULL,
 uploaded_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (uploaded_by) REFERENCES users(id),
 INDEX idx_attachment_owner (owner_type,owner_id)
) ENGINE=InnoDB;
