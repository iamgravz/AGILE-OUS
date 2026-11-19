<?php

declare(strict_types=1);

// Adds the wizard's two columns to databases created before step 10. Safe to run again.
return static function (PDO $db): void {
    $columns = [
        'wizard_step' => 'TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER status',
        'enrollment_confirmed_at' => 'DATETIME NULL AFTER consent_at',
    ];

    $exists = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'membership_applications' AND COLUMN_NAME = ?"
    );

    foreach ($columns as $column => $definition) {
        $exists->execute([$column]);

        if ((int) $exists->fetchColumn() === 0) {
            $db->exec("ALTER TABLE membership_applications ADD COLUMN {$column} {$definition}");
            echo "Added membership_applications.{$column}.\n";
        }
    }
};
