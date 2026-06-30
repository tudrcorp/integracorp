<?php

declare(strict_types=1);

namespace App\Support\GuiaChat;

final class GuiaChatPublicUrl
{
    public static function url(): string
    {
        $baseUrl = rtrim((string) config('parameters.INTEGRACORP_URL', config('app.url')), '/');

        return $baseUrl.'/chat/publico';
    }
}
