<?php

declare(strict_types=1);

namespace App\Support;

final class WhatsAppBrandImage
{
    public const RELATIVE_PATH = 'images-whatsapp/integracorp.png';

    public static function publicUrl(): string
    {
        return rtrim((string) config('parameters.PUBLIC_URL'), '/').'/'.self::RELATIVE_PATH;
    }
}
