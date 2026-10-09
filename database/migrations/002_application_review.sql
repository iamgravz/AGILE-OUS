ALTER TABLE membership_applications
  ADD COLUMN interview_completed TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN documents_verified TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN assigned_to BIGINT UNSIGNED NULL,
  ADD CONSTRAINT fk_application_assignee FOREIGN KEY (assigned_to) REFERENCES users(id),
  ADD INDEX idx_application_assignee_status (assigned_to, status);

CREATE TABLE IF NOT EXISTS application_answers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  application_id BIGINT UNSIGNED NOT NULL,
  question_key VARCHAR(64) NOT NULL,
  answer_text TEXT NOT NULL,
  CONSTRAINT fk_answer_application FOREIGN KEY (application_id) REFERENCES membership_applications(id),
  UNIQUE KEY uq_application_answer (application_id, question_key)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS application_status_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  application_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  old_status VARCHAR(32) NOT NULL,
  new_status VARCHAR(32) NOT NULL,
  note VARCHAR(1000) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_status_application FOREIGN KEY (application_id) REFERENCES membership_applications(id),
  CONSTRAINT fk_status_actor FOREIGN KEY (actor_user_id) REFERENCES users(id),
  INDEX idx_history_app (application_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS application_verification_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  application_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  interview_completed TINYINT(1) NOT NULL,
  documents_verified TINYINT(1) NOT NULL,
  note VARCHAR(1000) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_verification_application FOREIGN KEY (application_id) REFERENCES membership_applications(id),
  CONSTRAINT fk_verification_actor FOREIGN KEY (actor_user_id) REFERENCES users(id),
  INDEX idx_verification_app (application_id, created_at)
) ENGINE=InnoDB;
