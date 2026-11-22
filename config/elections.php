<?php

declare(strict_types=1);

// Who may be recorded as an elected officer (Constitution and By-Laws, Article VI). ElectedOfficerService reads this.
// The by-laws can still be amended: change the numbers here, nothing else.
return [
    // Article VI Section 2: the lowest year level per Executive Board title; 'default' covers the other titles.
    'min_year_level' => [
        'President' => 3,
        'Vice President for Internal Affairs' => 3,
        'Vice President for External Affairs' => 3,
        'default' => 2,
    ],

    // Article V Section 3: a Year-Level Representative is in the year level they represent.
    'representative_same_year' => true,

    // Article VI Section 2: what the MSW Committee Head confirms from the candidate's COR and grades.
    'confirmations' => [
        'full_load' => 'Enrolled with a full academic load this semester (from the COR).',
        'clean_grades' => 'No grade of 5.0, W (withdrawn) or D (dropped) in the previous semester (from the copy of grades).',
    ],

    // Article VI Section 4: at most this many consecutive academic years in the same position.
    'max_consecutive_terms' => 2,
];
