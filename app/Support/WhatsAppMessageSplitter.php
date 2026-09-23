<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Parte un mensaje de WhatsApp para respetar los límites de UltraMsg.
 *
 * Los mensajes del sistema se envían como imagen de marca con el texto como
 * pie, y UltraMsg rechaza pies de más de 1.024 caracteres («max length limit
 * exceeded of 1024»): 72 trabajos fallidos en producción por eso. Aquí el pie
 * lleva la primera parte y el resto viaja en mensajes de texto a continuación.
 * Siempre corta en un salto de párrafo, de línea o entre palabras.
 */
final class WhatsAppMessageSplitter
{
    /** Margen bajo el límite real de 1.024 de UltraMsg. */
    public const CAPTION_LIMIT = 1000;

    /** Margen bajo el límite de 4.096 de un mensaje de texto. */
    public const TEXT_LIMIT = 4000;

    private const CONTINUATION = '…';

    /**
     * @return array{caption: string, rest: list<string>}
     */
    public static function split(string $body, int $captionLimit = self::CAPTION_LIMIT, int $textLimit = self::TEXT_LIMIT): array
    {
        $body = rtrim(str_replace("\r\n", "\n", $body));

        if (mb_strlen($body) <= $captionLimit) {
            return ['caption' => $body, 'rest' => []];
        }

        [$caption, $remaining] = self::cut($body, $captionLimit - mb_strlen(self::CONTINUATION));
        $rest = [];

        while ($remaining !== '') {
            if (mb_strlen($remaining) <= $textLimit) {
                $rest[] = $remaining;

                break;
            }

            [$chunk, $remaining] = self::cut($remaining, $textLimit);
            $rest[] = $chunk;
        }

        return ['caption' => $caption.self::CONTINUATION, 'rest' => $rest];
    }

    /**
     * @return array{0: string, 1: string} parte que cabe y lo que sobra
     */
    private static function cut(string $text, int $limit): array
    {
        $window = mb_substr($text, 0, $limit);
        $position = null;

        foreach (["\n\n", "\n", ' '] as $separator) {
            $found = mb_strrpos($window, $separator);

            /** No se acepta un corte que deje una primera parte demasiado corta. */
            if ($found !== false && $found >= (int) ($limit * 0.5)) {
                $position = $found;

                break;
            }
        }

        $position ??= $limit;

        return [
            rtrim(mb_substr($text, 0, $position)),
            ltrim(mb_substr($text, $position)),
        ];
    }
}
