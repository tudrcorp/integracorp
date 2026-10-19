<?php

declare(strict_types=1);

namespace App\Support;

final class HelpdeskPlainText
{
    public static function fromHtml(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * La mayoría de las descripciones son texto plano; las de la página de error
     * (y algunas viejas) son HTML. Solo cuenta como HTML si trae etiquetas de bloque
     * o formato reales: un «a < b» escrito a mano sigue siendo texto.
     */
    public static function looksLikeHtml(?string $text): bool
    {
        return $text !== null && preg_match('/<\s*\/?\s*(p|ul|ol|li|br|h[1-6]|strong|em|pre|code|div)\b[^>]*>/i', $text) === 1;
    }

    /**
     * Texto legible para correo (con saltos de línea): cada párrafo, título o
     * lista separado por una línea en blanco, viñetas «• ». El texto plano se
     * devuelve tal cual.
     */
    public static function readable(?string $text): string
    {
        $text = trim((string) $text);

        if (! self::looksLikeHtml($text)) {
            return $text;
        }

        $text = (string) preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $text);
        $text = (string) preg_replace('/<\s*h[1-6]\b[^>]*>/i', "\n\n", $text);
        $text = (string) preg_replace('/<\s*li\b[^>]*>/i', "\n• ", $text);
        $text = (string) preg_replace('/<\s*\/\s*(p|div|h[1-6]|ul|ol|pre)\s*>/i', "\n\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace("/[ \t]+\n/", "\n", $text);
        $text = (string) preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    /**
     * Una sola línea para WhatsApp: el HTML se separa con « · » para que las
     * frases no queden pegadas. El texto plano conserva el comportamiento de siempre.
     */
    public static function inline(?string $text): string
    {
        if (! self::looksLikeHtml($text)) {
            return trim(html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $line = (string) preg_replace('/\s*\n+\s*/u', ' · ', self::readable($text));

        return trim((string) preg_replace('/ · • /u', ' · ', $line));
    }
}
