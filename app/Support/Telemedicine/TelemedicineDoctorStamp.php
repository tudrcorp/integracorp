<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use Illuminate\Support\Facades\Storage;

final class TelemedicineDoctorStamp
{
    public static function dataUri(mixed $signature): string
    {
        $value = trim((string) ($signature ?? ''));
        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, 'data:image')) {
            return $value;
        }

        $relative = ltrim($value, '/');
        if (str_starts_with($relative, 'storage/')) {
            $relative = substr($relative, strlen('storage/'));
        }

        $candidates = [];

        try {
            $candidates[] = Storage::disk('public')->path($relative);
        } catch (\Throwable) {
        }

        if (function_exists('public_path')) {
            $candidates[] = public_path('storage/'.$relative);
            $candidates[] = public_path($relative);
        }

        foreach ($candidates as $absolutePath) {
            $uri = self::fileToDataUri($absolutePath);
            if ($uri !== '') {
                return $uri;
            }
        }

        return '';
    }

    /**
     * @return array{width: int, height: int}|null
     */
    public static function displaySize(?string $dataUri, int $maxWidth = 128, int $maxHeight = 128): ?array
    {
        $value = trim((string) $dataUri);
        if ($value === '' || ! str_starts_with($value, 'data:image')) {
            return null;
        }

        $comma = strpos($value, ',');
        if ($comma === false) {
            return null;
        }

        $binary = base64_decode(substr($value, $comma + 1), true);
        if ($binary === false || $binary === '') {
            return null;
        }

        $info = @getimagesizefromstring($binary);
        if (! is_array($info) || (int) ($info[0] ?? 0) < 1 || (int) ($info[1] ?? 0) < 1) {
            return null;
        }

        $sourceWidth = (int) $info[0];
        $sourceHeight = (int) $info[1];
        $scale = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight, 1.0);

        return [
            'width' => max(1, (int) round($sourceWidth * $scale)),
            'height' => max(1, (int) round($sourceHeight * $scale)),
        ];
    }

    private static function fileToDataUri(string $absolutePath): string
    {
        if (! is_file($absolutePath)) {
            return '';
        }

        $contents = file_get_contents($absolutePath);
        if ($contents === false || $contents === '') {
            return '';
        }

        $mime = mime_content_type($absolutePath) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
