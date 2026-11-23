<?php

declare(strict_types=1);

// Archiving decided documents (Constitution and By-Laws, Article III Section 6: archive only, nothing is
// purged). DocumentArchiveService reads this.
return [
    // A decided application's COR and grades, or a closed candidacy's, are archived this long after the decision:
    // about one semester, so a decision can still be reviewed with the files where they were.
    'archive_after_days' => 120,

    // Which application documents are archived. Other uploads stay where they are.
    'document_types' => ['cor', 'grades'],
];
