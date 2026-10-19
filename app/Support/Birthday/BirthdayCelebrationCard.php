<?php

declare(strict_types=1);

namespace App\Support\Birthday;

final class BirthdayCelebrationCard
{
    public const PUBLIC_RELATIVE_PATH = 'animacion-cumpleanos/assets/tarjeta-cumpleanos.jpg';

    public static function publicUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/'.self::PUBLIC_RELATIVE_PATH;
    }

    public static function isOfficialCard(string $relativePath): bool
    {
        return ltrim($relativePath, '/') === self::PUBLIC_RELATIVE_PATH;
    }
}
