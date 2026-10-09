-- Recruitment and membership expansion. This is an additive schema migration.
ALTER TABLE users MODIFY COLUMN role ENUM(
 'msw_head','msw_member','president','admin','committee_head','deputy_head','executive_officer','source_editor'
) NOT NULL;

CREATE TABLE IF NOT EXISTS hr_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 requested_by BIGINT UNSIGNED NOT NULL,
 committee_name VARCHAR(140) NOT NULL,
 role_category VARCHAR(80) NOT NULL,
 position_title VARCHAR(140) NOT NULL,
 requested_slots SMALLINT UNSIGNED NOT NULL,
 reason TEXT NOT NULL,
 status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
 decided_by BIGINT UNSIGNED NULL,
 decision_note VARCHAR(1000) NULL,
 requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 decided_at DATETIME NULL,
 FOREIGN KEY (requested_by) REFERENCES users(id),
 FOREIGN KEY (decided_by) REFERENCES users(id),
 INDEX idx_hr_status (status,requested_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vacancies (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 hr_request_id BIGINT UNSIGNED NOT NULL UNIQUE,
 committee_name VARCHAR(140) NOT NULL,
 role_category VARCHAR(80) NOT NULL,
 position_title VARCHAR(140) NOT NULL,
 capacity SMALLINT UNSIGNED NOT NULL,
 filled SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 status ENUM('published','closed') NOT NULL DEFAULT 'published',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (hr_request_id) REFERENCES hr_requests(id),
 INDEX idx_vacancies_public (status,role_category),
 CONSTRAINT chk_vacancy_capacity CHECK (filled <= capacity)
) ENGINE=InnoDB;

ALTER TABLE membership_applications
 ADD COLUMN vacancy_id BIGINT UNSIGNED NULL,
 ADD CONSTRAINT fk_application_vacancy FOREIGN KEY (vacancy_id) REFERENCES vacancies(id),
 ADD INDEX idx_app_vacancy_status (vacancy_id,status);

CREATE TABLE IF NOT EXISTS interviews (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NOT NULL,
 interviewer_id BIGINT UNSIGNED NOT NULL,
 starts_at DATETIME NOT NULL,
 meeting_details VARCHAR(500) NOT NULL,
 status ENUM('scheduled','completed','cancelled','no_show') NOT NULL DEFAULT 'scheduled',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (application_id) REFERENCES membership_applications(id),
 FOREIGN KEY (interviewer_id) REFERENCES users(id),
 INDEX idx_interview_application (application_id,starts_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS evaluations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NOT NULL,
 evaluator_id BIGINT UNSIGNED NOT NULL,
 score TINYINT UNSIGNED NOT NULL,
 recommendation ENUM('recommend','hold','not_recommend') NOT NULL,
 notes TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (application_id) REFERENCES membership_applications(id),
 FOREIGN KEY (evaluator_id) REFERENCES users(id),
 INDEX idx_eval_app (application_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS members (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NOT NULL UNIQUE,
 student_number VARCHAR(32) NOT NULL,
 full_name VARCHAR(150) NOT NULL,
 email VARCHAR(190) NOT NULL,
 membership_status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 membership_type ENUM('general','appointed') NOT NULL DEFAULT 'general',
 verification_token_hash CHAR(64) NOT NULL UNIQUE,
 valid_until DATE NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (application_id) REFERENCES membership_applications(id),
 INDEX idx_member_student (student_number)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS role_assignments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 member_id BIGINT UNSIGNED NOT NULL,
 vacancy_id BIGINT UNSIGNED NULL,
 role_category VARCHAR(80) NOT NULL,
 position_title VARCHAR(140) NOT NULL,
 starts_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 ends_at DATETIME NULL,
 reason VARCHAR(1000) NULL,
 recorded_by BIGINT UNSIGNED NOT NULL,
 FOREIGN KEY (member_id) REFERENCES members(id),
 FOREIGN KEY (vacancy_id) REFERENCES vacancies(id),
 FOREIGN KEY (recorded_by) REFERENCES users(id),
 INDEX idx_active_role (member_id,ends_at)
) ENGINE=InnoDB;
