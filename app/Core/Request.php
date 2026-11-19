<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, mixed> $files
     * @param array<string, mixed> $server
     */
    public function __construct(
        private readonly array $query,
        private readonly array $body,
        private readonly array $files,
        private readonly array $server,
    ) {
    }

    public static function fromGlobals(): self
    {
        $body = $_POST;

        if (str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            $body = is_array($decoded) ? $decoded : [];
        }

        return new self($_GET, $body, $_FILES, $_SERVER);
    }

    public function method(): string
    {
        return strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    /** Request path without the query string or a trailing slash, always starting with "/". */
    public function path(): string
    {
        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');
        $path = explode('?', $uri, 2)[0];

        return '/' . trim($path, '/');
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /** A body value as a string. Arrays sent by a tampered form become an empty string. */
    public function text(string $key, bool $trim = true): string
    {
        $value = $this->body[$key] ?? '';

        if (!is_string($value)) {
            return '';
        }

        return $trim ? trim($value) : $value;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->body;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /** A query value as a trimmed string. Arrays from a tampered URL become an empty string. */
    public function queryText(string $key): string
    {
        $value = $this->query[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    /** The path and query string, always within this site. */
    public function pathWithQuery(): string
    {
        $query = (string) parse_url((string) ($this->server['REQUEST_URI'] ?? '/'), PHP_URL_QUERY);

        return $this->path() . ($query === '' ? '' : '?' . $query);
    }

    public function files(string $key): mixed
    {
        return $this->files[$key] ?? null;
    }

    /** PHP discards the whole form, including its CSRF token, when a POST exceeds post_max_size. */
    public function exceedsPostLimit(): bool
    {
        $limit = ini_parse_quantity((string) ini_get('post_max_size'));
        $length = (int) ($this->server['CONTENT_LENGTH'] ?? 0);

        return $limit > 0 && $length > $limit;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return isset($this->server[$key]) ? (string) $this->server[$key] : null;
    }

    /** The connecting address. Forwarded headers are ignored because clients can forge them. */
    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '');
    }

    public function userAgent(): string
    {
        return substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('X-Requested-With')) === 'xmlhttprequest'
            || str_contains((string) $this->header('Accept'), 'application/json');
    }

    public function isSecure(): bool
    {
        $https = (string) ($this->server['HTTPS'] ?? '');

        return $https !== '' && strtolower($https) !== 'off';
    }
}
