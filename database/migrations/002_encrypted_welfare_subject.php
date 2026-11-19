<?php

declare(strict_types=1);

// Widens welfare_cases.subject for ciphertext on databases created before the decision to encrypt it. Safe to run again.
return static function (PDO $db): void {
    $type = $db->query(
        "SELECT DATA_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'welfare_cases' AND COLUMN_NAME = 'subject'"
    )->fetchColumn();

    if ($type === 'varchar') {
        $db->exec('ALTER TABLE welfare_cases MODIFY subject TEXT NOT NULL');
        echo "Changed welfare_cases.subject to TEXT.\n";
    }
};
