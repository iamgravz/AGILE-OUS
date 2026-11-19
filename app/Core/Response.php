<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public const HTTP_OK = 200;
    public const HTTP_CREATED = 201;
    public const HTTP_MOVED_PERMANENTLY = 301;
    public const HTTP_FOUND = 302;
    public const HTTP_BAD_REQUEST = 400;
    public const HTTP_FORBIDDEN = 403;
    public const HTTP_NOT_FOUND = 404;
    public const HTTP_METHOD_NOT_ALLOWED = 405;
    public const HTTP_PAYLOAD_TOO_LARGE = 413;
    public const HTTP_PAGE_EXPIRED = 419;
    public const HTTP_UNPROCESSABLE = 422;
    public const HTTP_TOO_MANY_REQUESTS = 429;
    public const HTTP_SERVER_ERROR = 500;

    /** @param array<string, string> $headers */
    public function __construct(
        private readonly string $body = '',
        private readonly int $status = self::HTTP_OK,
        private readonly array $headers = [],
        private readonly ?string $filePath = null,
    ) {
    }

    /**
     * Sends a file from disk without loading it all into memory.
     *
     * @param array<string, string> $headers
     */
    public static function file(string $path, array $headers): self
    {
        return new self('', self::HTTP_OK, $headers, $path);
    }

    /** @param array<string, string> $headers */
    public static function html(string $body, int $status = self::HTTP_OK, array $headers = []): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8'] + $headers);
    }

    /** @param array<string, string> $headers */
    public static function json(mixed $data, int $status = self::HTTP_OK, array $headers = []): self
    {
        $body = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return new self($body, $status, ['Content-Type' => 'application/json; charset=UTF-8'] + $headers);
    }

    public static function redirect(string $to, int $status = self::HTTP_FOUND): self
    {
        return new self('', $status, ['Location' => $to]);
    }

    /**
     * Renders the error page for a status code (app/Views/errors/{status}.php).
     *
     * @param array<string, string> $headers
     */
    public static function error(int $status, array $headers = []): self
    {
        return self::html(View::render("errors/{$status}", [], 'layouts/public'), $status, $headers);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        if ($this->filePath !== null) {
            readfile($this->filePath);

            return;
        }

        echo $this->body;
    }
}
