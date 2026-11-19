<?php

declare(strict_types=1);

namespace App\Services\Ai;

final class ExtractorFactory
{
    /** Anything other than a known provider means off, so a typo in .env can never send a document out. */
    public static function make(): ExtractorInterface
    {
        return match (config('app.ai.provider')) {
            'anthropic' => new ClaudeExtractor((string) config('app.ai.api_key'), (string) config('app.ai.model'), config('app.ai.base_url') ?: null),
            default => new NullExtractor(),
        };
    }
}
