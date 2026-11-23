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
        // The official email (Constitution letterhead). The MSW Committee's own address is ORG_MSW_EMAIL in .env.
        'org.contact_email' => 'agile.pupous@gmail.com',
        'org.full_name' => 'AGILE OUS — Academic Guild of IT Learners and Experts',
        'org.facebook_url' => '', // The page is not published yet (Nov 23, 2026).
        // Sample signatory. Replace it with the real Committee Head.
        'email.signatory_name' => 'Elena Ramos',
        'email.signatory_position' => 'Committee Head',
        'email.signatory_committee' => 'Membership and Student Welfare Committee',
        // Constitution and By-Laws, Article II. One item per line for the mission and objectives.
        'org.vision' => 'The Organization envisions itself as the leading academic and support hub for BSIT - Open University students, fostering collaboration, technical excellence, and an empowering virtual learning ecosystem where distance learners thrive.',
        'org.mission' => "Providing accessible academic support, technical tutorials, and peer mentorship tailored to the dynamics of independent and distance learning.\n"
            . "Cultivating a robust professional network connecting students with alumni, IT industry leaders, and tech communities.\n"
            . "Delivering hands-on workshops, technical skill-building bootcamps, and real-world project collaborations.\n"
            . 'Ensuring equitable access to digital resources and peer assistance to bridge distance learning challenges.',
        'org.objectives' => "To build and sustain a dependable online academic support framework for BSIT-OUS students.\n"
            . "To conduct IT-focused workshops, study sessions, and industry networking events.\n"
            . "To promote ethical practices, open-source collaboration, and digital innovation.\n"
            . 'To represent and advocate for the general welfare and academic interests of the BSIT distance-learning community.',
        'evaluation.rubric' => json_encode($rubric, JSON_THROW_ON_ERROR),
    ];

    foreach ($settings as $key => $value) {
        seed_row($db, 'settings', 'setting_key', ['setting_key' => $key], ['setting_value' => $value]);
    }
};
