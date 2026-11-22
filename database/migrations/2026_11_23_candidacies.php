<?php

declare(strict_types=1);

// Candidacies (Nov 23, 2026): members file to run for the Executive Board or the Year-Level Council (Article VI), with
// their COR and previous semester copy of grades. Safe to run again.
return static function (PDO $db): void {
    $db->exec(<<<'SQL'
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
        SQL);
};
