<?php

declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

final class ErrorHandler
{
    private const FATAL_ERRORS = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR;
    private const DEPRECATIONS = E_DEPRECATED | E_USER_DEPRECATED;

    public static function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        // Keeps passwords and other function arguments out of stack traces and logs.
        ini_set('zend.exception_ignore_args', '1');

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if ((error_reporting() & $severity) === 0) {
            return false;
        }

        $error = new ErrorException($message, 0, $severity, $file, $line);

        // Deprecations can come from vendor code and should not take the page down.
        if (($severity & self::DEPRECATIONS) !== 0) {
            self::log($error);

            return true;
        }

        throw $error;
    }

    public static function handleException(Throwable $exception): void
    {
        self::log($exception);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        try {
            self::errorResponse($exception)->send();
        } catch (Throwable $renderFailure) {
            self::log($renderFailure);
            http_response_code(Response::HTTP_SERVER_ERROR);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Something went wrong. Please try again later.';
        }
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error !== null && ($error['type'] & self::FATAL_ERRORS) !== 0) {
            self::handleException(
                new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line'])
            );
        }
    }

    private static function errorResponse(Throwable $exception): Response
    {
        // Never in production, even if APP_DEBUG was left on.
        if (config('app.debug') === true && config('app.env') !== 'production') {
            $body = View::render('errors/debug', ['exception' => $exception], 'layouts/public');

            return Response::html($body, Response::HTTP_SERVER_ERROR);
        }

        return Response::error(Response::HTTP_SERVER_ERROR);
    }

    private static function log(Throwable $exception): void
    {
        $entry = sprintf(
            "[%s] %s: %s in %s:%d\n%s\n",
            date('Y-m-d H:i:s'),
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );

        file_put_contents(Config::basePath('storage/logs/app.log'), $entry, FILE_APPEND | LOCK_EX);
    }
}
