-- Phase 7: provisional draft-bylaws review with independent member correction cases.
-- Does NOT authorize eligibility decisions or automatic role removal.
-- Confidential inputs stay separate from approved academic_verifications policy outcomes.
CREATE TABLE IF NOT EXISTS academic_provisional_previews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  verification_id BIGINT UNSIGNED NOT NULL,
  policy_version VARCHAR(80) NOT NULL,
  input_json JSON NOT NULL,
  outcome_json JSON NOT NULL,
  reviewed_by BIGINT UNSIGNED NOT NULL,
  reviewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_preview_verification FOREIGN KEY (verification_id) REFERENCES academic_verifications(id),
  CONSTRAINT fk_preview_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id),
  INDEX idx_preview_verification_date (verification_id,id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS academic_review_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  verification_id BIGINT UNSIGNED NOT NULL,
  member_id BIGINT UNSIGNED NOT NULL,
  submitted_by BIGINT UNSIGNED NOT NULL,
  request_type ENUM('correction','appeal','additional_information') NOT NULL,
  request_text TEXT NOT NULL,
  status ENUM('submitted','in_review','resolved','rejected') NOT NULL DEFAULT 'submitted',
  resolution_note TEXT NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_acad_request_verification FOREIGN KEY (verification_id) REFERENCES academic_verifications(id),
  CONSTRAINT fk_acad_request_member FOREIGN KEY (member_id) REFERENCES members(id),
  CONSTRAINT fk_acad_request_submitter FOREIGN KEY (submitted_by) REFERENCES users(id),
  CONSTRAINT fk_acad_request_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id),
  INDEX idx_acad_request_member (member_id,created_at),
  INDEX idx_acad_request_inbox (status,created_at),
  INDEX idx_acad_request_verification (verification_id,status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS academic_review_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  review_request_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  event_type ENUM('submitted','in_review','resolved','rejected') NOT NULL,
  event_note TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_acad_review_event_request FOREIGN KEY (review_request_id) REFERENCES academic_review_requests(id),
  CONSTRAINT fk_acad_review_event_actor FOREIGN KEY (actor_user_id) REFERENCES users(id),
  INDEX idx_acad_review_event_order (review_request_id,id)
) ENGINE=InnoDB;
