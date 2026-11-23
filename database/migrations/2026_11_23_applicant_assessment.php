<?php

declare(strict_types=1);

// AGILE's own application and interview process (Nov 23, 2026), from its Google Sheets: the registration form's
// questions replace the system's sample ones (kept, inactive, so old answers still have their question), the interview
// question bank, and the MSW interviewer's assessment that Committee Heads read before deciding. Safe to run again.
return static function (PDO $db): void {
    $has = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'application_questions' AND COLUMN_NAME = 'is_active'")->fetchColumn();

    if ((int) $has === 0) {
        $db->exec('ALTER TABLE application_questions ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER is_required');
        // The sample questions from before: answers to them stay readable, but nobody is asked them again.
        $db->exec("UPDATE application_questions SET is_active = 0 WHERE role_type <> 'academic'");
        echo "Retired the sample application questions.\n";
    }

    $db->exec(<<<'SQL'
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    $db->exec(<<<'SQL'
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
};
