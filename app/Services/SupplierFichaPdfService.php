<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Supplier;
use App\Support\PdfCertifiedCheckBadge;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

class SupplierFichaPdfService
{
    /**
     * v1: base64 en caché (driver database / UTF-8).
     */
    private const PDF_CACHE_KEY_PREFIX = 'supplier_ficha_pdf:v2:';

    private const PDF_CACHE_TTL_FALLBACK_SECONDS = 900;

    /**
     * Relaciones alineadas con {@see ViewSupplier} (acción PDF histórica).
     *
     * @return list<string>
     */
    private const FICHA_RELATIONS = [
        'SupplierClasificacion',
        'state',
        'city',
        'supplierContactPrincipals',
        'supplierRedGlobals.state',
        'supplierRedGlobals.city',
        'SupplierZonaCoberturas.supplierClasificacion',
        'SupplierZonaCoberturas.state',
        'SupplierZonaCoberturas.city',
        'supplierObservacions',
    ];

    public static function downloadFilename(Supplier $supplier): string
    {
        return 'Ficha-Proveedor-'.$supplier->id.'.pdf';
    }

    /**
     * Invalida caché al cambiar el proveedor o tablas relacionadas que alimentan la ficha.
     */
    public static function pdfCacheVersion(Supplier $supplier): string
    {
        $id = $supplier->id;

        $parts = [
            (string) $id,
            (string) ($supplier->updated_at ?? ''),
            (string) DB::table('supplier_contact_principals')->where('supplier_id', $id)->max('updated_at'),
            (string) DB::table('supplier_red_globals')->where('supplier_id', $id)->max('updated_at'),
            (string) DB::table('supplier_zona_coberturas')->where('supplier_id', $id)->max('updated_at'),
            (string) DB::table('supplier_observacions')->where('supplier_id', $id)->max('updated_at'),
        ];

        return hash('sha256', implode('|', $parts));
    }

    public static function supplierWithFichaRelations(Supplier $supplier): Supplier
    {
        return $supplier->load(self::FICHA_RELATIONS);
    }

    public static function outputBinary(Supplier $supplier): string
    {
        $html = View::make('documents.supplier-ficha', [
            'supplier' => self::supplierWithFichaRelations($supplier),
            'infraCheckBadgeDataUri' => PdfCertifiedCheckBadge::dataUri(),
            'isPreview' => false,
        ])->render();

        return Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setWarnings(false)
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'sans-serif',
            ])
            ->output();
    }

    public static function outputBinaryCached(Supplier $supplier): string
    {
        $ttl = (int) config('supplier-report.pdf_cache_ttl_seconds', self::PDF_CACHE_TTL_FALLBACK_SECONDS);

        $encoded = Cache::remember(
            self::PDF_CACHE_KEY_PREFIX.self::pdfCacheVersion($supplier),
            max(60, $ttl),
            fn (): string => base64_encode(self::outputBinary($supplier)),
        );

        $binary = base64_decode($encoded, true);

        return is_string($binary) ? $binary : self::outputBinary($supplier);
    }
}
