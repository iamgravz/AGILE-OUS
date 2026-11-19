<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Response;
use App\Models\SettingRepository;
use InvalidArgumentException;
use RuntimeException;

/** All uploads live in storage/uploads, outside public/, and leave only through FileController. */
final class FileStorageService
{
    private const DEFAULT_MAX_MB = 5;

    private const MIME_TYPES = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
    ];

    // Browsers may display these. Anything else always downloads.
    private const INLINE_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    public function __construct(private readonly SettingRepository $settings = new SettingRepository())
    {
    }

    /**
     * @param array<string, mixed> $uploadedFile One entry of $_FILES.
     * @param list<string> $allowedTypes
     * @return array{stored_path: string, original_name: string, mime_type: string, size_bytes: int}
     * @throws FileRejectedException
     */
    public function store(array $uploadedFile, string $folder, array $allowedTypes = ['pdf', 'jpg', 'jpeg', 'png'], ?int $maxMb = null): array
    {
        $file = $this->validate($uploadedFile, $allowedTypes, $maxMb);
        $storedPath = $this->newPath($folder, $file['extension']);

        if (!move_uploaded_file($uploadedFile['tmp_name'], $this->path($storedPath))) {
            throw new RuntimeException('Could not save an uploaded file.');
        }

        return [
            'stored_path' => $storedPath,
            'original_name' => $file['original_name'],
            'mime_type' => $file['mime_type'],
            'size_bytes' => $file['size_bytes'],
        ];
    }

    /**
     * Runs every upload check without saving, for callers that transform the file first.
     *
     * @param array<string, mixed> $uploadedFile
     * @param list<string> $allowedTypes
     * @return array{extension: string, original_name: string, mime_type: string, size_bytes: int}
     * @throws FileRejectedException
     */
    public function validate(array $uploadedFile, array $allowedTypes = ['pdf', 'jpg', 'jpeg', 'png'], ?int $maxMb = null): array
    {
        $maxMb ??= $this->maxUploadMb();
        $error = $uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE;

        if (!is_int($error)) {
            throw new FileRejectedException('Please choose one file.');
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new FileRejectedException($this->uploadErrorMessage($error, $maxMb));
        }

        if (!is_uploaded_file((string) $uploadedFile['tmp_name'])) {
            throw new FileRejectedException('The upload could not be read. Please try again.');
        }

        $extension = strtolower(pathinfo((string) $uploadedFile['name'], PATHINFO_EXTENSION));
        $allowedLabel = strtoupper(implode(', ', array_diff($allowedTypes, ['jpeg'])));

        if (!in_array($extension, $allowedTypes, true) || !isset(self::MIME_TYPES[$extension])) {
            throw new FileRejectedException("This file type is not allowed. Please upload one of: {$allowedLabel}.");
        }

        $size = (int) filesize($uploadedFile['tmp_name']);

        if ($size === 0) {
            throw new FileRejectedException('This file is empty.');
        }

        if ($size > $maxMb * 1024 * 1024) {
            throw new FileRejectedException("This file is too large. The limit is {$maxMb} MB.");
        }

        $mimeType = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($uploadedFile['tmp_name']);

        if (!in_array($mimeType, self::MIME_TYPES[$extension], true)) {
            throw new FileRejectedException("The contents of this file do not match its .{$extension} extension. Please upload a real {$allowedLabel} file.");
        }

        return [
            'extension' => $extension,
            'original_name' => $this->cleanName((string) $uploadedFile['name']),
            'mime_type' => $mimeType,
            'size_bytes' => $size,
        ];
    }

    /** Saves content the application made itself, such as a resized photo. Returns its stored path. */
    public function storeContents(string $contents, string $folder, string $extension): string
    {
        $storedPath = $this->newPath($folder, $extension);

        if (file_put_contents($this->path($storedPath), $contents, LOCK_EX) === false) {
            throw new RuntimeException('Could not save a generated file.');
        }

        return $storedPath;
    }

    /** The absolute path of a stored file. Refuses anything that would leave storage/uploads. */
    public function path(string $storedPath): string
    {
        if (preg_match('#^[a-z0-9_-]+(/[a-z0-9_-]+)*/[a-f0-9]{32}\.[a-z0-9]+$#', $storedPath) !== 1) {
            throw new InvalidArgumentException('Invalid stored file path.');
        }

        return Config::basePath('storage/uploads/' . $storedPath);
    }

    /** The stored path of a public image, from the id in its /media/{id} URL. */
    public function findMedia(string $id): ?string
    {
        if (preg_match('/^[a-f0-9]{32}$/', $id) !== 1) {
            return null;
        }

        $root = Config::basePath('storage/uploads/');
        $matches = glob($root . "media/*/*/{$id}.*") ?: [];

        return $matches === [] ? null : str_replace('\\', '/', substr($matches[0], strlen($root)));
    }

    public function delete(string $storedPath): void
    {
        $path = $this->path($storedPath);

        if (is_file($path)) {
            unlink($path);
        }
    }

    /** $framable relaxes the site's "no frames" headers for this one response, within this site only. */
    public function stream(string $storedPath, string $downloadName, bool $inline = false, bool $framable = false): Response
    {
        return $this->streamPath($this->path($storedPath), $downloadName, $inline, $framable);
    }

    /** For a file the caller has already located and checked. */
    public function streamPath(string $path, string $downloadName, bool $inline = false, bool $framable = false): Response
    {
        if (!is_file($path)) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        $mimeType = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $disposition = $inline && in_array($mimeType, self::INLINE_MIME_TYPES, true) ? 'inline' : 'attachment';

        $frameHeaders = !$framable ? [] : [
            'X-Frame-Options' => 'SAMEORIGIN',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:; object-src 'self'; frame-ancestors 'self'",
        ];

        return Response::file($path, $this->downloadHeaders($mimeType, (int) filesize($path), $disposition, $downloadName) + $frameHeaders);
    }

    /** For a file decrypted in memory. It always downloads. */
    public function streamContents(string $contents, string $mimeType, string $downloadName): Response
    {
        return new Response($contents, Response::HTTP_OK, $this->downloadHeaders($mimeType, strlen($contents), 'attachment', $downloadName));
    }

    /** @return array<string, string> */
    private function downloadHeaders(string $mimeType, int $size, string $disposition, string $downloadName): array
    {
        $name = $this->cleanName($downloadName);
        $asciiName = (string) preg_replace('/[^A-Za-z0-9._-]+/', '_', $name);

        return [
            'Content-Type' => $mimeType,
            'Content-Length' => (string) $size,
            'Content-Disposition' => sprintf('%s; filename="%s"; filename*=UTF-8\'\'%s', $disposition, $asciiName, rawurlencode($name)),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
        ];
    }

    public function maxUploadMb(): int
    {
        $value = (int) $this->settings->value('upload.max_mb', (string) self::DEFAULT_MAX_MB);

        return $value > 0 ? $value : self::DEFAULT_MAX_MB;
    }

    private function newPath(string $folder, string $extension): string
    {
        $relativeFolder = trim($folder, '/') . date('/Y/m');
        $storedPath = $relativeFolder . '/' . bin2hex(random_bytes(16)) . '.' . $extension;
        $directory = dirname($this->path($storedPath));

        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create the upload folder.');
        }

        return $storedPath;
    }

    /** For display and download names only. Stored files never use this name. */
    private function cleanName(string $name): string
    {
        $base = basename(str_replace('\\', '/', $name));
        $clean = (string) preg_replace('/[\x00-\x1F\x7F"]/u', '', $base);

        return mb_substr($clean, -255) ?: 'file';
    }

    private function uploadErrorMessage(int $error, int $maxMb): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "This file is too large. The limit is {$maxMb} MB.",
            UPLOAD_ERR_NO_FILE => 'Please choose a file.',
            UPLOAD_ERR_PARTIAL => 'The upload did not finish. Please try again.',
            default => 'The file could not be uploaded. Please try again.',
        };
    }
}
