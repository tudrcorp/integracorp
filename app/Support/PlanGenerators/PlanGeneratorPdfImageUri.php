<?php

declare(strict_types=1);

namespace App\Support\PlanGenerators;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

final class PlanGeneratorPdfImageUri
{
    private const CACHE_PREFIX = 'plan_generator_pdf_image_uri:v1:';

    public static function forPublicPath(?string $relativePath): string
    {
        if (! filled($relativePath)) {
            return '';
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($relativePath)) {
            return '';
        }

        $absolutePath = $disk->path($relativePath);
        $mtime = is_file($absolutePath) ? (int) @filemtime($absolutePath) : 0;
        $size = is_file($absolutePath) ? (int) @filesize($absolutePath) : 0;

        if ($mtime === 0 || $size === 0) {
            return '';
        }

        $cacheKey = self::CACHE_PREFIX.hash('sha256', $relativePath.'|'.$mtime.'|'.$size);

        return Cache::remember(
            $cacheKey,
            60 * 60 * 24 * 7,
            static fn (): string => self::buildDataUri($absolutePath),
        );
    }

    private static function buildDataUri(string $absolutePath): string
    {
        $optimized = self::optimizeBinary($absolutePath);

        if ($optimized !== null) {
            return 'data:image/jpeg;base64,'.base64_encode($optimized);
        }

        $raw = @file_get_contents($absolutePath);

        if ($raw === false || $raw === '') {
            return '';
        }

        $extension = strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION));

        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return 'data:'.$mime.';base64,'.base64_encode($raw);
    }

    private static function optimizeBinary(string $absolutePath): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $info = @getimagesize($absolutePath);

        if ($info === false) {
            return null;
        }

        $width = (int) ($info[0] ?? 0);
        $height = (int) ($info[1] ?? 0);
        $type = (int) ($info[2] ?? 0);
        $fileSize = is_file($absolutePath) ? (int) @filesize($absolutePath) : 0;
        $maxWidth = max(800, (int) config('plan-generator.pdf_image_max_width', 1240));

        if ($width <= 0 || $height <= 0) {
            return null;
        }

        if ($width <= $maxWidth && $fileSize <= 512_000) {
            return null;
        }

        $source = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($absolutePath),
            IMAGETYPE_PNG => @imagecreatefrompng($absolutePath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolutePath) : false,
            default => false,
        };

        if ($source === false) {
            return null;
        }

        $targetWidth = $width;
        $targetHeight = $height;

        if ($width > $maxWidth) {
            $targetWidth = $maxWidth;
            $targetHeight = (int) round($height * ($targetWidth / $width));
        }

        if ($targetWidth !== $width) {
            $resized = imagescale($source, $targetWidth, $targetHeight, IMG_BILINEAR_FIXED);
            imagedestroy($source);

            if ($resized === false) {
                return null;
            }

            $source = $resized;
        }

        ob_start();
        imagejpeg($source, null, 82);
        imagedestroy($source);

        $binary = ob_get_clean();

        return is_string($binary) && $binary !== '' ? $binary : null;
    }
}
