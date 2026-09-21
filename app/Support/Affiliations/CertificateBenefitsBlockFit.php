<?php

declare(strict_types=1);

namespace App\Support\Affiliations;

/**
 * Decide si un bloque de beneficios del certificado puede mantenerse entero en una
 * página.
 *
 * DomPDF no parte una celda de tabla entre páginas, así que envolver el bloque en una
 * celda lo empuja completo a la hoja siguiente en vez de cortarlo. Esa técnica solo es
 * segura mientras el bloque quepa en una página: si no cabe, DomPDF dejaría una hoja en
 * blanco y recortaría lo que sobra. Por eso la plantilla pregunta aquí antes de agrupar.
 *
 * Las medidas son las del `@page` del certificado (A4 de 1123px con 132px de margen
 * superior y 104px inferior) y las del CSS de `.table-benefits`.
 */
final class CertificateBenefitsBlockFit
{
    /** Alto útil de una página, con holgura sobre los 887px reales del área de contenido. */
    public const CONTENT_HEIGHT = 870;

    /** Fila de una sola línea: 5px de padding arriba y abajo, 11px de línea y el borde. */
    private const ROW_HEIGHT = 22;

    private const ROW_EXTRA_LINE_HEIGHT = 11;

    /** Caracteres que entran en el ancho de la columna de texto con la fuente a 9px. */
    private const ROW_CHARS_PER_LINE = 58;

    /** Título de la sección más el margen superior del bloque. */
    private const TITLE_HEIGHT = 37;

    /** Nota al pie de la sección: tres líneas de 8px más su margen. */
    private const NOTE_HEIGHT = 38;

    /** Firma: la imagen de 70px más los 26px de margen superior. */
    private const SIGNATURE_HEIGHT = 96;

    /**
     * @param  array<int, array{text?: string}>  $rows
     */
    public static function fitsInOnePage(array $rows, bool $hasNote, bool $withSignature): bool
    {
        return self::estimatedHeight($rows, $hasNote, $withSignature) <= self::CONTENT_HEIGHT;
    }

    /**
     * @param  array<int, array{text?: string}>  $rows
     */
    public static function estimatedHeight(array $rows, bool $hasNote, bool $withSignature): int
    {
        $height = self::TITLE_HEIGHT;

        foreach ($rows as $row) {
            $height += self::rowHeight((string) ($row['text'] ?? ''));
        }

        if ($hasNote) {
            $height += self::NOTE_HEIGHT;
        }

        if ($withSignature) {
            $height += self::SIGNATURE_HEIGHT;
        }

        return $height;
    }

    private static function rowHeight(string $text): int
    {
        $lines = max(1, (int) ceil(mb_strlen(trim($text)) / self::ROW_CHARS_PER_LINE));

        return self::ROW_HEIGHT + (($lines - 1) * self::ROW_EXTRA_LINE_HEIGHT);
    }
}
