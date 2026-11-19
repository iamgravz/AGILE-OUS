<?php

declare(strict_types=1);

use App\Core\Config;
use Dotenv\Dotenv;

if (PHP_SAPI !== 'cli') {
    exit("This script can only be run from the command line.\n");
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

Dotenv::createImmutable(Config::basePath())->safeLoad();
date_default_timezone_set((string) config('app.timezone'));
