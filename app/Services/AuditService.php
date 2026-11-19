<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Models\AuditLogRepository;

final class AuditService
{
    /**
     * Records who did what, from where. Never put passwords, tokens or welfare details in $details.
     *
     * @param array<string, mixed> $details
     */
    public static function log(string $action, ?string $entityType = null, ?int $entityId = null, array $details = []): void
    {
        $request = Request::fromGlobals();

        (new AuditLogRepository())->insert([
            'user_id' => current_user_id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details_json' => $details === [] ? null : json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'ip_address' => $request->ip() ?: 'cli',
            'user_agent' => $request->userAgent(),
        ]);
    }
}
