<?php

declare(strict_types=1);

namespace App\Support;

final class PublicStorageAssetUrl
{
    public static function url(string $relativePath): string
    {
        $relativePath = ltrim($relativePath, '/');

        $base = config('parameters.PUBLIC_URL');
        if (! is_string($base) || trim($base) === '') {
            $base = rtrim((string) config('app.url'), '/').'/storage';
        }

        return rtrim($base, '/').'/'.$relativePath;
    }
}
