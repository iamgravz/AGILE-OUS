<?php

declare(strict_types=1);

namespace App\Services;

use GdImage;

final class ImageService
{
    // Decoding uses about 5 bytes per pixel, so this keeps one image well under PHP's memory limit.
    private const MAX_PIXELS = 24_000_000;
    private const JPEG_QUALITY = 85;

    /**
     * Center-crops an image to a square JPEG. Re-encoding also drops EXIF data such as GPS location.
     *
     * @throws FileRejectedException
     */
    public function squareJpeg(string $path, int $size): string
    {
        $info = getimagesize($path);

        if ($info === false) {
            throw new FileRejectedException('We could not read this image. Please try another file.');
        }

        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            throw new FileRejectedException('This image has too many pixels. Please use a smaller photo.');
        }

        $source = @imagecreatefromstring((string) file_get_contents($path));

        if ($source === false) {
            throw new FileRejectedException('We could not read this image. Please try another file.');
        }

        $source = $this->applyOrientation($source, $path, $info[2]);
        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);

        $square = imagecreatetruecolor($size, $size);
        // Transparent PNG areas become white, because JPEG has no transparency.
        imagefill($square, 0, 0, imagecolorallocate($square, 255, 255, 255));
        imagecopyresampled($square, $source, 0, 0, (int) (($width - $side) / 2), (int) (($height - $side) / 2), $size, $size, $side, $side);

        ob_start();
        imagejpeg($square, null, self::JPEG_QUALITY);

        return (string) ob_get_clean();
    }

    /** Phones store photos sideways with an EXIF flag, so that flag is applied before it is dropped. */
    private function applyOrientation(GdImage $image, string $path, int $imageType): GdImage
    {
        if ($imageType !== IMAGETYPE_JPEG || !function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated === false ? $image : $rotated;
    }
}
