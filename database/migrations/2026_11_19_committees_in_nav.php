<?php

declare(strict_types=1);

// The committees moved from the home page to the nav bar (About, "Our committees"). A saved list of home page
// sections still has them ticked, so this takes them out once. Settings can tick them again.
return static function (PDO $db): void {
    $saved = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'landing.show_sections'")->fetchColumn();
    $list = is_string($saved) ? json_decode($saved, true) : null;

    if (!is_array($list) || !in_array('committees', $list, true)) {
        return;
    }

    $list = array_values(array_diff($list, ['committees']));
    $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'landing.show_sections'")
        ->execute([json_encode($list, JSON_THROW_ON_ERROR)]);
    echo "Home page: committees section turned off (now in the nav bar).\n";
};
