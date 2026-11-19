<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\CsrfMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Services\AuditService;
use RuntimeException;

final class Router
{
    private static ?self $instance = null;

    /** @var list<array{method: string, path: string, pattern: string, action: string, middleware: list<string>}> */
    private array $routes = [];

    /** @var array<string, string> */
    private array $named = [];

    private string $groupPrefix = '';

    /** @var list<string> */
    private array $groupMiddleware = [];

    public static function setInstance(self $router): void
    {
        self::$instance = $router;
    }

    public static function instance(): self
    {
        return self::$instance ?? throw new RuntimeException('Router has not been set up.');
    }

    public function get(string $path, string $action, ?string $name = null): self
    {
        $this->add('GET', $path, $action, $name);

        return $this;
    }

    public function post(string $path, string $action, ?string $name = null): self
    {
        $this->add('POST', $path, $action, $name);

        return $this;
    }

    /** Adds middleware to the route just declared: a class name, or 'perm:code' for a permission check. */
    public function middleware(string ...$middleware): self
    {
        $last = array_key_last($this->routes) ?? throw new RuntimeException('No route to add middleware to.');
        array_push($this->routes[$last]['middleware'], ...$middleware);

        return $this;
    }

    /**
     * Routes added inside $routes share the prefix and middleware. Groups can be nested.
     *
     * @param array{prefix?: string, middleware?: list<string>} $options
     */
    public function group(array $options, callable $routes): void
    {
        $prefix = $this->groupPrefix;
        $middleware = $this->groupMiddleware;

        $this->groupPrefix .= '/' . trim($options['prefix'] ?? '', '/');
        $this->groupMiddleware = array_merge($middleware, $options['middleware'] ?? []);

        $routes($this);

        $this->groupPrefix = $prefix;
        $this->groupMiddleware = $middleware;
    }

    /** @param array<string, string|int> $params */
    public function urlFor(string $name, array $params = []): string
    {
        $path = $this->named[$name] ?? throw new RuntimeException("Unknown route name: {$name}");

        $url = preg_replace_callback('/\{(\w+)\}/', static function (array $match) use ($params, $name): string {
            if (!array_key_exists($match[1], $params)) {
                throw new RuntimeException("Route {$name} needs parameter {$match[1]}.");
            }

            return rawurlencode((string) $params[$match[1]]);
        }, $path);

        return (string) $url;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method() === 'HEAD' ? 'GET' : $request->method();
        $allowed = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['pattern'], $request->path(), $matches) !== 1) {
                continue;
            }

            if ($route['method'] !== $method) {
                $allowed[] = $route['method'];
                continue;
            }

            $params = array_map('rawurldecode', array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));

            return $this->run($route, $request, array_values($params));
        }

        if ($allowed !== []) {
            return Response::error(Response::HTTP_METHOD_NOT_ALLOWED, ['Allow' => implode(', ', $allowed)]);
        }

        return Response::error(Response::HTTP_NOT_FOUND);
    }

    private function add(string $method, string $path, string $action, ?string $name): void
    {
        $full = '/' . trim($this->groupPrefix . '/' . trim($path, '/'), '/');
        $full = (string) preg_replace('#/+#', '/', $full);

        $pattern = (string) preg_replace('/\\\\\{(\w+)\\\\\}/', '(?P<$1>[^/]+)', preg_quote($full, '#'));

        $this->routes[] = [
            'method' => $method,
            'path' => $full,
            'pattern' => "#^{$pattern}$#",
            'action' => $action,
            // Added here so that no POST route can be declared without it.
            'middleware' => $method === 'POST' ? [CsrfMiddleware::class, ...$this->groupMiddleware] : $this->groupMiddleware,
        ];

        if ($name !== null) {
            $this->named[$name] = $full;
        }
    }

    /**
     * @param array{action: string, middleware: list<string>} $route
     * @param list<string> $params
     */
    private function run(array $route, Request $request, array $params): Response
    {
        $next = fn (Request $request): Response => $this->callAction($route['action'], $request, $params);

        foreach (array_reverse($route['middleware']) as $middlewareClass) {
            $next = fn (Request $request): Response => $this->makeMiddleware($middlewareClass)->handle($request, $next);
        }

        try {
            return $next($request);
        } catch (ForbiddenException $exception) {
            return $this->denied($request, $exception);
        }
    }

    private function makeMiddleware(string $middleware): object
    {
        return str_starts_with($middleware, 'perm:')
            ? new PermissionMiddleware(substr($middleware, 5))
            : new $middleware();
    }

    private function denied(Request $request, ForbiddenException $exception): Response
    {
        AuditService::log('access.denied', null, null, [
            'route' => $request->path(),
            'method' => $request->method(),
            'permission' => $exception->permission,
        ]);

        // TC-15: attempts on confidential welfare records can be found on their own.
        if (str_starts_with($exception->permission, 'welfare.') || $exception->permission === 'file.welfare_attachment') {
            AuditService::log('welfare.access_denied', null, null, [
                'route' => $request->path(),
                'method' => $request->method(),
                'permission' => $exception->permission,
            ]);
        }

        return $request->isAjax()
            ? Response::json(['error' => 'You do not have permission to do this.'], Response::HTTP_FORBIDDEN)
            : Response::error(Response::HTTP_FORBIDDEN);
    }

    /** @param list<string> $params */
    private function callAction(string $action, Request $request, array $params): Response
    {
        [$controller, $method] = explode('@', $action, 2);
        $class = "App\\Controllers\\{$controller}";

        if (!method_exists($class, $method)) {
            throw new RuntimeException("Route action not found: {$action}");
        }

        return (new $class())->$method($request, ...$params);
    }
}
