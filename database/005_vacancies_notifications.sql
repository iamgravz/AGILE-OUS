-- Phase 5 vacancy keys and draft notifications. Backup before migrating.
ALTER TABLE recruitment_positions
 ADD COLUMN academic_year VARCHAR(15) NOT NULL DEFAULT '2026-2027',
 ADD COLUMN semester VARCHAR(25) NOT NULL DEFAULT '1st';
ALTER TABLE recruitment_positions DROP INDEX uq_position;
ALTER TABLE recruitment_positions ADD UNIQUE KEY uq_position_term (title,committee,academic_year,semester);
ALTER TABLE membership_applications ADD COLUMN recruitment_position_id BIGINT UNSIGNED NULL;
ALTER TABLE membership_applications ADD CONSTRAINT fk_application_position FOREIGN KEY(recruitment_position_id) REFERENCES recruitment_positions(id);
CREATE TABLE IF NOT EXISTS notification_outbox (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NOT NULL,
 template_key ENUM('interview_invite','approved','rejected') NOT NULL,
 status ENUM('draft','cancelled','sent') NOT NULL DEFAULT 'draft',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_notification_draft (application_id,template_key),
 FOREIGN KEY (application_id) REFERENCES membership_applications(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- No email provider or sender is configured. Rows are drafts only.
