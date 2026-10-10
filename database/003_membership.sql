-- Phase 3: migrate after 001 and 002; use an empty test deployment first.
ALTER TABLE membership_applications
 ADD COLUMN application_reference CHAR(24) NULL UNIQUE,
 ADD COLUMN program VARCHAR(30) NOT NULL DEFAULT 'BSIT OUS',
 ADD COLUMN requested_position VARCHAR(120) NULL,
 ADD COLUMN consent_at TIMESTAMP NULL,
 ADD COLUMN interview_at DATETIME NULL,
 ADD COLUMN decision_note VARCHAR(500) NULL;
CREATE UNIQUE INDEX uq_membership_period_email ON membership_applications (applicant_email, academic_year, semester);
CREATE TABLE IF NOT EXISTS membership_status_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NOT NULL,
 actor_id BIGINT UNSIGNED NOT NULL,
 old_status VARCHAR(32) NOT NULL,
 new_status VARCHAR(32) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(application_id) REFERENCES membership_applications(id),
 FOREIGN KEY(actor_id) REFERENCES users(id),
 INDEX idx_membership_events (application_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
