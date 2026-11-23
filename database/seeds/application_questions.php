<?php

declare(strict_types=1);

// The application questions, from AGILE's own registration form (Google Form, Nov 23, 2026), by what the person applies
// for: general membership, Committee Member, Deputy Committee Head, Committee Head, or a post in The Source Code.
// The form's "which committee / position" questions are left out: the apply page already asks them. The year level
// is asked on the wizard's academic step. Questions from before stay in the table, inactive (migration
// 2026_11_23_applicant_assessment), so old answers keep their question.
return static function (PDO $db): void {
    $db->exec("UPDATE application_questions SET role_type = 'academic' WHERE role_type = 'common' AND question_text = 'What is your current year level?'");

    $yesNo = ['Yes', 'No'];
    $questions = [
        'academic' => [
            ['select', 'What is your current year level?', ['1st Year', '2nd Year', '3rd Year', '4th Year'], true],
        ],
        'general' => [
            ['textarea', 'Why do you want to join AGILE?', null, true],
            ['textarea', 'What skills, interests, or areas of Information Technology are you interested in developing?', null, true],
            ['textarea', 'How would you like to participate in AGILE?', null, true],
            ['select', 'Are you willing to participate in AGILE programs and activities based on your availability?', $yesNo, true],
        ],
        'committee_member' => [
            ['textarea', 'Why do you want to join this committee?', null, true],
            ['textarea', 'What activities, projects, or responsibilities are you interested in helping with?', null, true],
            ['textarea', 'How will you balance your academic responsibilities and your responsibilities as a Committee Member?', null, true],
            ['select', 'Are you willing to attend meetings, participate in activities, and perform responsibilities assigned to Committee Members?', $yesNo, true],
        ],
        'deputy' => [
            ['textarea', 'Why did you choose this committee?', null, true],
            ['textarea', 'Why do you want to serve as Deputy Committee Head of AGILE?', null, true],
            ['textarea', 'What skills, knowledge, or experience can you contribute to this committee?', null, true],
            ['select', 'Do you have previous leadership or organizational experience?', $yesNo, true],
            ['textarea', 'If yes, briefly describe your previous leadership or organizational experience.', null, false],
            ['textarea', 'How would you assist the Committee Head in managing and supporting the committee?', null, true],
            ['textarea', 'What activities, projects, or improvements would you propose for this committee?', null, true],
            ['textarea', 'How will you balance your academic responsibilities and your responsibilities as Deputy Committee Head?', null, true],
            ['select', 'Are you willing to attend meetings, participate in activities, and perform responsibilities assigned to the Deputy Committee Head?', $yesNo, true],
        ],
        'committee_head' => [
            ['textarea', 'Why did you choose this committee?', null, true],
            ['textarea', 'Why do you want to serve as Committee Head of AGILE?', null, true],
            ['textarea', 'What skills, knowledge, leadership experience, or qualifications can you contribute as Committee Head?', null, true],
            ['select', 'Do you have previous leadership or organizational experience?', $yesNo, true],
            ['textarea', 'If yes, briefly describe your previous leadership or organizational experience.', null, false],
            ['textarea', 'How would you lead, coordinate, and support the members of your committee?', null, true],
            ['textarea', 'What activities, projects, or improvements would you propose for this committee?', null, true],
            ['textarea', 'How would you handle disagreements or problems within your committee?', null, true],
            ['textarea', 'How will you balance your academic responsibilities and your responsibilities as Committee Head?', null, true],
            ['select', 'Are you willing to attend meetings, lead committee activities, and fulfill the responsibilities assigned to the Committee Head?', $yesNo, true],
        ],
        'source_code' => [
            ['textarea', 'Why are you applying for this position in The Source Code?', null, true],
            ['textarea', 'What relevant skills, knowledge, or experience can you contribute to The Source Code?', null, true],
            ['textarea', 'Please describe any previous experience related to writing, editing, design, illustration, photography, videography, or student publications.', null, true],
            ['text', 'Portfolio or Work Sample Link', null, false],
            ['textarea', 'Why do you want to become part of The Source Code?', null, true],
            ['textarea', 'How will you balance your academic responsibilities and your responsibilities in The Source Code?', null, true],
            ['select', 'Are you willing to attend meetings, participate in publication activities, and fulfill the responsibilities of your selected position?', $yesNo, true],
        ],
    ];

    foreach ($questions as $roleType => $items) {
        foreach ($items as $index => [$inputType, $text, $options, $required]) {
            seed_row($db, 'application_questions', 'question_id', ['role_type' => $roleType, 'question_text' => $text], [
                'input_type' => $inputType,
                'options_json' => $options === null ? null : json_encode($options, JSON_THROW_ON_ERROR),
                'is_required' => $required ? 1 : 0,
                'is_active' => 1,
                'sort_order' => ($index + 1) * 10,
            ]);
        }
    }
};
