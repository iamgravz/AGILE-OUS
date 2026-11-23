<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Models\UserRepository;
use App\Services\RbacService;

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Absolute URL built from APP_URL, for links that leave the site such as emails. */
function url(string $path = ''): string
{
    return rtrim((string) config('app.url'), '/') . '/' . ltrim($path, '/');
}

/** Root-relative URL of a named route. */
function route(string $name, array $params = []): string
{
    return Router::instance()->urlFor($name, $params);
}

/**
 * Root-relative so the CSP 'self' check passes on any host name used in development. The file's change time is added
 * as ?v=, so browsers may cache assets for a long time (public/.htaccess) and still get a changed file at once.
 */
function asset(string $path): string
{
    [$path, $fragment] = array_pad(explode('#', ltrim($path, '/'), 2), 2, null);
    $file = Config::basePath('public/assets/' . $path);
    $version = !str_contains($path, '?') && is_file($file) ? (int) filemtime($file) : 0;

    return '/assets/' . $path . ($version > 0 ? "?v={$version}" : '') . ($fragment !== null ? "#{$fragment}" : '');
}

function redirect(string $to, int $status = Response::HTTP_FOUND): Response
{
    return Response::redirect($to, $status);
}

/** Input flashed under '_old_input' by the previous request, used to refill forms. */
function old(string $key, mixed $default = ''): mixed
{
    $input = Session::getFlash('_old_input', []);

    return $input[$key] ?? $default;
}

/** Stores a value for the next request when $value is given, otherwise reads the current one. */
function flash(string $key, mixed $value = null): mixed
{
    if ($value === null) {
        return Session::getFlash($key);
    }

    Session::flash($key, $value);

    return $value;
}

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return sprintf('<input type="hidden" name="%s" value="%s">', e(Csrf::FIELD), e(Csrf::token()));
}

/** Passwordless seeded login: local mode and this computer only, so a server left on APP_ENV=local still refuses it. */
function dev_tools_allowed(): bool
{
    return config('app.env') === 'local' && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
}

/** The inline validation error flashed for a form field by the previous request. */
/** @param array<string, mixed> $document */
function application_file_url(array $document): string
{
    return current_user_id() !== null
        ? route('files.show', ['type' => 'application_document', 'id' => $document['document_id']])
        : route('apply.document_file', ['id' => $document['document_id']]);
}

function field_error(string $key): ?string
{
    return Session::getFlash('_errors', [])[$key] ?? null;
}

function current_user_id(): ?int
{
    $id = Session::get('user_id');

    return $id === null ? null : (int) $id;
}

/** The logged-in user with role_slug and role_name, loaded once per request. */
function current_user(): ?array
{
    static $loaded = [];

    $id = current_user_id();

    if ($id === null) {
        return null;
    }

    return $loaded[$id] ??= (new UserRepository())->findWithRole($id);
}

function can(string $code): bool
{
    return (new RbacService())->can($code);
}

/** @throws \App\Core\ForbiddenException */
function authorize(string $code): void
{
    (new RbacService())->authorize($code);
}

/**
 * For service methods reached through several permissions. On the web one of them is required. Scheduled jobs run
 * from the command line with nobody signed in, so only that case passes without one.
 *
 * @throws \App\Core\ForbiddenException
 */
function authorize_any(string ...$codes): void
{
    if (PHP_SAPI === 'cli' && current_user_id() === null) {
        return;
    }

    foreach ($codes as $code) {
        if (can($code)) {
            return;
        }
    }

    throw new \App\Core\ForbiddenException($codes[0]);
}

/** Settings wins, so the MSW Committee Head can change it. The .env value is the fallback. */
function session_idle_minutes(): int
{
    return (int) ((new \App\Models\SettingRepository())->value('session.idle_minutes') ?: config('app.session_idle_minutes'));
}

/** The contact email from Settings. The .env value is the fallback. */
function org_contact_email(): string
{
    return (string) ((new \App\Models\SettingRepository())->value('org.contact_email') ?: config('app.contact_email'));
}

