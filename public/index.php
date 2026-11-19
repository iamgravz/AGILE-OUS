<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Services\SessionGuard;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

ErrorHandler::register();

Dotenv::createImmutable(Config::basePath())->safeLoad();
date_default_timezone_set((string) config('app.timezone'));

header_remove('X-Powered-By');
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

$request = Request::fromGlobals();

if ($request->isSecure()) {
    header('Strict-Transport-Security: max-age=31536000');
}

// A proxy may end HTTPS before this server, so an https APP_URL also asks for a Secure cookie.
Session::start($request->isSecure() || str_starts_with((string) config('app.url'), 'https://'));
SessionGuard::check();

$router = new Router();
Router::setInstance($router);
(require Config::basePath('config/routes.php'))($router);

$router->dispatch($request)->send();
