<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/** A submit found missing details. The wizard returns to $step and highlights $errors. */
final class ApplicationIncompleteException extends RuntimeException
{
    /** @param array<string, string> $errors */
    public function __construct(public readonly int $step, public readonly array $errors)
    {
        parent::__construct('The application is incomplete.');
    }
}
