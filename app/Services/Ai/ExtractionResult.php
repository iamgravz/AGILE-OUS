<?php

declare(strict_types=1);

namespace App\Services\Ai;

final class ExtractionResult
{
    /**
     * @param array<string, mixed> $fields Empty when nothing was extracted.
     * @param string|null $failure Why, for the audit log. Holds no document content.
     */
    public function __construct(
        public readonly array $fields = [],
        public readonly ?float $confidence = null,
        public readonly ?string $failure = null,
    ) {
    }

    public function succeeded(): bool
    {
        return $this->failure === null && $this->fields !== [];
    }
}
