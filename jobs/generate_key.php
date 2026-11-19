<?php

declare(strict_types=1);

// Prints a new ENCRYPTION_KEY. Standalone, so it works before the database or .env is set up.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

echo base64_encode(random_bytes(32)), PHP_EOL;
