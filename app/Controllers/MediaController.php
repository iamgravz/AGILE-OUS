<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\FileStorageService;

/** Public images for content posts. Only the "media" folder is reachable, by its random 128-bit id. */
final class MediaController
{
    public function show(Request $request, string $id): Response
    {
        $files = new FileStorageService();
        $storedPath = $files->findMedia($id);

        return $storedPath === null
            ? Response::error(Response::HTTP_NOT_FOUND)
            : $files->stream($storedPath, basename($storedPath), true);
    }
}
