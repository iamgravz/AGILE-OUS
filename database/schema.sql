-- AGILE Membership and Student Welfare Management System
-- MySQL 8 / InnoDB / utf8mb4_unicode_ci. Tables are in foreign-key-safe order and follow PROJECT_CONTEXT.md section 9.
-- Foreign keys are RESTRICT, except the two pure join tables (role_permissions, user_permissions), which CASCADE.
-- Encrypted columns hold AES-256-GCM ciphertext encoded as base64.

-- ---------------------------------------------------------------------------
-- Access control and administration
-- ---------------------------------------------------------------------------

-- Which migration files have run (setup.php). A file runs once; schema.sql already holds the result of every one.
CREATE TABLE IF NOT EXISTS schema_migrations (
    filename VARCHAR(190) NOT NULL,
    ran_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roles (
    role_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(40) NOT NULL,
    role_name VARCHAR(50) NOT NULL,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id),
    UNIQUE KEY uq_roles_slug (slug),
    UNIQUE KEY uq_roles_role_name (role_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    permission_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (permission_id),
    UNIQUE KEY uq_permissions_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id, permission_id),
    KEY idx_role_permissions_permission_id (permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles (role_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions (permission_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    user_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_id INT UNSIGNED NOT NULL,
    email VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    student_no VARCHAR(20) NULL,
    contact_no VARCHAR(20) NOT NULL,
    year_level VARCHAR(20) NULL,
    section VARCHAR(30) NULL,
    photo_path VARCHAR(255) NULL,
    status ENUM('pending','active','locked','disabled') NOT NULL DEFAULT 'pending',
    email_verified_at DATETIME NULL,
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    rejection_remarks TEXT NULL,
    failed_logins TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_student_no (student_no),
    KEY idx_users_role_id (role_id),
    KEY idx_users_status (status),
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (role_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_users_approved_by FOREIGN KEY (approved_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_permissions (
    user_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, permission_id),
    KEY idx_user_permissions_permission_id (permission_id),
    CONSTRAINT fk_user_permissions_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_user_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions (permission_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_tokens (
    token_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    type ENUM('account_setup','password_reset') NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (token_id),
    KEY idx_user_tokens_user_id (user_id),
    KEY idx_user_tokens_token_hash (token_hash),
    CONSTRAINT fk_user_tokens_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
    rl_key VARCHAR(120) NOT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    window_start DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (rl_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS committees (
    committee_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    head_user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (committee_id),
    UNIQUE KEY uq_committees_name (name),
    KEY idx_committees_head_user_id (head_user_id),
    CONSTRAINT fk_committees_head_user FOREIGN KEY (head_user_id) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS positions (
    position_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    committee_id INT UNSIGNED NULL,
    committee_key INT UNSIGNED AS (IFNULL(committee_id, 0)) STORED,
    title VARCHAR(100) NOT NULL,
    role_type ENUM('officer','committee_head','deputy','committee_member','source_code','general') NOT NULL,
    -- Open for application on the first apply page, and how many people the position should have (addition, Nov 22, 2026).
    is_open TINYINT(1) NOT NULL DEFAULT 0,
    target_slots SMALLINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (position_id),
    UNIQUE KEY uq_positions_committee_title (committee_key, title),
    KEY idx_positions_committee_id (committee_id),
    CONSTRAINT fk_positions_committee FOREIGN KEY (committee_id) REFERENCES committees (committee_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    action VARCHAR(60) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id INT UNSIGNED NULL,
    details_json JSON NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (log_id),
    KEY idx_audit_logs_user_id (user_id),
    KEY idx_audit_logs_entity (entity_type, entity_id),
    KEY idx_audit_logs_created_at (created_at),
    CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(80) NOT NULL,
    setting_value TEXT NOT NULL,
    updated_by INT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key),
    KEY idx_settings_updated_by (updated_by),
    CONSTRAINT fk_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Membership and recruitment
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS applicants (
    applicant_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(150) NOT NULL,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    student_no VARCHAR(20) NULL,
    contact_no VARCHAR(20) NULL,
    year_level VARCHAR(30) NULL,
    -- Asked on the first apply page with the year level (addition, Nov 22, 2026).
    section VARCHAR(30) NULL,
    email_verified_at DATETIME NULL,
    user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (applicant_id),
    UNIQUE KEY uq_applicants_email (email),
    UNIQUE KEY uq_applicants_student_no (student_no),
    KEY idx_applicants_user_id (user_id),
    CONSTRAINT fk_applicants_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS members (
    member_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    applicant_id INT UNSIGNED NULL,
    position_id INT UNSIGNED NULL,
    member_no VARCHAR(20) NOT NULL,
    verify_token CHAR(64) NULL,
    membership_type ENUM('general','officer') NOT NULL,
    status ENUM('active') NOT NULL DEFAULT 'active',
    role_status ENUM('active','expired') NULL,
    role_expired_at DATETIME NULL,
    joined_at DATE NOT NULL,
    registered_at DATETIME NULL,
    registration_reminders TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (member_id),
    UNIQUE KEY uq_members_user_id (user_id),
    UNIQUE KEY uq_members_applicant_id (applicant_id),
    UNIQUE KEY uq_members_member_no (member_no),
    UNIQUE KEY uq_members_verify_token (verify_token),
    KEY idx_members_position_id (position_id),
    KEY idx_members_status (status),
    KEY idx_members_role_status (role_status),
    CONSTRAINT fk_members_applicant FOREIGN KEY (applicant_id) REFERENCES applicants (applicant_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_members_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_members_position FOREIGN KEY (position_id) REFERENCES positions (position_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS membership_status_history (
    history_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    member_id INT UNSIGNED NOT NULL,
    old_role VARCHAR(60) NULL,
    new_role VARCHAR(60) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    changed_by INT UNSIGNED NOT NULL,
    changed_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (history_id),
    KEY idx_membership_status_history_member_id (member_id),
    KEY idx_membership_status_history_changed_by (changed_by),
    CONSTRAINT fk_membership_status_history_member FOREIGN KEY (member_id) REFERENCES members (member_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_membership_status_history_changed_by FOREIGN KEY (changed_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS digital_credentials (
    credential_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    member_id INT UNSIGNED NOT NULL,
    type ENUM('digital_id','certificate') NOT NULL,
    serial_no VARCHAR(30) NOT NULL,
    qr_token CHAR(64) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    status ENUM('pending_approval','approved','returned') NOT NULL DEFAULT 'pending_approval',
    reason ENUM('new_member','role_change','reissue') NOT NULL DEFAULT 'new_member',
    issued_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    review_remarks TEXT NULL,
    replaces_credential_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (credential_id),
    UNIQUE KEY uq_digital_credentials_serial_no (serial_no),
    UNIQUE KEY uq_digital_credentials_qr_token (qr_token),
    KEY idx_digital_credentials_member_id (member_id),
    KEY idx_digital_credentials_status (status),
    CONSTRAINT fk_digital_credentials_approved_by FOREIGN KEY (approved_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_digital_credentials_replaces FOREIGN KEY (replaces_credential_id) REFERENCES digital_credentials (credential_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_digital_credentials_member FOREIGN KEY (member_id) REFERENCES members (member_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_requests (
    request_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    committee_id INT UNSIGNED NOT NULL,
    position_id INT UNSIGNED NOT NULL,
    requested_by INT UNSIGNED NOT NULL,
    slots TINYINT UNSIGNED NOT NULL,
    justification TEXT NOT NULL,
    qualifications TEXT NOT NULL,
    status ENUM('submitted','approved','returned','cancelled') NOT NULL DEFAULT 'submitted',
    remarks TEXT NULL,
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (request_id),
    KEY idx_hr_requests_committee_id (committee_id),
    KEY idx_hr_requests_position_id (position_id),
    KEY idx_hr_requests_requested_by (requested_by),
    KEY idx_hr_requests_approved_by (approved_by),
    KEY idx_hr_requests_status (status),
    CONSTRAINT chk_hr_requests_slots CHECK (slots > 0),
    CONSTRAINT fk_hr_requests_committee FOREIGN KEY (committee_id) REFERENCES committees (committee_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_hr_requests_position FOREIGN KEY (position_id) REFERENCES positions (position_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_hr_requests_requested_by FOREIGN KEY (requested_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_hr_requests_approved_by FOREIGN KEY (approved_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vacancies (
    vacancy_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id INT UNSIGNED NOT NULL,
    position_id INT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    qualifications TEXT NULL,
    slots TINYINT UNSIGNED NOT NULL,
    slots_filled TINYINT UNSIGNED NOT NULL DEFAULT 0,
    opens_at DATETIME NOT NULL,
    closes_at DATETIME NOT NULL,
    status ENUM('draft','open','closed','filled','cancelled') NOT NULL DEFAULT 'draft',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (vacancy_id),
    KEY idx_vacancies_request_id (request_id),
    KEY idx_vacancies_position_id (position_id),
    KEY idx_vacancies_status_closes_at (status, closes_at),
    KEY idx_vacancies_closes_at (closes_at),
    CONSTRAINT chk_vacancies_slots CHECK (slots > 0),
    CONSTRAINT chk_vacancies_slots_filled CHECK (slots_filled <= slots),
    CONSTRAINT chk_vacancies_dates CHECK (closes_at > opens_at),
    CONSTRAINT fk_vacancies_request FOREIGN KEY (request_id) REFERENCES hr_requests (request_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_vacancies_position FOREIGN KEY (position_id) REFERENCES positions (position_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS membership_applications (
    application_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    applicant_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    vacancy_id INT UNSIGNED NULL,
    -- The open position chosen on the first apply page, without a vacancy (addition, Nov 22, 2026).
    position_id INT UNSIGNED NULL,
    tracking_code CHAR(12) NOT NULL,
    status ENUM('draft','submitted','under_review','for_revision','waitlisted','approved','rejected','withdrawn') NOT NULL DEFAULT 'draft',
    wizard_step TINYINT UNSIGNED NOT NULL DEFAULT 1,
    decision_remarks TEXT NULL,
    decided_by INT UNSIGNED NULL,
    decided_at DATETIME NULL,
    consent_at DATETIME NULL,
    enrollment_confirmed_at DATETIME NULL,
    submitted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (application_id),
    UNIQUE KEY uq_membership_applications_tracking_code (tracking_code),
    KEY idx_membership_applications_applicant_id (applicant_id),
    KEY idx_membership_applications_user_id (user_id),
    KEY idx_membership_applications_vacancy_id (vacancy_id),
    KEY idx_membership_applications_position_id (position_id),
    KEY idx_membership_applications_decided_by (decided_by),
    KEY idx_membership_applications_status (status),
    KEY idx_membership_applications_created_at (created_at),
    CONSTRAINT chk_membership_applications_owner CHECK ((applicant_id IS NULL) <> (user_id IS NULL)),
    CONSTRAINT chk_membership_applications_one_choice CHECK (vacancy_id IS NULL OR position_id IS NULL),
    CONSTRAINT fk_membership_applications_position FOREIGN KEY (position_id) REFERENCES positions (position_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_membership_applications_applicant FOREIGN KEY (applicant_id) REFERENCES applicants (applicant_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_membership_applications_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_membership_applications_vacancy FOREIGN KEY (vacancy_id) REFERENCES vacancies (vacancy_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_membership_applications_decided_by FOREIGN KEY (decided_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS application_access_links (
    link_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    purpose ENUM('resume_draft','revise','withdraw') NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (link_id),
    KEY idx_application_access_links_application_id (application_id),
    KEY idx_application_access_links_token_hash (token_hash),
    CONSTRAINT fk_application_access_links_application FOREIGN KEY (application_id) REFERENCES membership_applications (application_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS application_status_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id INT UNSIGNED NOT NULL,
    old_status VARCHAR(20) NULL,
    new_status VARCHAR(20) NOT NULL,
    remarks TEXT NULL,
    changed_by INT UNSIGNED NULL,
    changed_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_application_status_history_application_id (application_id),
    KEY idx_application_status_history_changed_by (changed_by),
    KEY idx_application_status_history_new_status (new_status),
    CONSTRAINT fk_application_status_history_application FOREIGN KEY (application_id) REFERENCES membership_applications (application_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_application_status_history_changed_by FOREIGN KEY (changed_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS application_questions (
    question_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_type VARCHAR(30) NOT NULL,
    input_type ENUM('text','textarea','select','checkbox','file') NOT NULL,
    options_json JSON NULL,
    question_text VARCHAR(255) NOT NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (question_id),
    KEY idx_application_questions_role_type (role_type, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS application_answers (
    answer_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    answer_text TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (answer_id),
    KEY idx_application_answers_application_id (application_id),
    KEY idx_application_answers_question_id (question_id),
    CONSTRAINT fk_application_answers_application FOREIGN KEY (application_id) REFERENCES membership_applications (application_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_application_answers_question FOREIGN KEY (question_id) REFERENCES application_questions (question_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS application_documents (
    document_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id INT UNSIGNED NOT NULL,
    doc_type VARCHAR(50) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    size_bytes INT UNSIGNED NOT NULL,
    verification_status ENUM('pending','verified','needs_correction','rejected') NOT NULL DEFAULT 'pending',
    verification_remarks TEXT NULL,
    verified_by INT UNSIGNED NULL,
    verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (document_id),
    KEY idx_application_documents_application_id (application_id),
    KEY idx_application_documents_verified_by (verified_by),
    KEY idx_application_documents_verification_status (verification_status),
    CONSTRAINT fk_application_documents_application FOREIGN KEY (application_id) REFERENCES membership_applications (application_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_application_documents_verified_by FOREIGN KEY (verified_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_extractions (
    extraction_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_type ENUM('application_document','eligibility_screening') NOT NULL,
    source_id INT UNSIGNED NOT NULL,
    extracted_json JSON NOT NULL,
    confidence DECIMAL(4,3) NULL,
    status ENUM('suggested','confirmed','discarded') NOT NULL DEFAULT 'suggested',
    verified_by INT UNSIGNED NULL,
    verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (extraction_id),
    KEY idx_ai_extractions_source (source_type, source_id),
    KEY idx_ai_extractions_verified_by (verified_by),
    KEY idx_ai_extractions_status (status),
    CONSTRAINT fk_ai_extractions_verified_by FOREIGN KEY (verified_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evaluations (
    evaluation_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id INT UNSIGNED NOT NULL,
    evaluator_id INT UNSIGNED NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    rubric_json JSON NOT NULL,
    recommendation ENUM('recommend','hold','not_recommend') NOT NULL,
    remarks TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (evaluation_id),
    UNIQUE KEY uq_evaluations_application_evaluator (application_id, evaluator_id),
    KEY idx_evaluations_evaluator_id (evaluator_id),
    CONSTRAINT chk_evaluations_score CHECK (score BETWEEN 0 AND 100),
    CONSTRAINT fk_evaluations_application FOREIGN KEY (application_id) REFERENCES membership_applications (application_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_evaluations_evaluator FOREIGN KEY (evaluator_id) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS interviews (
    interview_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id INT UNSIGNED NOT NULL,
    interviewer_id INT UNSIGNED NOT NULL,
    scheduled_at DATETIME NOT NULL,
    mode ENUM('online','onsite') NOT NULL,
    location VARCHAR(255) NOT NULL,
    result ENUM('pending','passed','failed','no_show') NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    reminder_sent_at DATETIME NULL,
    identity_verified TINYINT(1) NOT NULL DEFAULT 0,
    role_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    privacy_reminder_given TINYINT(1) NOT NULL DEFAULT 0,
    conflict_of_interest TINYINT(1) NULL,
    checklist_by INT UNSIGNED NULL,
    checklist_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (interview_id),
    KEY idx_interviews_application_id (application_id),
    KEY idx_interviews_interviewer_id (interviewer_id),
    KEY idx_interviews_scheduled_at (scheduled_at),
    KEY idx_interviews_result (result),
    KEY idx_interviews_checklist_by (checklist_by),
    CONSTRAINT chk_interviews_checklist CHECK (checklist_at IS NULL OR (identity_verified = 1 AND role_confirmed = 1 AND privacy_reminder_given = 1 AND conflict_of_interest IS NOT NULL AND checklist_by IS NOT NULL)),
    CONSTRAINT fk_interviews_checklist_by FOREIGN KEY (checklist_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_interviews_application FOREIGN KEY (application_id) REFERENCES membership_applications (application_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_interviews_interviewer FOREIGN KEY (interviewer_id) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS appointments (
    appointment_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id INT UNSIGNED NOT NULL,
    member_id INT UNSIGNED NULL,
    position_id INT UNSIGNED NOT NULL,
    status ENUM('prepared','approved','returned') NOT NULL DEFAULT 'prepared',
    prepared_by INT UNSIGNED NOT NULL,
    appointed_by INT UNSIGNED NULL,
    remarks TEXT NULL,
    effective_date DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (appointment_id),
    KEY idx_appointments_application_id (application_id),
    KEY idx_appointments_member_id (member_id),
    KEY idx_appointments_position_id (position_id),
    KEY idx_appointments_prepared_by (prepared_by),
    KEY idx_appointments_appointed_by (appointed_by),
    KEY idx_appointments_status (status),
    CONSTRAINT fk_appointments_application FOREIGN KEY (application_id) REFERENCES membership_applications (application_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_appointments_member FOREIGN KEY (member_id) REFERENCES members (member_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_appointments_position FOREIGN KEY (position_id) REFERENCES positions (position_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_appointments_prepared_by FOREIGN KEY (prepared_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_appointments_appointed_by FOREIGN KEY (appointed_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Welfare (description, note, actions_taken and summary are encrypted)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS welfare_categories (
    category_id TINYINT UNSIGNED NOT NULL,
    name VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (category_id),
    UNIQUE KEY uq_welfare_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS guest_verifications (
    verification_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(150) NOT NULL,
    code_hash CHAR(64) NOT NULL,
    purpose ENUM('welfare_submit','application_submit','member_registration') NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (verification_id),
    KEY idx_guest_verifications_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS welfare_cases (
    case_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    case_code CHAR(12) NOT NULL,
    user_id INT UNSIGNED NULL,
    guest_name TEXT NULL,
    guest_email TEXT NULL,
    guest_email_hash CHAR(64) NULL,
    guest_student_no TEXT NULL,
    submitter_contact_no TEXT NULL,
    submitter_year_level TEXT NULL,
    submitter_section TEXT NULL,
    category_id TINYINT UNSIGNED NOT NULL,
    priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    student_marked_urgent TINYINT(1) NOT NULL DEFAULT 0,
    subject TEXT NOT NULL,
    description TEXT NOT NULL,
    assigned_to INT UNSIGNED NULL,
    related_case_id INT UNSIGNED NULL,
    status ENUM('submitted','in_progress','referred','resolved','closed') NOT NULL DEFAULT 'submitted',
    acknowledged_at DATETIME NULL,
    closing_remarks TEXT NULL,
    closed_by INT UNSIGNED NULL,
    submitted_at DATETIME NOT NULL,
    closed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (case_id),
    UNIQUE KEY uq_welfare_cases_case_code (case_code),
    KEY idx_welfare_cases_user_id (user_id),
    KEY idx_welfare_cases_category_id (category_id),
    KEY idx_welfare_cases_assigned_to (assigned_to),
    KEY idx_welfare_cases_related_case_id (related_case_id),
    KEY idx_welfare_cases_closed_by (closed_by),
    KEY idx_welfare_cases_status_priority (status, priority),
    KEY idx_welfare_cases_priority (priority),
    KEY idx_welfare_cases_guest_email_hash (guest_email_hash),
    KEY idx_welfare_cases_created_at (created_at),
    CONSTRAINT chk_welfare_cases_owner CHECK (user_id IS NOT NULL OR guest_email IS NOT NULL),
    CONSTRAINT chk_welfare_cases_guest_hash CHECK ((guest_email IS NULL) = (guest_email_hash IS NULL)),
    CONSTRAINT fk_welfare_cases_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_welfare_cases_category FOREIGN KEY (category_id) REFERENCES welfare_categories (category_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_welfare_cases_assigned_to FOREIGN KEY (assigned_to) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_welfare_cases_related_case FOREIGN KEY (related_case_id) REFERENCES welfare_cases (case_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_welfare_cases_closed_by FOREIGN KEY (closed_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS case_notes (
    note_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    case_id INT UNSIGNED NOT NULL,
    author_id INT UNSIGNED NOT NULL,
    note TEXT NOT NULL,
    visible_to_student TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (note_id),
    KEY idx_case_notes_case_id (case_id),
    KEY idx_case_notes_author_id (author_id),
    CONSTRAINT fk_case_notes_case FOREIGN KEY (case_id) REFERENCES welfare_cases (case_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_case_notes_author FOREIGN KEY (author_id) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS referrals (
    referral_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    case_id INT UNSIGNED NOT NULL,
    referred_to TEXT NOT NULL,
    remarks TEXT NOT NULL,
    status ENUM('pending','accepted','completed','declined') NOT NULL DEFAULT 'pending',
    referred_by INT UNSIGNED NOT NULL,
    referred_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (referral_id),
    KEY idx_referrals_case_id (case_id),
    KEY idx_referrals_referred_by (referred_by),
    KEY idx_referrals_status (status),
    CONSTRAINT fk_referrals_case FOREIGN KEY (case_id) REFERENCES welfare_cases (case_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_referrals_referred_by FOREIGN KEY (referred_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS case_followups (
    followup_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    case_id INT UNSIGNED NOT NULL,
    due_at DATETIME NOT NULL,
    handler_id INT UNSIGNED NOT NULL,
    done_at DATETIME NULL,
    remarks TEXT NULL,
    reminder_sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (followup_id),
    KEY idx_case_followups_case_id (case_id),
    KEY idx_case_followups_handler_id (handler_id),
    KEY idx_case_followups_due_at (due_at),
    CONSTRAINT fk_case_followups_case FOREIGN KEY (case_id) REFERENCES welfare_cases (case_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_case_followups_handler FOREIGN KEY (handler_id) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS case_resolutions (
    resolution_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    case_id INT UNSIGNED NOT NULL,
    actions_taken TEXT NOT NULL,
    outcome ENUM('resolved','partially_resolved','referred_out','withdrawn') NOT NULL,
    summary TEXT NOT NULL,
    resolved_by INT UNSIGNED NOT NULL,
    resolved_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (resolution_id),
    UNIQUE KEY uq_case_resolutions_case_id (case_id),
    KEY idx_case_resolutions_resolved_by (resolved_by),
    CONSTRAINT fk_case_resolutions_case FOREIGN KEY (case_id) REFERENCES welfare_cases (case_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_case_resolutions_resolved_by FOREIGN KEY (resolved_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- actor_id is NULL when a guest or the system caused the event (BR-17).
CREATE TABLE IF NOT EXISTS welfare_case_history (
    history_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    case_id INT UNSIGNED NOT NULL,
    event_type VARCHAR(40) NOT NULL,
    old_value VARCHAR(255) NULL,
    new_value VARCHAR(255) NULL,
    actor_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (history_id),
    KEY idx_welfare_case_history_case_id (case_id),
    KEY idx_welfare_case_history_actor_id (actor_id),
    CONSTRAINT fk_welfare_case_history_case FOREIGN KEY (case_id) REFERENCES welfare_cases (case_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_welfare_case_history_actor FOREIGN KEY (actor_id) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS welfare_attachments (
    attachment_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    case_id INT UNSIGNED NOT NULL,
    original_name TEXT NOT NULL,
    stored_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    size_bytes INT UNSIGNED NOT NULL,
    disposition ENUM('open','kept','returned','deleted') NOT NULL DEFAULT 'open',
    disposition_by INT UNSIGNED NULL,
    disposition_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (attachment_id),
    KEY idx_welfare_attachments_case_id (case_id),
    KEY idx_welfare_attachments_disposition_by (disposition_by),
    KEY idx_welfare_attachments_disposition (disposition),
    CONSTRAINT fk_welfare_attachments_case FOREIGN KEY (case_id) REFERENCES welfare_cases (case_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_welfare_attachments_disposition_by FOREIGN KEY (disposition_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Content, communications and eligibility
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS content_posts (
    post_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    type ENUM('news','event','publication','announcement') NOT NULL,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL,
    excerpt VARCHAR(300) NOT NULL,
    body MEDIUMTEXT NOT NULL,
    event_start DATETIME NULL,
    event_end DATETIME NULL,
    venue_or_link VARCHAR(255) NULL,
    registration_open TINYINT(1) NOT NULL DEFAULT 1,
    capacity SMALLINT UNSIGNED NULL,
    cover_image VARCHAR(255) NULL,
    status ENUM('draft','for_approval','published','returned','archived') NOT NULL DEFAULT 'draft',
    review_remarks TEXT NULL,
    author_id INT UNSIGNED NOT NULL,
    approved_by INT UNSIGNED NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (post_id),
    UNIQUE KEY uq_content_posts_slug (slug),
    KEY idx_content_posts_author_id (author_id),
    KEY idx_content_posts_approved_by (approved_by),
    KEY idx_content_posts_status (status),
    KEY idx_content_posts_type_status (type, status),
    KEY idx_content_posts_created_at (created_at),
    CONSTRAINT fk_content_posts_author FOREIGN KEY (author_id) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_content_posts_approved_by FOREIGN KEY (approved_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_templates (
    template_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(60) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    body_html MEDIUMTEXT NOT NULL,
    updated_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (template_id),
    UNIQUE KEY uq_email_templates_code (code),
    KEY idx_email_templates_updated_by (updated_by),
    CONSTRAINT fk_email_templates_updated_by FOREIGN KEY (updated_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_drafts (
    draft_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    subject VARCHAR(200) NOT NULL,
    body_html MEDIUMTEXT NOT NULL,
    target_group VARCHAR(60) NOT NULL,
    status ENUM('draft','queued','sent') NOT NULL DEFAULT 'draft',
    created_by INT UNSIGNED NOT NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (draft_id),
    KEY idx_email_drafts_created_by (created_by),
    KEY idx_email_drafts_status (status),
    CONSTRAINT fk_email_drafts_created_by FOREIGN KEY (created_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    recipient_email VARCHAR(150) NULL,
    template_id INT UNSIGNED NULL,
    channel ENUM('email','in_app') NOT NULL,
    title VARCHAR(200) NOT NULL,
    link VARCHAR(255) NULL,
    payload_json JSON NOT NULL,
    status ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_error TEXT NULL,
    scheduled_at DATETIME NULL,
    sent_at DATETIME NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (notification_id),
    KEY idx_notifications_user_bell (user_id, channel, read_at),
    KEY idx_notifications_queue (channel, status, scheduled_at),
    KEY idx_notifications_template_id (template_id),
    KEY idx_notifications_status (status),
    KEY idx_notifications_created_at (created_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_notifications_template FOREIGN KEY (template_id) REFERENCES email_templates (template_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academic_terms (
    term_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    academic_year CHAR(9) NOT NULL,
    semester ENUM('1st','2nd','summer') NOT NULL,
    screening_deadline DATE NOT NULL,
    grace_days TINYINT UNSIGNED NOT NULL DEFAULT 7,
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    current_flag TINYINT UNSIGNED AS (IF(is_current = 1, 1, NULL)) STORED,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (term_id),
    UNIQUE KEY uq_academic_terms_year_semester (academic_year, semester),
    UNIQUE KEY uq_academic_terms_current_flag (current_flag),
    KEY idx_academic_terms_is_current (is_current),
    CONSTRAINT chk_academic_terms_year CHECK (academic_year REGEXP '^[0-9]{4}-[0-9]{4}$' AND CAST(RIGHT(academic_year, 4) AS UNSIGNED) = CAST(LEFT(academic_year, 4) AS UNSIGNED) + 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS eligibility_screenings (
    screening_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    term_id INT UNSIGNED NOT NULL,
    member_id INT UNSIGNED NOT NULL,
    has_failed_subject TINYINT(1) NULL,
    is_officially_enrolled TINYINT(1) NULL,
    result ENUM('pending','submitted','eligible','flagged','incomplete','confirmed_ineligible','cleared') NOT NULL DEFAULT 'pending',
    flagged_at DATETIME NULL,
    verified_by INT UNSIGNED NULL,
    verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (screening_id),
    UNIQUE KEY uq_eligibility_screenings_term_member (term_id, member_id),
    KEY idx_eligibility_screenings_member_id (member_id),
    KEY idx_eligibility_screenings_verified_by (verified_by),
    KEY idx_eligibility_screenings_result (result),
    CONSTRAINT fk_eligibility_screenings_term FOREIGN KEY (term_id) REFERENCES academic_terms (term_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_eligibility_screenings_member FOREIGN KEY (member_id) REFERENCES members (member_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_eligibility_screenings_verified_by FOREIGN KEY (verified_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- An officer may submit several files. Replaces the single document_path, which stays unused (addition, step 17).
CREATE TABLE IF NOT EXISTS eligibility_documents (
    document_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    screening_id INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    size_bytes INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (document_id),
    KEY idx_eligibility_documents_screening_id (screening_id),
    CONSTRAINT fk_eligibility_documents_screening FOREIGN KEY (screening_id) REFERENCES eligibility_screenings (screening_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS eligibility_reviews (
    review_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    screening_id INT UNSIGNED NOT NULL,
    decision ENUM('confirm_ineligible','clear') NOT NULL,
    remarks TEXT NOT NULL,
    decided_by INT UNSIGNED NOT NULL,
    decided_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (review_id),
    UNIQUE KEY uq_eligibility_reviews_screening_id (screening_id),
    KEY idx_eligibility_reviews_decided_by (decided_by),
    CONSTRAINT fk_eligibility_reviews_screening FOREIGN KEY (screening_id) REFERENCES eligibility_screenings (screening_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_eligibility_reviews_decided_by FOREIGN KEY (decided_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Elected officers' terms (Constitution and By-Laws, Article VI Section 4: at most two consecutive terms in one post).
CREATE TABLE IF NOT EXISTS officer_terms (
    term_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    member_id INT UNSIGNED NOT NULL,
    position_id INT UNSIGNED NOT NULL,
    academic_year CHAR(9) NOT NULL,
    year_level VARCHAR(20) NOT NULL,
    recorded_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (term_id),
    UNIQUE KEY uq_officer_terms_member_position_year (member_id, position_id, academic_year),
    KEY idx_officer_terms_position_id (position_id),
    KEY idx_officer_terms_recorded_by (recorded_by),
    CONSTRAINT fk_officer_terms_member FOREIGN KEY (member_id) REFERENCES members (member_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_officer_terms_position FOREIGN KEY (position_id) REFERENCES positions (position_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_officer_terms_recorded_by FOREIGN KEY (recorded_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_officer_terms_year CHECK (academic_year REGEXP '^[0-9]{4}-[0-9]{4}$' AND CAST(RIGHT(academic_year, 4) AS UNSIGNED) = CAST(LEFT(academic_year, 4) AS UNSIGNED) + 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Candidacies for the Executive Board and the Year-Level Council (Article VI), filed by members.
CREATE TABLE IF NOT EXISTS election_candidacies (
    candidacy_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    position_id INT UNSIGNED NOT NULL,
    academic_year CHAR(9) NOT NULL,
    year_level VARCHAR(20) NOT NULL,
    section VARCHAR(30) NOT NULL,
    contact_no VARCHAR(20) NOT NULL,
    statement TEXT NULL,
    cor_path VARCHAR(255) NOT NULL,
    grades_path VARCHAR(255) NOT NULL,
    status ENUM('filed','qualified','disqualified','withdrawn','elected') NOT NULL DEFAULT 'filed',
    remarks VARCHAR(255) NULL,
    decided_by INT UNSIGNED NULL,
    decided_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (candidacy_id),
    KEY idx_election_candidacies_user_year (user_id, academic_year),
    KEY idx_election_candidacies_position_id (position_id),
    KEY idx_election_candidacies_status (status),
    KEY idx_election_candidacies_decided_by (decided_by),
    CONSTRAINT fk_election_candidacies_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_election_candidacies_position FOREIGN KEY (position_id) REFERENCES positions (position_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_election_candidacies_decided_by FOREIGN KEY (decided_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_election_candidacies_year CHECK (academic_year REGEXP '^[0-9]{4}-[0-9]{4}$' AND CAST(RIGHT(academic_year, 4) AS UNSIGNED) = CAST(LEFT(academic_year, 4) AS UNSIGNED) + 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
;

-- A second standing committee for a committee member or Year-Level Representative (Article VI Section 4-A allows two).
CREATE TABLE IF NOT EXISTS member_committees (
    member_committee_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    member_id INT UNSIGNED NOT NULL,
    committee_id INT UNSIGNED NOT NULL,
    joined_at DATE NOT NULL,
    added_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (member_committee_id),
    UNIQUE KEY uq_member_committees_member_committee (member_id, committee_id),
    KEY idx_member_committees_committee_id (committee_id),
    KEY idx_member_committees_added_by (added_by),
    CONSTRAINT fk_member_committees_member FOREIGN KEY (member_id) REFERENCES members (member_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_member_committees_committee FOREIGN KEY (committee_id) REFERENCES committees (committee_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_member_committees_added_by FOREIGN KEY (added_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
;

-- A member asks for their digital ID or certificate; the MSW Committee Head uploads it, made from AGILE's own template.
CREATE TABLE IF NOT EXISTS credential_requests (
    request_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    member_id INT UNSIGNED NOT NULL,
    type ENUM('digital_id','certificate') NOT NULL,
    note VARCHAR(255) NULL,
    photo_path VARCHAR(255) NULL,
    status ENUM('requested','issued','declined','cancelled') NOT NULL DEFAULT 'requested',
    credential_id INT UNSIGNED NULL,
    remarks VARCHAR(255) NULL,
    handled_by INT UNSIGNED NULL,
    handled_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (request_id),
    KEY idx_credential_requests_member_id (member_id),
    KEY idx_credential_requests_status (status),
    KEY idx_credential_requests_credential_id (credential_id),
    KEY idx_credential_requests_handled_by (handled_by),
    CONSTRAINT fk_credential_requests_member FOREIGN KEY (member_id) REFERENCES members (member_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_credential_requests_credential FOREIGN KEY (credential_id) REFERENCES digital_credentials (credential_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_credential_requests_handled_by FOREIGN KEY (handled_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
;

-- AGILE's interview question bank, and the MSW interviewer's assessment of each applicant (Nov 23, 2026).
CREATE TABLE IF NOT EXISTS interview_questions (
    question_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    applicant_type VARCHAR(30) NOT NULL,
    committee VARCHAR(100) NULL,
    position VARCHAR(100) NULL,
    code VARCHAR(10) NOT NULL,
    question VARCHAR(500) NOT NULL,
    competency VARCHAR(100) NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (question_id),
    KEY idx_interview_questions_type (applicant_type, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS applicant_assessments (
    assessment_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id INT UNSIGNED NOT NULL,
    evaluator_id INT UNSIGNED NOT NULL,
    ratings_json JSON NOT NULL,
    application_score TINYINT UNSIGNED NOT NULL,
    interview_score TINYINT UNSIGNED NOT NULL,
    role_score TINYINT UNSIGNED NOT NULL,
    total_score TINYINT UNSIGNED NOT NULL,
    recommendation ENUM('highly_recommended','recommended','recommended_with_considerations','further_evaluation','not_recommended') NOT NULL,
    strengths TEXT NULL,
    development TEXT NULL,
    remarks TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (assessment_id),
    UNIQUE KEY uq_applicant_assessments_application_id (application_id),
    KEY idx_applicant_assessments_evaluator_id (evaluator_id),
    CONSTRAINT fk_applicant_assessments_application FOREIGN KEY (application_id) REFERENCES membership_applications (application_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_applicant_assessments_evaluator FOREIGN KEY (evaluator_id) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Event registration and attendance (Nov 23, 2026).
CREATE TABLE IF NOT EXISTS event_registrations (
    registration_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id INT UNSIGNED NOT NULL,
    member_id INT UNSIGNED NOT NULL,
    status ENUM('registered','cancelled') NOT NULL DEFAULT 'registered',
    walk_in TINYINT(1) NOT NULL DEFAULT 0,
    registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cancelled_at DATETIME NULL,
    attended_at DATETIME NULL,
    checked_in_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (registration_id),
    UNIQUE KEY uq_event_registrations_post_member (post_id, member_id),
    KEY idx_event_registrations_member_id (member_id),
    KEY idx_event_registrations_checked_in_by (checked_in_by),
    CONSTRAINT fk_event_registrations_post FOREIGN KEY (post_id) REFERENCES content_posts (post_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_event_registrations_member FOREIGN KEY (member_id) REFERENCES members (member_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_event_registrations_checked_in_by FOREIGN KEY (checked_in_by) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notification preferences (Nov 23, 2026). No row means every channel is on.
CREATE TABLE IF NOT EXISTS notification_preferences (
    user_id INT UNSIGNED NOT NULL,
    category VARCHAR(30) NOT NULL,
    email TINYINT(1) NOT NULL DEFAULT 1,
    in_app TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, category),
    CONSTRAINT fk_notification_preferences_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_runs (
    run_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job VARCHAR(60) NOT NULL,
    started_at DATETIME NOT NULL,
    finished_at DATETIME NULL,
    result ENUM('running','ok','failed') NOT NULL DEFAULT 'running',
    summary VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (run_id),
    KEY idx_job_runs_job (job),
    KEY idx_job_runs_started_at (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
