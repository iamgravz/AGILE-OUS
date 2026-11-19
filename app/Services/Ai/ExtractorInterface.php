<?php

declare(strict_types=1);

namespace App\Services\Ai;

/** Reads one document and suggests values. It never touches the database (BR-11). */
interface ExtractorInterface
{
    /** False means AI is off, so the screen offers manual entry only. */
    public function isEnabled(): bool;

    /** Never throws because of the provider. A failure comes back as a result with no fields. */
    public function extract(string $filePath, string $mime): ExtractionResult;
}
