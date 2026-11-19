<?php

declare(strict_types=1);

// AGILE's real structure (update R6): the Executive Committee, seven committees and The Source Code.
// The 2026_11_18_real_committees migration renames the old sample rows first, so this only adds what is missing.
return static function (PDO $db): void {
    // The Executive Committee as AGILE has it (AGILE-OUS Applicant Assessment Report, Positions Dashboard).
    $executive = ['President', 'Vice President for Internal Affairs', 'Vice President for External Affairs', 'Secretary',
        'Assistant Secretary', 'Treasurer', 'Auditor'];

    foreach ($executive as $title) {
        seed_row($db, 'positions', 'position_id', ['committee_id' => null, 'title' => $title], ['role_type' => 'officer']);
    }

    $committees = [
        'Academics and Training' => 'Plans tutorials, review sessions, workshops and other academic support.',
        'Research and Extensions' => 'Leads research activities and extension programs with partner communities.',
        'Creatives, Social Media and Communication' => 'Handles publicity materials, social media and announcements.',
        'Finance and Fund Raising' => 'Manages AGILE funds, budgets and fund-raising activities.',
        'Logistics' => 'Prepares venues, equipment and supplies for AGILE activities.',
        'Membership and Student Welfare' => 'Manages membership, recruitment and student welfare services.',
        'Events and External Relations' => 'Organizes AGILE events and works with partner organizations.',
    ];

    foreach ($committees as $name => $description) {
        $committeeId = seed_row($db, 'committees', 'committee_id', ['name' => $name], ['description' => $description]);

        foreach (['Committee Head' => 'committee_head', 'Deputy Committee Head' => 'deputy', 'Committee Member' => 'committee_member'] as $title => $roleType) {
            seed_row($db, 'positions', 'position_id', ['committee_id' => $committeeId, 'title' => $title], ['role_type' => $roleType]);
        }
    }

    // The Source Code is AGILE's publication, led by its Editor-in-Chief. It has no committee head in the system: the MSW
    // Committee Head decides its applications (BR-20).
    $sourceCode = seed_row($db, 'committees', 'committee_id', ['name' => 'The Source Code'], ['description' => 'The official student publication of AGILE OUS.']);
    $titles = ['Editor-in-Chief', 'Managing Editor', 'News Editor', 'Feature Editor', 'Writer', 'Head Cartoonist', 'Cartoonist',
        'Layout Editor', 'Head Photojournalist', 'Photojournalist', 'Videographer/Editor'];

    foreach ($titles as $title) {
        seed_row($db, 'positions', 'position_id', ['committee_id' => $sourceCode, 'title' => $title], ['role_type' => 'source_code']);
    }
};
