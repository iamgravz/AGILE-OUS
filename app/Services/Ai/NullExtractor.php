<?php

declare(strict_types=1);

namespace App\Services\Ai;

/** The default, AI_PROVIDER=none. Sends nothing anywhere. */
final class NullExtractor implements ExtractorInterface
{
    public function isEnabled(): bool
    {
        return false;
    }

    public function extract(string $filePath, string $mime): ExtractionResult
    {
        return new ExtractionResult();
    }
}
