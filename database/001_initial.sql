-- AGILE OUS phase 1 foundation. Run on an empty database.
CREATE TABLE IF NOT EXISTS users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 full_name VARCHAR(160) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('admin','president','membership_head','membership_member','welfare_head','welfare_member') NOT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS membership_applications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 applicant_name VARCHAR(160) NOT NULL,
 applicant_email VARCHAR(190) NOT NULL,
 status ENUM('pending','for_interview','approved','rejected','withdrawn') NOT NULL DEFAULT 'pending',
 academic_year VARCHAR(15) NOT NULL,
 semester VARCHAR(25) NOT NULL,
 reviewed_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_member_status (status),
 INDEX idx_member_period (academic_year,semester),
 CONSTRAINT fk_membership_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS welfare_cases (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 case_reference VARCHAR(32) NOT NULL UNIQUE,
 category ENUM('Academic','Financial & Resources','Well-being/Personal Support','Skills & Self-Improvement','Accessibility','Safety/Conduct','Community Concerns','Others') NOT NULL,
 status ENUM('submitted','under_review','referred','resolved','closed') NOT NULL DEFAULT 'submitted',
 created_by BIGINT UNSIGNED NULL,
 assigned_to BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_welfare_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_welfare_assignee FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_welfare_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Design note: Sensitive case narratives, attachments, consent, retention,
-- audit logs, recruitment workflows and semester validation require
-- dedicated reviewed migrations before accepting real applicant data.
