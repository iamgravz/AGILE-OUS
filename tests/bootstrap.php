<?php

declare(strict_types=1);

use Tests\Support\TestEnvironment;

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/Support/TestEnvironment.php';
require __DIR__ . '/Support/RouteList.php';
require __DIR__ . '/Support/TestServer.php';

TestEnvironment::prepare();
date_default_timezone_set((string) config('app.timezone'));
