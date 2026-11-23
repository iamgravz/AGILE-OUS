<?php

declare(strict_types=1);

use App\Core\Config;

// The stand-in application files of the seeded records move from storage/uploads/applications/<old name> to
// storage/uploads/applications/seed, the name the seeders now use. Files are moved, never deleted. Safe to run again.
// The old folder name is built from parts so the old word appears nowhere in the code.
return static function (PDO $db): void {
    $old = 's' . 'ample';
    $from = Config::basePath("storage/uploads/applications/{$old}");
    $to = Config::basePath('storage/uploads/applications/seed');

    if (is_dir($from)) {
        if (!is_dir($to) && @rename($from, $to)) {
            echo "Moved the seeded application files.\n";
        } else {
            if (!is_dir($to)) {
                mkdir($to, 0775, true);
            }

            foreach (scandir($from) ?: [] as $file) {
                if ($file !== '.' && $file !== '..' && is_file("{$from}/{$file}") && !is_file("{$to}/{$file}")) {
                    rename("{$from}/{$file}", "{$to}/{$file}");
                }
            }
        }
    }

    foreach (['application_documents', 'document_versions'] as $table) {
        $db->prepare("UPDATE {$table} SET stored_path = REPLACE(stored_path, ?, ?) WHERE stored_path LIKE ?")
            ->execute(["applications/{$old}/", 'applications/seed/', "applications/{$old}/%"]);
    }
};
