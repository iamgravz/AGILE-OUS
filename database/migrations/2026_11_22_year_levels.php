<?php

declare(strict_types=1);

// Year level goes up to 4th Year (Nov 22, 2026): "5th Year or above" is no longer offered on the apply page or step 2.
// Answers already given keep their text. Safe to run again.
return static function (PDO $db): void {
    $statement = $db->prepare("UPDATE application_questions SET options_json = ? WHERE role_type = 'academic' AND question_text = 'What is your current year level?'");
    $statement->execute([json_encode(['1st Year', '2nd Year', '3rd Year', '4th Year'], JSON_THROW_ON_ERROR)]);

    if ($statement->rowCount() > 0) {
        echo "Year level now goes up to 4th Year.\n";
    }
};
