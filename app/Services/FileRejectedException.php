<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/** An upload was refused. The message is written for the person uploading, so it is safe to show. */
final class FileRejectedException extends RuntimeException
{
}
