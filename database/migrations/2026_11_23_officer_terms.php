<?php

declare(strict_types=1);

// Nov 23, 2026: elected officers' terms, for the term limit (Article VI Section 4). And the Facebook page is not
// published yet, so the unconfirmed address set on Nov 22 is cleared (an address someone typed in stays). Safe to run again.
return static function (PDO $db): void {
    $db->exec(<<<'SQL'
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

    $db->prepare("UPDATE settings SET setting_value = '' WHERE setting_key = 'org.facebook_url' AND setting_value = ?")
        ->execute(['https://www.facebook.com/agile.ous']);
};
