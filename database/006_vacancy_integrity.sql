-- Phase 5B: required by current membership approval flow.
-- Run after 001–005, after backing up the database.
ALTER TABLE recruitment_positions
  ADD COLUMN academic_year VARCHAR(15) NOT NULL DEFAULT '2026-2027',
  ADD COLUMN semester VARCHAR(25) NOT NULL DEFAULT '1st';
ALTER TABLE recruitment_positions DROP INDEX uq_position;
ALTER TABLE recruitment_positions
  ADD UNIQUE KEY uq_position_term (committee,title,academic_year,semester);
ALTER TABLE membership_applications
  ADD COLUMN recruitment_position_id BIGINT UNSIGNED NULL,
  ADD INDEX idx_application_position_status (recruitment_position_id,status),
  ADD CONSTRAINT fk_application_position FOREIGN KEY (recruitment_position_id)
    REFERENCES recruitment_positions(id) ON DELETE RESTRICT;
CREATE TABLE IF NOT EXISTS notification_outbox (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NOT NULL,
 template_key ENUM('approved','rejected') NOT NULL,
 status ENUM('pending_review','cancelled') NOT NULL DEFAULT 'pending_review',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_outbox_application_template (application_id,template_key),
 FOREIGN KEY (application_id) REFERENCES membership_applications(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Outbox is pending review only: no automatic sending.
