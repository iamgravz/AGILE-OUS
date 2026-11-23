<?php

declare(strict_types=1);

// How digital IDs and certificates are made.
// 'manual': a member requests one, and the MSW Committee Head uploads the file made from AGILE's own template
//           (Administration, ID and certificate requests). The system makes no PDFs of its own.
// 'generated': the system draws them from credentials/id_card and credentials/certificate, for approval.
return [
    'mode' => (string) env('CREDENTIAL_MODE', 'manual'),

    // What the MSW Committee Head may upload.
    'upload_types' => ['pdf', 'jpg', 'jpeg', 'png'],
];
