<?php

declare(strict_types=1);

namespace App\Support\Tdev;

use App\Support\WhatsAppBrandImage;

final class TdevWhatsAppBrandImage
{
    public const PUBLIC_RELATIVE_PATH = 'image/intcorp-tdev.png';

    public const STORAGE_RELATIVE_PATH = 'images-whatsapp/intcorp-tdev.png';

    /**
     * URL pública del banner INTEGRACORP + TDEV para UltraMsg.
     *
     * UltraMsg descarga la imagen desde internet: no puede usar hosts locales (.test).
     * Preferimos PUBLIC_URL (mismo patrón que el brand Integracorp).
     */
    public static function publicUrl(): string
    {
        $storageUrl = rtrim((string) config('parameters.PUBLIC_URL'), '/').'/'.self::STORAGE_RELATIVE_PATH;

        if (self::hostIsPubliclyReachable($storageUrl)) {
            return $storageUrl;
        }

        $appUrl = rtrim((string) config('app.url'), '/');

        if (self::hostIsPubliclyReachable($appUrl)) {
            return $appUrl.'/'.self::PUBLIC_RELATIVE_PATH;
        }

        return WhatsAppBrandImage::publicUrl();
    }

    public static function emailLogoPath(): string
    {
        $primaryLogo = public_path('image/intcorp-tdev.png');

        if (file_exists($primaryLogo)) {
            return $primaryLogo;
        }

        $fallbackTdev = public_path('image/logo-tdev.png');

        if (file_exists($fallbackTdev)) {
            return $fallbackTdev;
        }

        return public_path('image/logoNewPdf.png');
    }

    private static function hostIsPubliclyReachable(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);

        if ($host === 'localhost' || str_starts_with($host, '127.') || str_ends_with($host, '.test')) {
            return false;
        }

        return true;
    }
}
