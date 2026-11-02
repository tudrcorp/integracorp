<?php

declare(strict_types=1);

namespace App\Support\Birthday;

final class BirthdayCelebrationCard
{
    /** Imagen que se envía por WhatsApp (y se usa en la animación). */
    public const PUBLIC_RELATIVE_PATH = 'animacion-cumpleanos/assets/tarjeta-cumpleanos.jpg';

    /** Imagen del correo de celebración (enlace a la animación / cupones). */
    public const EMAIL_PUBLIC_RELATIVE_PATH = 'animacion-cumpleanos/assets/tarjeta-cumpleanos-email.png';

    public static function publicUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/'.self::PUBLIC_RELATIVE_PATH;
    }

    public static function emailPublicUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/'.self::EMAIL_PUBLIC_RELATIVE_PATH;
    }

    public static function isOfficialCard(string $relativePath): bool
    {
        $normalized = ltrim($relativePath, '/');

        return $normalized === self::PUBLIC_RELATIVE_PATH
            || $normalized === self::EMAIL_PUBLIC_RELATIVE_PATH;
    }
}
