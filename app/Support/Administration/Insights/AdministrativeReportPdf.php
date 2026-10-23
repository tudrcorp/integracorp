<?php

declare(strict_types=1);

namespace App\Support\Administration\Insights;

use App\Support\Administration\Reports\AdministrationReportGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * PDF del Informe administrativo. Se guarda en la misma carpeta privada y con el mismo
 * patrón de nombre que los reportes del Centro de reportes, así se descarga por la misma
 * ruta autenticada y aparece en «Mis reportes recientes».
 */
final class AdministrativeReportPdf
{
    public const FILE_SLUG = 'informe-administrativo';

    public const LABEL = 'Informe administrativo';

    public static function filename(?CarbonImmutable $now = null): string
    {
        $now ??= CarbonImmutable::now();

        return 'reporte-administracion-'.self::FILE_SLUG.'-'.$now->format('Ymd-His').'-'.Str::lower(Str::random(6)).'.pdf';
    }

    /** Ancho de un gráfico de barras a tamaño completo (el ancho útil de la tarjeta). */
    public const CHART_WIDTH_MM = 180.0;

    /** Ancho de una dona a tamaño completo. */
    public const DONUT_WIDTH_MM = 46.0;

    /** Con hasta estas filas, la tabla va junto a su gráfico (el bloque no se parte). */
    public const WHOLE_BLOCK_MAX_ROWS = 4;

    /** Un gráfico nunca se achica por debajo de esto: debajo deja de leerse. */
    public const MIN_SCALE = 0.65;

    /** Un gráfico de pocas barras (rótulos grandes y separados) se lee aún más chico. */
    public const MIN_SCALE_FEW_BARS = 0.55;

    private const FEW_BARS = 6;

    /** Rondas de acomodo como máximo; casi siempre basta una. */
    private const MAX_PASSES = 3;

    private const POINTS_PER_MM = 72 / 25.4;

    /** Huecos menores que esto no se tocan. */
    private const MIN_GAP_MM = 5.0;

    /** Margen que se deja al pie al repartir, para no empujar nada por redondeo. */
    private const SAFETY_MM = 3.0;

    /** Separación extra máxima por bloque: más que esto se ve forzado. */
    private const MAX_SPACING_MM = 18.0;

    /** Márgenes de `@page` en documents.administrative-report, en mm. */
    private const PAGE_TOP_MM = 10.0;

    private const PAGE_BOTTOM_MM = 14.0;

    /**
     * Genera el PDF acomodado en varias pasadas para que no queden huecos en páginas intermedias.
     *
     * Un gráfico es una imagen y DomPDF no lo parte: si no cabe en lo que queda de la página,
     * salta entero a la siguiente y deja el hueco. Por eso se mide dónde quedó cada bloque y,
     * si un bloque saltó dejando espacio libre, se achica su gráfico lo justo para que entre
     * (nunca por debajo de {@see MIN_SCALE}). Se vuelve a medir hasta que no quede nada que
     * ajustar. El orden del contenido no cambia.
     *
     * @param  array<string, mixed>  $report  Salida de {@see AdministrativeReportBuilder::build()}.
     */
    public static function render(array $report): string
    {
        $scales = [];
        $fixed = self::fixedUnits($report);
        $minimums = self::minimumScales($report);
        [$dompdf, $layout] = self::renderMeasured($report);

        for ($pass = 1; $pass <= self::MAX_PASSES; $pass++) {
            $adjustments = array_diff_key(self::adjustments($layout, $scales, $minimums), array_flip($fixed));

            if ($adjustments === []) {
                break;
            }

            $scales = array_replace($scales, $adjustments);
            [$dompdf, $layout] = self::renderMeasured($report, $scales);
        }

        $spacing = self::spacing($layout);

        if ($spacing === []) {
            return (string) $dompdf->output();
        }

        [$spaced, $spacedLayout] = self::renderMeasured($report, $scales, $spacing);

        // El espacio repartido nunca puede mover un bloque de página; si lo hiciera, se descarta.
        return (string) (self::keepPages($spacedLayout) === self::keepPages($layout) ? $spaced : $dompdf)->output();
    }

