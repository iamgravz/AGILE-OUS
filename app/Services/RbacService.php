<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ForbiddenException;
use App\Models\PermissionRepository;

/**
 * Role-based access control. Access is denied unless a permission grants it (section 6).
 *
 * RULE FOR EVERY STEP: check permissions in two places.
 *   1. On the route: ->middleware('perm:application.decide')
 *   2. Inside the service method that does the work: authorize('application.decide');
 *
 * The route check protects the URL. The service check still protects the action when another
 * controller, fetch endpoint or future feature calls that method. Hiding a button is never protection.
 *
 * Background jobs have no logged-in user, so authorize() fails for them. Keep the check in the public
 * method that users reach, and let jobs call a separate method for the system's own work.
 *
 * Record-level rules, such as "own committee only", are extra checks in the service on top of these.
 */
final class RbacService
{
    /** @var array<int, list<string>> Permissions per user, kept for this request only. */
    private static array $cache = [];

    public function __construct(private readonly PermissionRepository $permissions = new PermissionRepository())
    {
    }

    /** @return list<string> */
    public function permissionsFor(int $userId): array
    {
        return self::$cache[$userId] ??= $this->permissions->codesForUser($userId);
    }

    public function can(string $code): bool
    {
        $userId = current_user_id();

        return $userId !== null && in_array($code, $this->permissionsFor($userId), true);
    }

    /** @throws ForbiddenException */
    public function authorize(string $code): void
    {
        if (!$this->can($code)) {
            throw new ForbiddenException($code);
        }
    }

    /** For anything that changes permissions within one request, and for tests. */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
