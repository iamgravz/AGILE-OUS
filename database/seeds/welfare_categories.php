<?php

declare(strict_types=1);

return static function (PDO $db): void {
    $categories = [
        1 => 'Academic',
        2 => 'Financial',
        3 => 'Mental Health',
        4 => 'Discipline',
        5 => 'Scholarship',
        6 => 'Harassment/Bullying',
        7 => 'Others',
    ];

    foreach ($categories as $id => $name) {
        seed_row($db, 'welfare_categories', 'category_id', ['category_id' => $id], ['name' => $name]);
    }
};