    /**
     * Dibuja el informe y anota, de cada bloque, la página y la altura donde quedó, y de cada
     * página hasta dónde llega el contenido.
     *
     * @param  array<string, mixed>  $report
     * @param  array<int, float>  $scales
     * @param  array<string, float>  $spacing
     * @return array{0: \Dompdf\Dompdf, 1: array{blocks: array<int, array{page: int, top: float, height: float, chart_height: float|null}>, keeps: array<string, array{page: int, top: float, height: float}>, page_bottoms: array<int, float>}}
     */
    public static function renderMeasured(array $report, array $scales = [], array $spacing = []): array
    {
        $dompdf = Pdf::loadView('documents.administrative-report', ['report' => $report, 'chartScales' => $scales, 'blockSpacing' => $spacing])
            ->setPaper('a4', 'portrait')
            ->setOption('isFontSubsettingEnabled', true)
            ->getDomPDF();

        $layout = ['blocks' => [], 'keeps' => [], 'page_bottoms' => []];

        $dompdf->setCallbacks([[
            'event' => 'end_frame',
            'f' => function ($frame, $canvas) use (&$layout): void {
                $page = (int) $canvas->get_page_number();
                [, $top] = $frame->get_position();
                $height = (float) $frame->get_margin_height();
                $node = $frame->get_node();

                // Solo texto e imágenes marcan dónde termina el contenido: un contenedor partido
                // entre páginas parece llenar la página y ocultaría el hueco.
                if ($node->nodeName === '#text' || $node->nodeName === 'img') {
                    $layout['page_bottoms'][$page] = max($layout['page_bottoms'][$page] ?? 0.0, (float) $top + $height);
                }

                $id = $node instanceof \DOMElement ? $node->getAttribute('id') : '';

                if (str_starts_with($id, 'adm-keep-') || str_starts_with($id, 'adm-head-')) {
                    $layout['keeps'][$id] = ['page' => $page, 'top' => (float) $top, 'height' => $height];
                }

                if (str_starts_with($id, 'adm-keep-')) {
                    $unit = (int) substr($id, 9);
                    $layout['blocks'][$unit] = ['page' => $page, 'top' => (float) $top, 'height' => $height, 'chart_height' => $layout['blocks'][$unit]['chart_height'] ?? null];
                } elseif (str_starts_with($id, 'adm-chart-')) {
                    $unit = (int) substr($id, 10);
                    $layout['blocks'][$unit]['chart_height'] = $height;
                }
            },
        ]]);

        $dompdf->render();

        return [$dompdf, $layout];
    }

    /**
     * Bloques que saltaron de página dejando un hueco en la anterior, con la escala que hace
     * falta para que entren. Solo los que con esa escala siguen legibles.
     *
     * @param  array{blocks: array<int, array{page: int, top: float, height: float, chart_height: float|null}>, page_bottoms: array<int, float>}  $layout
     * @param  array<int, float>  $current
     * @param  array<int, float>  $minimums  Escala mínima por bloque; sin dato, {@see MIN_SCALE}.
     * @return array<int, float>
     */
    public static function adjustments(array $layout, array $current = [], array $minimums = []): array
    {
        $pageTop = self::PAGE_TOP_MM * self::POINTS_PER_MM;
        $contentBottom = 297 * self::POINTS_PER_MM - self::PAGE_BOTTOM_MM * self::POINTS_PER_MM;
        $adjustments = [];

        foreach ($layout['blocks'] as $unit => $block) {
            $chartHeight = (float) ($block['chart_height'] ?? 0);

            // Solo interesa el bloque que abre una página (saltó desde la anterior).
            if ($block['page'] <= 1 || $chartHeight <= 0 || $block['top'] > $pageTop + 20) {
                continue;
            }

            $gap = $contentBottom - ($layout['page_bottoms'][$block['page'] - 1] ?? $contentBottom);

            // Lo que no cabe; un pequeño margen evita que vuelva a saltar por redondeo.
            $overflow = $block['height'] - $gap + 6;

            if ($gap <= 0 || $overflow <= 0) {
                continue;
            }

            $scaleNow = $current[$unit] ?? 1.0;
            $fullChart = $chartHeight / $scaleNow;
            $scale = round(($chartHeight - $overflow) / $fullChart, 3);

            if ($scale >= ($minimums[$unit] ?? self::MIN_SCALE) && $scale < $scaleNow) {
                $adjustments[$unit] = $scale;
            }
        }

        return $adjustments;
    }

