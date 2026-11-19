<?php

declare(strict_types=1);

return static function (PDO $db): void {
    $rubric = [
        'scale' => ['min' => 1, 'max' => 5],
        'criteria' => [
            ['key' => 'qualifications', 'label' => 'Qualifications and experience', 'weight' => 25],
            ['key' => 'skills', 'label' => 'Skills relevant to the position', 'weight' => 25],
            ['key' => 'communication', 'label' => 'Communication', 'weight' => 20],
            ['key' => 'commitment', 'label' => 'Commitment and availability', 'weight' => 20],
            ['key' => 'teamwork', 'label' => 'Teamwork and attitude', 'weight' => 10],
        ],
    ];

    $settings = [
        'upload.max_mb' => '5',
        'eligibility.grace_days' => '7',
        'session.idle_minutes' => '30',
        'welfare.guest_submission' => '1',
        'org.contact_email' => 'contact@agile.test',
        'org.full_name' => 'AGILE OUS — Academic Guild of IT Learners and Experts',
        'org.facebook_url' => '',
        // Sample signatory. Replace it with the real Committee Head.
        'email.signatory_name' => 'Elena Ramos',
        'email.signatory_position' => 'Committee Head',
        'email.signatory_committee' => 'Membership and Student Welfare Committee',
        // Sample wording. Replace it with AGILE's official mission and vision.
        'org.mission' => 'To build a supportive community of BSIT-OU learners through academic support, skills development and student welfare services.',
        'org.vision' => 'A connected guild of IT learners and experts who grow together and serve others through technology.',
        'evaluation.rubric' => json_encode($rubric, JSON_THROW_ON_ERROR),
    ];

    foreach ($settings as $key => $value) {
        seed_row($db, 'settings', 'setting_key', ['setting_key' => $key], ['setting_value' => $value]);
    }
};
