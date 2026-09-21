<?php

declare(strict_types=1);

namespace App\Support\TuDrQuote;

use setasign\Fpdi\Fpdi;
use Throwable;

/**
 * Aplica al PDF de la propuesta el formato configurado en CONFIGURACIÓN.
 *
 * El microservicio entrega el documento oficial completo —portada, «acerca»,
 * una página de cálculos por plan, patologías del Especial y contraportada—,
 * así que aquí no se añade nada: se reordena y se recorta lo que ya viene.
 * Con los valores por defecto (7 hojas, cálculos en la 3) el documento sale
 * exactamente como lo dibuja el servicio.
 */
final class QuoteDocumentAssembler
{
    /**
     * Páginas fijas que el servicio dibuja antes de los cálculos: portada y
     * «acerca de nosotros».
     */
    public const PAGES_BEFORE_CALCULATIONS = 2;

    /**
     * @param  string  $document  PDF devuelto por el microservicio
     * @param  int  $calculationPages  una página por plan cotizado
     */
    public static function apply(string $document, int $calculationPages, string $scope): string
    {
        $layout = QuoteDocumentLayout::for($scope);
        $file = self::temporaryFile($document);

        try {
            $pdf = new Fpdi;
            $total = $pdf->setSourceFile($file);

            $templates = [];
            for ($page = 1; $page <= $total; $page++) {
                $templates[] = $pdf->importPage($page);
            }

            $order = self::order($templates, $calculationPages, $layout);

            /** Si el formato configurado no cambia nada, no se reescribe el PDF. */
            if ($order === $templates) {
                return $document;
            }

            foreach ($order as $templateId) {
                $size = $pdf->getTemplateSize($templateId);

                if (! is_array($size)) {
                    continue;
                }

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
            }

            return (string) $pdf->Output('S');
        } catch (Throwable) {
            /** Reordenar no puede costar el documento: se entrega tal cual. */
            return $document;
        } finally {
            self::forget($file);
        }
    }

    /**
     * Orden final de las páginas del documento.
     *
     * Los cálculos viajan juntos a la posición configurada y las páginas
     * institucionales conservan su orden alrededor. Al recortar se descartan
     * institucionales del final, nunca las tarifas.
     *
     * @param  list<int>  $pages  páginas del documento, en el orden del servicio
     * @param  array{total_pages: int, calculations_page: int}  $layout
     * @return list<int>
     */
    public static function order(array $pages, int $calculationPages, array $layout): array
    {
        $calculationPages = max(0, $calculationPages);
        $offset = self::PAGES_BEFORE_CALCULATIONS;

        if ($calculationPages === 0 || count($pages) <= $offset) {
            return $pages;
        }

        $calculations = array_slice($pages, $offset, $calculationPages);
        $institutional = [...array_slice($pages, 0, $offset), ...array_slice($pages, $offset + $calculationPages)];

        $position = max(1, min($layout['calculations_page'], $layout['total_pages']));
        $before = array_slice($institutional, 0, $position - 1);
        $after = array_slice($institutional, $position - 1);

        $ordered = [...$before, ...$calculations, ...$after];

        $limit = max($layout['total_pages'], count($before) + count($calculations));

        return array_slice($ordered, 0, $limit);
    }

    private static function temporaryFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tudr-quote-');

        if ($path === false) {
            $path = sys_get_temp_dir().'/tudr-quote-'.uniqid().'.pdf';
        }

        file_put_contents($path, $contents);

        return $path;
    }

    private static function forget(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
