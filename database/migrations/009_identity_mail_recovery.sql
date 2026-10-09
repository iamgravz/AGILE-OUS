-- Staff membership identity association and safe delivery recovery.
CREATE TABLE IF NOT EXISTS identity_link_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  member_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending','approved','rejected','cancelled','expired') NOT NULL DEFAULT 'pending',
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  reviewed_at DATETIME NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  review_note VARCHAR(1000) NULL,
  pending_member_id BIGINT UNSIGNED GENERATED ALWAYS AS
    (CASE WHEN status = 'pending' THEN member_id ELSE NULL END) STORED,
  pending_user_id BIGINT UNSIGNED GENERATED ALWAYS AS
    (CASE WHEN status = 'pending' THEN user_id ELSE NULL END) STORED,
  CONSTRAINT fk_identity_member FOREIGN KEY(member_id) REFERENCES members(id),
  CONSTRAINT fk_identity_user FOREIGN KEY(user_id) REFERENCES users(id),
  CONSTRAINT fk_identity_reviewer FOREIGN KEY(reviewed_by) REFERENCES users(id),
  UNIQUE KEY uq_pending_member (pending_member_id),
  UNIQUE KEY uq_pending_user (pending_user_id),
  INDEX idx_identity_queue (status,expires_at,requested_at)
) ENGINE=InnoDB;

ALTER TABLE notification_outbox
  MODIFY COLUMN status ENUM('queued','processing','submitted','failed','disabled','needs_review') NOT NULL DEFAULT 'queued',
  ADD COLUMN processing_started_at DATETIME NULL,
  ADD COLUMN last_attempt_at DATETIME NULL,
  ADD INDEX idx_outbox_processing (status,processing_started_at);

CREATE TABLE IF NOT EXISTS mail_delivery_reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  outbox_id BIGINT UNSIGNED NOT NULL,
  reviewer_id BIGINT UNSIGNED NOT NULL,
  previous_status VARCHAR(24) NOT NULL,
  decision ENUM('requeue','mark_failed') NOT NULL,
  reason VARCHAR(1000) NOT NULL,
  reviewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_delivery_review_outbox FOREIGN KEY(outbox_id) REFERENCES notification_outbox(id),
  CONSTRAINT fk_delivery_review_user FOREIGN KEY(reviewer_id) REFERENCES users(id),
  INDEX idx_mail_reviews (outbox_id,reviewed_at)
) ENGINE=InnoDB;