    /**
     * Cuando la página siguiente abre con un bloque que no se parte, el sobrante al pie de esta
     * página se reparte como separación entre sus bloques: el contenido llega al pie y nada
     * cambia de página. Si la página siguiente abre con una tabla, no se toca (sus filas
     * podrían subir o bajar).
     *
     * @param  array{keeps: array<string, array{page: int, top: float, height: float}>, page_bottoms: array<int, float>}  $layout
     * @return array<string, float> id del bloque => mm extra encima
     */
    public static function spacing(array $layout): array
    {
        $pageTop = self::PAGE_TOP_MM * self::POINTS_PER_MM;
        $contentBottom = 297 * self::POINTS_PER_MM - self::PAGE_BOTTOM_MM * self::POINTS_PER_MM;
        $lastPage = max(array_keys($layout['page_bottoms']) ?: [1]);
        $spacing = [];

        for ($page = 1; $page < $lastPage; $page++) {
            $gapMm = ($contentBottom - ($layout['page_bottoms'][$page] ?? $contentBottom)) / self::POINTS_PER_MM - self::SAFETY_MM;
            $nextOpensWithBlock = collect($layout['keeps'])->contains(static fn (array $keep): bool => $keep['page'] === $page + 1 && $keep['top'] <= $pageTop + 20);

            if ($gapMm < self::MIN_GAP_MM || ! $nextOpensWithBlock) {
                continue;
            }

            // Los bloques de la página, salvo el que ya está arriba de todo.
            $onPage = array_keys(array_filter($layout['keeps'], static fn (array $keep): bool => $keep['page'] === $page && $keep['top'] > $pageTop + 20));

            if ($onPage === []) {
                continue;
            }

            $each = min($gapMm / count($onPage), self::MAX_SPACING_MM);

            foreach ($onPage as $id) {
                $spacing[$id] = round($each, 2);
            }
        }

        return $spacing;
    }

    /**
     * Escala mínima de cada bloque: los gráficos de barras con pocas barras admiten achicarse
     * más que los de 12 meses o las donas. Se numeran igual que en la plantilla.
     *
     * @param  array<string, mixed>  $report
     * @return array<int, float>
     */
    public static function minimumScales(array $report): array
    {
        $minimums = [];
        $unit = 0;

        foreach ($report['sections'] as $section) {
            foreach ($section['blocks'] as $block) {
                $unit++;
                $svg = (string) ($block['chart'] ?? '');
                $isBars = $svg !== '' && ! str_contains($svg, '<path') && ! str_contains($svg, '<circle');
                $minimums[$unit] = $isBars && substr_count($svg, '<rect') <= self::FEW_BARS ? self::MIN_SCALE_FEW_BARS : self::MIN_SCALE;
            }
        }

        return $minimums;
    }

    /**
     * Bloques cuyo gráfico no se achica (rankings con mucho texto). Se numeran igual que en
     * la plantilla: un bloque tras otro, en el orden del informe.
     *
     * @param  array<string, mixed>  $report
     * @return list<int>
     */
    public static function fixedUnits(array $report): array
    {
        $fixed = [];
        $unit = 0;

        foreach ($report['sections'] as $section) {
            foreach ($section['blocks'] as $block) {
                $unit++;

                if (($block['shrinkable'] ?? true) === false) {
                    $fixed[] = $unit;
                }
            }
        }

        return $fixed;
    }

    /**
     * @param  array{keeps: array<string, array{page: int}>}  $layout
     * @return array<string, int>
     */
    private static function keepPages(array $layout): array
    {
        $pages = array_map(static fn (array $keep): int => $keep['page'], $layout['keeps']);
        ksort($pages);

        return $pages;
    }

    /**
     * Genera y guarda el PDF. Idempotente por nombre: un reintento reescribe el mismo
     * archivo. Se escribe a un `.part` y se renombra al final para no servirlo a medias.
     */
    public static function store(AdministrativeReportFilters $filters, int $userId, string $filename): string
    {
        $disk = Storage::disk(AdministrationReportGenerator::DISK);
        $directory = AdministrationReportGenerator::userDirectory($userId);
        $disk->makeDirectory($directory);

        $absolute = $disk->path($directory.'/'.$filename);
        $partial = $absolute.'.part';

        try {
            if (file_put_contents($partial, self::render((new AdministrativeReportBuilder($filters))->build())) === false) {
                throw new RuntimeException('No se pudo guardar el PDF del informe administrativo.');
            }

            rename($partial, $absolute);
        } catch (Throwable $exception) {
            @unlink($partial);

            throw $exception;
        }

        AdministrationReportGenerator::purgeExpired($userId);

        return $directory.'/'.$filename;
    }
}
