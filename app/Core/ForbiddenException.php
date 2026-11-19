<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** Thrown by authorize(). The router turns it into a 403 response and an access.denied audit row. */
final class ForbiddenException extends RuntimeException
{
    public function __construct(public readonly string $permission)
    {
        parent::__construct("Permission required: {$permission}");
    }
}
