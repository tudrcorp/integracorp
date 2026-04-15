<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

final class HelpdeskNoteHtmlSanitizer
{
    private static ?HtmlSanitizer $sanitizer = null;

    public static function sanitize(string $html): string
    {
        return self::sanitizer()->sanitize($html);
    }

    private static function sanitizer(): HtmlSanitizer
    {
        return self::$sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->withMaxInputLength(-1)
        );
    }
}
