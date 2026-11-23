<?php

declare(strict_types=1);

// Made-up applications and welfare cases get codes in the system's own format, like real ones: the six fixed sample
// applications, the bulk ones (once DEMBLK + number) and the welfare cases (once SMPLCASE + number). The new codes
// are what seed_code() in database/seed.php gives, so seeding again finds these rows. Safe to run again.
return static function (PDO $db): void {
    $code = static function (string $key): string {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $hash = hash('sha256', 'agile-seed:' . $key, true);
        $out = '';

        for ($i = 0; $i < 12; $i++) {
            $out .= $alphabet[ord($hash[$i]) % strlen($alphabet)];
        }

        return $out;
    };

    $fixed = ['DEMAPPSUBMT2' => 'FJKBRQJTPMC5', 'DEMAPPREV2SE' => 'FTAVJNRA69R4', 'DEMAPPREVWD3' => '6M3DRHUUHT49',
        'DEMAPPAPPRV4' => 'GJMK2Q7UT6A7', 'DEMAPPAPPRV5' => 'DDVMBQCGKZSN', 'DEMAPPWAIT06' => 'CHZE8VH86R3C'];
    $rename = $db->prepare('UPDATE membership_applications SET tracking_code = ? WHERE tracking_code = ?');

    foreach ($fixed as $old => $new) {
        $rename->execute([$new, $old]);
    }

    foreach ($db->query("SELECT tracking_code FROM membership_applications WHERE tracking_code LIKE 'DEMBLK%'")->fetchAll(PDO::FETCH_COLUMN) as $old) {
        $rename->execute([$code('bulk:' . (int) substr((string) $old, 6)), $old]);
    }

    $renameCase = $db->prepare('UPDATE welfare_cases SET case_code = ? WHERE case_code = ?');

    foreach ($db->query("SELECT case_code FROM welfare_cases WHERE case_code LIKE 'SMPLCASE%'")->fetchAll(PDO::FETCH_COLUMN) as $old) {
        $renameCase->execute([$code('case:' . (int) substr((string) $old, 8)), $old]);
    }
};
