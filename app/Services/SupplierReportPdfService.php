<?php

declare(strict_types=1);

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SupplierReportPdfService
{
    public const FILENAME = 'reporte-proveedores.pdf';

    private const LOGO_CACHE_PREFIX = 'supplier_report_logo_uri:';

    /**
     * Filas listas para el PDF: una sola consulta, sin hidratar modelos Eloquent ni relaciones.
     *
     * @return list<array{state: string, city: string, name: string, clasificacion: string}>
     */
    public static function reportRows(): array
    {
        $rows = DB::table('suppliers')
            ->leftJoin('states', 'suppliers.state_id', '=', 'states.id')
            ->leftJoin('cities', 'suppliers.city_id', '=', 'cities.id')
            ->leftJoin('supplier_clasificacions', 'suppliers.supplier_clasificacion_id', '=', 'supplier_clasificacions.id')
            ->orderByRaw('CASE WHEN states.definition IS NULL OR states.definition = \'\' THEN 1 ELSE 0 END')
            ->orderBy('states.definition')
            ->orderByRaw('CASE WHEN cities.definition IS NULL OR cities.definition = \'\' THEN 1 ELSE 0 END')
            ->orderBy('cities.definition')
            ->orderBy('suppliers.name')
            ->select([
                'states.definition as state_definition',
                'cities.definition as city_definition',
                'suppliers.name as supplier_name',
                'supplier_clasificacions.description as clasificacion_description',
            ])
            ->get();

        $out = [];

        foreach ($rows as $r) {
            $out[] = [
                'state' => self::normalizeCell($r->state_definition ?? null),
                'city' => self::normalizeCell($r->city_definition ?? null),
                'name' => self::normalizeCell($r->supplier_name ?? null),
                'clasificacion' => self::normalizeCell($r->clasificacion_description ?? null),
            ];
        }

        return $out;
    }

    /**
     * Data URI del logo en caché (clave incluye mtime del archivo; sin releer disco en cada petición).
     */
    public static function cachedLogoDataUri(): string
    {
        $path = public_path('image/logoNewPdf.png');

        if (! is_file($path)) {
            return '';
        }

        $mtime = @filemtime($path) ?: 0;

        return Cache::remember(
            self::LOGO_CACHE_PREFIX.$mtime,
            60 * 60 * 24 * 30,
            static function () use ($path): string {
                $raw = @file_get_contents($path);

                if ($raw === false || $raw === '') {
                    return '';
                }

                return 'data:image/png;base64,'.base64_encode($raw);
            }
        );
    }

    public static function make(): PdfDocument
    {
        $reportRows = self::reportRows();

        $pdf = Pdf::loadView('documents.suppliers-report', [
            'reportRows' => $reportRows,
            'generatedAt' => now(),
            'logoDataUri' => self::cachedLogoDataUri(),
        ])
            ->setPaper('a4', 'portrait');

        $pdf->setOptions([
            'isRemoteEnabled' => false,
            'isJavascriptEnabled' => false,
            'isPhpEnabled' => false,
            'dpi' => 72,
            'isFontSubsettingEnabled' => false,
            'defaultFont' => 'DejaVu Sans',
        ], mergeWithDefaults: true);

        return $pdf;
    }

    private static function normalizeCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $s = trim((string) $value);

        return $s;
    }
}
