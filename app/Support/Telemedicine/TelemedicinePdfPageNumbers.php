<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use Dompdf\Dompdf;

/**
 * Folio «Página X de Y» de los documentos de telemedicina.
 *
 * DomPDF no soporta `counter(pages)` en CSS, así que el total solo se conoce
 * después del layout: el folio se dibuja sobre el canvas, debajo del pie fijo,
 * fuera del flujo. Como no ocupa espacio en el documento, no puede cambiar el
 * número de páginas que está contando.
 *
 * Debe llamarse tras `render()` y antes de `output()`.
 */
final class TelemedicinePdfPageNumbers
{
    public const FORMAT = 'Página %d de %d';

    /**
     * Borde superior del folio en A4 vertical (informes, órdenes y bitácora).
     * El pie fijo de esas plantillas termina en 279 mm (`bottom: 18mm`).
     */
    public const PORTRAIT_TOP_MM = 281.5;

    /**
     * Borde superior del folio en el récipe (A4 horizontal, 210 mm de alto).
     * Sus pies fijos terminan en 198 mm (`bottom: 12mm`).
     */
    public const RECIPE_TOP_MM = 200.5;

    public const A4_PORTRAIT_WIDTH_MM = 210.0;

    /**
     * Centro de cada mitad del récipe: los pies miden 128 mm y se apoyan a 14 mm
     * de cada borde de la hoja de 297 mm. Un folio por mitad, para que original y
     * copia queden foliadas al cortar la hoja.
     *
     * @var list<float>
     */
    public const RECIPE_CENTERS_MM = [78.0, 219.0];

    private const FONT_SIZE = 6.5;

    /** Gris del pie (#9ca3af). */
    private const COLOR = [0.612, 0.639, 0.686];

    public static function label(int $pageNumber, int $pageCount): string
    {
        return sprintf(self::FORMAT, $pageNumber, $pageCount);
    }

    public static function applyToPortrait(Dompdf $dompdf): void
    {
        self::applyTo($dompdf, [self::A4_PORTRAIT_WIDTH_MM / 2], self::PORTRAIT_TOP_MM);
    }

    public static function applyToRecipe(Dompdf $dompdf): void
    {
        self::applyTo($dompdf, self::RECIPE_CENTERS_MM, self::RECIPE_TOP_MM);
    }

    /**
     * @param  list<float>  $centersMm  Centro horizontal de cada folio en la hoja.
     */
    public static function applyTo(Dompdf $dompdf, array $centersMm, float $topMm): void
    {
        $dompdf->getCanvas()->page_script(function (
            int $pageNumber,
            int $pageCount,
            $canvas,
            $fontMetrics
        ) use ($centersMm, $topMm): void {
            $text = self::label($pageNumber, $pageCount);
            $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
            $width = $fontMetrics->getTextWidth($text, $font, self::FONT_SIZE);
            $top = TelemedicineInformeSignatureStamp::mmToPt($topMm);

            foreach ($centersMm as $centerMm) {
                $center = TelemedicineInformeSignatureStamp::mmToPt($centerMm);
                $canvas->text($center - $width / 2, $top, $text, $font, self::FONT_SIZE, self::COLOR);
            }
        });
    }
}