/** The public URL of an image stored in the media folder. */
function media_url(string $storedPath): string
{
    return '/media/' . pathinfo($storedPath, PATHINFO_FILENAME);
}

/** Color and text together. An unknown status still shows its name, in the neutral tone. */
/** Philippine date format, such as October 31, 2026. */
function date_ph(string $value): string
{
    return date('F j, Y', (int) strtotime($value));
}

/** Philippine time format, such as 2:30 PM. */
function time_ph(string $value): string
{
    return date('g:i A', (int) strtotime($value));
}

/** October 31, 2026, 2:30 PM */
function datetime_ph(string $value): string
{
    return date_ph($value) . ', ' . time_ph($value);
}

/** How long ago, such as "2 min ago". Falls back to the date after a week. */
function time_ago(string $value): string
{
    $seconds = time() - (int) strtotime($value);

    return match (true) {
        $seconds < 60 => 'Just now',
        $seconds < 3600 => (int) ($seconds / 60) . ' min ago',
        $seconds < 86400 => (int) ($seconds / 3600) . ' hr ago',
        $seconds < 604800 => (int) ($seconds / 86400) . ((int) ($seconds / 86400) === 1 ? ' day ago' : ' days ago'),
        default => date_ph($value),
    };
}

/** A colored circle with up to two initials. The same name always gets the same color. Decorative: the name is next to it. */
function initials_avatar(string $name): string
{
    $words = preg_split('/\s+/', trim(preg_replace('/[^\p{L}\p{N}\s]/u', '', $name) ?? '')) ?: [];
    $letters = '';

    foreach (array_slice(array_values(array_filter($words)), 0, 2) as $word) {
        $letters .= mb_strtoupper(mb_substr($word, 0, 1));
    }

    return '<span class="avatar avatar--initials avatar--tone-' . (abs(crc32($name)) % 4) . '" aria-hidden="true">' . e($letters) . '</span>';
}

/** A status name in sentence case. */
function status_label(string $status): string
{
    $label = config("statuses.{$status}")[0] ?? str_replace('_', ' ', $status);

    return ucfirst(mb_strtolower($label));
}

function status_badge(string $status): string
{
    $label = status_label($status);
    $tone = config("statuses.{$status}")[1] ?? 'neutral';

    return sprintf('<span class="badge badge--%s">%s</span>', e($tone), e($label));
}

/** Decorative icon from the sprite. Pair it with text, or give the parent an aria-label. */
function icon(string $name, string $class = ''): string
{
    return sprintf(
        '<svg class="%s" aria-hidden="true" focusable="false"><use href="%s#%s"></use></svg>',
        e(trim("icon {$class}")),
        e(asset('img/icons.svg')),
        e($name)
    );
}

/** How many things wait behind a sidebar item. */
function sidebar_badge(string $item): int
{
    return match ($item) {
        'accounts' => count((new \App\Models\UserRepository())->pendingAccounts()),
        'credential_requests' => (new \App\Services\CredentialRequestService())->openCount(),
        default => (new \App\Services\CredentialService())->pendingCount(),
    };
}

/** config/menu.php filtered for the current user. Groups left with no items are dropped. */
function sidebar_menu(): array
{
    $menu = [];

    foreach (config('menu.dashboard', []) as $group) {
        $group['items'] = array_values(array_filter(
            $group['items'],
            static fn (array $item): bool => $item['permission'] === null ? current_user_id() !== null : can($item['permission'])
        ));

        if ($group['items'] !== []) {
            $menu[] = $group;
        }
    }

    return $menu;
}

function is_current_path(string $path): bool
{
    return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) === $path;
}

function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? null;

    if ($value === null) {
        return $default;
    }

    return match (is_string($value) ? strtolower($value) : $value) {
        'true' => true,
        'false' => false,
        'null' => null,
        default => $value,
    };
}
