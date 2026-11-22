<?php

declare(strict_types=1);

// A second standing committee (Nov 23, 2026): a committee member or Year-Level Representative may also be a
// Committee Member of one other standing committee (Article VI Section 4-A). Safe to run again.
return static function (PDO $db): void {
    $db->exec(<<<'SQL'
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
        SQL);
};
