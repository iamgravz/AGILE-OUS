-- Phase 4 recruitment infrastructure. Apply after 003_membership.sql.
CREATE TABLE IF NOT EXISTS recruitment_positions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(120) NOT NULL,
 committee VARCHAR(120) NOT NULL,
 capacity SMALLINT UNSIGNED NOT NULL,
 enabled TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_position (title,committee)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS membership_interviews (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NOT NULL,
 scheduled_at DATETIME NOT NULL,
 format ENUM('online','in_person') NOT NULL,
 location_note VARCHAR(255) NOT NULL,
 scheduled_by BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_interview_application (application_id),
 FOREIGN KEY (application_id) REFERENCES membership_applications(id),
 FOREIGN KEY (scheduled_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS membership_evaluations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NOT NULL,
 evaluator_id BIGINT UNSIGNED NOT NULL,
 communication_score TINYINT UNSIGNED NOT NULL,
 motivation_score TINYINT UNSIGNED NOT NULL,
 skills_score TINYINT UNSIGNED NOT NULL,
 notes VARCHAR(500) NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_evaluator_application (application_id,evaluator_id),
 FOREIGN KEY (application_id) REFERENCES membership_applications(id),
 FOREIGN KEY (evaluator_id) REFERENCES users(id),
 CONSTRAINT chk_communication CHECK (communication_score BETWEEN 1 AND 5),
 CONSTRAINT chk_motivation CHECK (motivation_score BETWEEN 1 AND 5),
 CONSTRAINT chk_skills CHECK (skills_score BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
