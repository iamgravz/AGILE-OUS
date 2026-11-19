<?php

declare(strict_types=1);

return static function (PDO $db): void {
    // Year level is asked on the wizard's academic step. Earlier seeds stored it as a common question.
    $db->exec("UPDATE application_questions SET role_type = 'academic' WHERE role_type = 'common' AND question_text = 'What is your current year level?'");

    $questions = [
        'academic' => [
            ['select', 'What is your current year level?', ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year or above'], true],
        ],
        'common' => [
            ['textarea', 'Why do you want to join AGILE?', null, true],
            ['textarea', 'What skills can you contribute to the organization?', null, true],
            ['text', 'How many hours per week are you available for AGILE activities?', null, true],
        ],
        'officer' => [
            ['textarea', 'What leadership experience do you have?', null, true],
            ['textarea', 'What would you prioritize during your term?', null, true],
        ],
        'committee_head' => [
            ['textarea', 'How would you lead and organize your committee?', null, true],
            ['textarea', 'Describe a project or activity you have led before.', null, true],
        ],
        'deputy' => [
            ['textarea', 'How would you support the committee head in their work?', null, true],
            ['textarea', 'Describe a time you helped a team finish a task on schedule.', null, true],
        ],
        'committee_member' => [
            ['textarea', 'What tasks would you like to take on in this committee?', null, true],
            ['textarea', 'What do you hope to learn from joining this committee?', null, false],
        ],
        'source_code' => [
            ['text', 'What is your GitHub or portfolio link?', null, true],
            ['textarea', 'Which programming languages and tools are you comfortable with?', null, true],
            ['textarea', 'Describe a software project you have worked on.', null, true],
        ],
    ];

    foreach ($questions as $roleType => $items) {
        foreach ($items as $index => [$inputType, $text, $options, $required]) {
            seed_row($db, 'application_questions', 'question_id', ['role_type' => $roleType, 'question_text' => $text], [
                'input_type' => $inputType,
                'options_json' => $options === null ? null : json_encode($options, JSON_THROW_ON_ERROR),
                'is_required' => $required ? 1 : 0,
                'sort_order' => ($index + 1) * 10,
            ]);
        }
    }
};
