<?php

declare(strict_types=1);

// The sample vacancy application (once DEMVACANCY01) gets a code in the system's own format too. Safe to run again.
return static function (PDO $db): void {
    $db->exec("UPDATE membership_applications SET tracking_code = '9NARD7QDRQ59' WHERE tracking_code = 'DEMVACANCY01'");
};
