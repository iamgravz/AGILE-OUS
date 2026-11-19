<?php

declare(strict_types=1);

// Which system role a position gives its holder (section 5). MembershipService::roleSlugFor() reads this.
return [
    'msw_committee' => 'Membership and Student Welfare',

    // Positions with no committee, by title.
    'executive' => ['President' => 'president', 'default' => 'execomm'],

    // By positions.role_type.
    'msw' => ['committee_head' => 'msw_head', 'deputy' => 'msw_member', 'committee_member' => 'msw_member'],
    'other_committees' => ['committee_head' => 'committee_head'],

    // Everyone else, including The Source Code: general_member, with membership_type officer from their position.
    'default' => 'general_member',

    // What the MSW Committee Head may choose instead when approving an appointment.
    'overrides' => ['general_member', 'committee_head', 'execomm', 'msw_member', 'msw_head', 'president'],
];
