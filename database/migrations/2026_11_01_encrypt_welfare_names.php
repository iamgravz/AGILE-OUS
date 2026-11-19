<?php

declare(strict_types=1);

use App\Services\EncryptionService;

// Step 22: a welfare attachment's file name and who a case was referred to are welfare text too (section 11).
// Safe to run again: a value that already decrypts is skipped, and so is one that looks encrypted with another key,
// so a wrong ENCRYPTION_KEY can never encrypt a value twice.
return static function (PDO $db): void {
    foreach ([['welfare_attachments', 'attachment_id', 'original_name'], ['referrals', 'referral_id', 'referred_to']] as [$table, $key, $column]) {
        $type = $db->prepare('SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $type->execute([$table, $column]);

        if ($type->fetchColumn() !== 'text') {
            $db->exec("ALTER TABLE {$table} MODIFY {$column} TEXT NOT NULL");
        }

        $rows = $db->query("SELECT {$key} AS id, {$column} AS value FROM {$table}")->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            continue;
        }

        $encryption = new EncryptionService();
        $update = $db->prepare("UPDATE {$table} SET {$column} = ? WHERE {$key} = ?");
        $changed = 0;

        foreach ($rows as $row) {
            try {
                $encryption->decrypt($row['value']);

                continue;
            } catch (RuntimeException) {
                // Plain text, or encrypted with another key, which the next check tells apart.
            }

            $raw = base64_decode($row['value'], true);

            if ($raw !== false && strlen($raw) >= 28 && base64_encode($raw) === $row['value']) {
                echo "Skipped {$table} row {$row['id']}: it looks encrypted with another key. Check ENCRYPTION_KEY.\n";

                continue;
            }

            $update->execute([$encryption->encrypt($row['value']), $row['id']]);
            $changed++;
        }

        if ($changed > 0) {
            echo "Encrypted {$changed} values in {$table}.{$column}.\n";
        }
    }
};
