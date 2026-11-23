<?php

declare(strict_types=1);

// Permission names that say what they guard. evaluation.view is renamed application.view (same grants, same row);
// candidacy.file is new and given to exactly the roles and people who hold welfare.submit, which "Run for office"
// used before. Nobody gains or loses access. Safe to run again.
return static function (PDO $db): void {
    $id = static fn (string $code): int|false => $db->query('SELECT permission_id FROM permissions WHERE code = ' . $db->quote($code))->fetchColumn();

    if ($id('evaluation.view') !== false && $id('application.view') === false) {
        $db->exec("UPDATE permissions SET code = 'application.view', description = 'Read applications and their assessment' WHERE code = 'evaluation.view'");
        echo "Renamed evaluation.view to application.view.\n";
    }

    if ($id('candidacy.file') === false) {
        $db->exec("INSERT INTO permissions (code, description) VALUES ('candidacy.file', 'File a candidacy for an elected office')");
        $new = (int) $id('candidacy.file');
        $from = $id('welfare.submit');

        if ($from !== false) {
            $db->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) SELECT role_id, ? FROM role_permissions WHERE permission_id = ?')->execute([$new, (int) $from]);
            $db->prepare('INSERT IGNORE INTO user_permissions (user_id, permission_id) SELECT user_id, ? FROM user_permissions WHERE permission_id = ?')->execute([$new, (int) $from]);
        }

        echo "Added candidacy.file with the grants of welfare.submit.\n";
    }
};
