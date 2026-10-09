CREATE TABLE IF NOT EXISTS welfare_cases (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reference_code VARCHAR(30) NOT NULL UNIQUE,
 tracking_hash CHAR(64) NOT NULL,
 reporter_name VARCHAR(150) NOT NULL,
 reporter_email VARCHAR(190) NOT NULL,
 category ENUM('Academic','Financial & Resources','Well-being/Personal Support','Skills & Self-Improvement','Accessibility','Safety/Conduct','Community Concerns','Others') NOT NULL,
 summary VARCHAR(255) NOT NULL,
 details TEXT NOT NULL,
 priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
 status ENUM('submitted','triaged','in_progress','referred','resolved','closed') NOT NULL DEFAULT 'submitted',
 assigned_to BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (assigned_to) REFERENCES users(id),
 INDEX idx_welfare_work (status,priority,assigned_to)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS welfare_updates (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 case_id BIGINT UNSIGNED NOT NULL,
 actor_id BIGINT UNSIGNED NOT NULL,
 old_status VARCHAR(24) NOT NULL,
 new_status VARCHAR(24) NOT NULL,
 private_note TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (case_id) REFERENCES welfare_cases(id),
 FOREIGN KEY (actor_id) REFERENCES users(id),
 INDEX idx_welfare_history (case_id,created_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS welfare_followups (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 case_id BIGINT UNSIGNED NOT NULL,
 assigned_to BIGINT UNSIGNED NOT NULL,
 due_at DATETIME NOT NULL,
 note VARCHAR(1000) NOT NULL,
 completed_at DATETIME NULL,
 reminder_sent_at DATETIME NULL,
 FOREIGN KEY (case_id) REFERENCES welfare_cases(id),
 FOREIGN KEY (assigned_to) REFERENCES users(id),
 INDEX idx_welfare_due (due_at,completed_at,reminder_sent_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS welfare_referrals (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 case_id BIGINT UNSIGNED NOT NULL,
 referred_by BIGINT UNSIGNED NOT NULL,
 target_office VARCHAR(200) NOT NULL,
 referral_note TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (case_id) REFERENCES welfare_cases(id),
 FOREIGN KEY (referred_by) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS content_posts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 author_id BIGINT UNSIGNED NOT NULL,
 title VARCHAR(200) NOT NULL,
 body TEXT NOT NULL,
 content_type ENUM('news','event','publication','announcement') NOT NULL,
 status ENUM('draft','review','published','archived') NOT NULL DEFAULT 'draft',
 published_at DATETIME NULL,
 approved_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (author_id) REFERENCES users(id),
 FOREIGN KEY (approved_by) REFERENCES users(id),
 INDEX idx_published_content (status,content_type,published_at)
) ENGINE=InnoDB;
