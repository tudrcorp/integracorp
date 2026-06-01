<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\HtmlString;

final class HelpdeskDescriptionInfolistRenderer
{
    public static function format(?string $html): HtmlString
    {
        $trimmed = trim((string) $html);

        if ($trimmed === '') {
            return self::emptyState();
        }

        $sanitized = HelpdeskNoteHtmlSanitizer::sanitize($trimmed);

        return new HtmlString(
            '<div class="fi-helpdesk-description-card__body">'
            .'<div class="fi-helpdesk-description-card__prose">'.$sanitized.'</div>'
            .'</div>'
        );
    }

    public static function characterSummary(?string $html): ?string
    {
        $plain = HelpdeskPlainText::fromHtml($html);

        if ($plain === '') {
            return null;
        }

        $length = mb_strlen($plain);

        return $length === 1
            ? '1 carácter'
            : number_format($length, 0, ',', '.').' caracteres';
    }

    public static function emptyState(): HtmlString
    {
        return new HtmlString(
            '<div class="fi-helpdesk-description-card__empty">'
            .'<p class="fi-helpdesk-description-card__empty-title">Sin descripción</p>'
            .'<p class="fi-helpdesk-description-card__empty-text">Este ticket no tiene detalle registrado en el campo de descripción.</p>'
            .'</div>'
        );
    }
}
