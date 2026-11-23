<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Config;
use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * A real web server on a spare local port, with its own rebuilt database. One server per test run.
 * Each instance of this class is one browser: it keeps its own session cookie.
 */
final class TestServer
{
    private static mixed $process = null;
    private static string $base = '';
    private static ?PDO $db = null;

    private string $cookie = '';
    public string $csrf = '';

    /** @var array<string, string> Response headers of the last request, lower-case names. */
    public array $headers = [];

    public function __construct()
    {
        self::start();
    }

    /** A browser logged in as this person, with the sample password. */
    public static function loggedIn(string $email, string $password = 'Password123!'): self
    {
        $browser = new self();
        $browser->get('/login');
        $response = $browser->post('/login', ['email' => $email, 'password' => $password]);

        if (($response['location'] ?? '') === '' || str_contains($response['location'], '/login')) {
            throw new RuntimeException("Could not log in as {$email}.");
        }

        // Logging in changes the token, so the next page supplies the new one.
        $browser->follow($response);

        return $browser;
    }

    public static function db(): PDO
    {
        self::start();

        return self::$db;
    }

    /** @return array{status: int, location: string, body: string} */
    public function get(string $path): array
    {
        return $this->send('GET', $path, '', []);
    }

    /** @param array<string, string> $data The token from the last page is added, unless $data gives its own. */
    public function post(string $path, array $data = [], bool $withToken = true): array
    {
        if ($withToken && !array_key_exists('_token', $data)) {
            if ($this->csrf === '') {
                $this->get('/privacy');
            }

            $data['_token'] = $this->csrf;
        }

        return $this->send('POST', $path, http_build_query($data), ['Content-Type: application/x-www-form-urlencoded']);
    }

    /** @param array<string, string> $data */
    public function upload(string $path, array $data, string $field, string $name, string $contents, string $type): array
    {
        if ($this->csrf === '') {
            $this->get('/privacy');
        }

        $boundary = '----agile' . bin2hex(random_bytes(8));
        $body = '';

        foreach ($data + ['_token' => $this->csrf] as $key => $value) {
            $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$key}\"\r\n\r\n{$value}\r\n";
        }

        $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$field}\"; filename=\"{$name}\"\r\nContent-Type: {$type}\r\n\r\n{$contents}\r\n--{$boundary}--\r\n";

        return $this->send('POST', $path, $body, ["Content-Type: multipart/form-data; boundary={$boundary}"]);
    }

    /**
     * Several files in one form, as field => [file name, contents, type].
     *
     * @param array<string, string> $data
     * @param array<string, array{0: string, 1: string, 2: string}> $files
     * @return array{status: int, location: string, body: string}
     */
    public function uploadFiles(string $path, array $data, array $files): array
    {
        if ($this->csrf === '') {
            $this->get('/privacy');
        }

        $boundary = '----agile' . bin2hex(random_bytes(8));
        $body = '';

        foreach ($data + ['_token' => $this->csrf] as $key => $value) {
            $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$key}\"\r\n\r\n{$value}\r\n";
        }

        foreach ($files as $field => [$name, $contents, $type]) {
            $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$field}\"; filename=\"{$name}\"\r\nContent-Type: {$type}\r\n\r\n{$contents}\r\n";
        }

        return $this->send('POST', $path, $body . "--{$boundary}--\r\n", ["Content-Type: multipart/form-data; boundary={$boundary}"]);
    }

    /** @return array{status: int, location: string, body: string} */
    public function follow(array $response): array
    {
        for ($i = 0; $i < 5 && $response['location'] !== ''; $i++) {
            $response = $this->get((string) preg_replace('#^https?://[^/]+#', '', $response['location']));
        }

        return $response;
    }

    /**
     * @param list<string> $headers
     * @return array{status: int, location: string, body: string}
     */
    private function send(string $method, string $path, string $body, array $headers): array
    {
        if ($this->cookie !== '') {
            $headers[] = "Cookie: {$this->cookie}";
        }

        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => $headers,
            'content' => $body,
            'ignore_errors' => true,
            'follow_location' => 0,
            'timeout' => 60,
        ]]);

        // PHP's single-process server can refuse a connection now and then on Windows.
        for ($try = 0; $try < 5; $try++) {
            $responseBody = @file_get_contents(self::$base . $path, false, $context);

            if ($responseBody !== false) {
                break;
            }

            usleep(200000);
        }

        if ($responseBody === false) {
            throw new RuntimeException("No response from {$method} {$path}.");
        }

        $this->headers = [];
        $status = 0;

        foreach ($http_response_header as $line) {
            if (preg_match('#^HTTP/\S+ (\d+)#', $line, $match) === 1) {
                $status = (int) $match[1];
            } elseif (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $this->headers[strtolower($name)] = trim($value);

                if (strtolower($name) === 'set-cookie' && str_starts_with(trim($value), 'agile_session=')) {
                    $this->cookie = explode(';', trim($value))[0];
                    $this->headers['session-cookie'] = trim($value);
                }
            }
        }

        if (preg_match('/name="_token" value="([^"]+)"/', $responseBody, $match) === 1) {
            $this->csrf = $match[1];
        }

        return ['status' => $status, 'location' => $this->headers['location'] ?? '', 'body' => $responseBody];
    }

    private static function start(): void
    {
        if (self::$process !== null) {
            return;
        }

        $database = TestEnvironment::secondDatabase();
        self::$db = Database::connect($database);

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        self::$process = proc_open(
            [PHP_BINARY, '-d', 'variables_order=EGPCS', '-S', $address, '-t', Config::basePath('public')],
            [0 => ['file', $null, 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']],
            $pipes,
            Config::basePath(),
            TestEnvironment::childEnvironment($database),
        );

        if (!is_resource(self::$process)) {
            throw new RuntimeException('Could not start the test web server.');
        }

        register_shutdown_function(static function (): void {
            if (is_resource(self::$process)) {
                proc_terminate(self::$process);
            }
        });

        self::$base = "http://{$address}";

        for ($i = 0; $i < 50; $i++) {
            $connection = @fsockopen('127.0.0.1', (int) substr(strrchr($address, ':'), 1), $code, $message, 0.2);

            if ($connection !== false) {
                fclose($connection);

                return;
            }

            usleep(100000);
        }

        throw new RuntimeException('The test web server did not start.');
    }
}
