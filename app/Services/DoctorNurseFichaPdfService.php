<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DoctorNurse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DoctorNurseFichaPdfService
{
    /**
     * v1: base64 en caché (driver database / UTF-8), igual que {@see SupplierFichaPdfService}.
     */
    private const PDF_CACHE_KEY_PREFIX = 'doctor_nurse_ficha_pdf:v1:';

    private const PDF_CACHE_TTL_FALLBACK_SECONDS = 900;

    public static function downloadFilename(DoctorNurse $doctorNurse): string
    {
        $name = trim((string) ($doctorNurse->name ?? ''));
        $safe = $name !== '' ? preg_replace('/[^A-Za-z0-9\-_ ]+/', '', $name) : null;
        $safe = $safe ? trim(str_replace('  ', ' ', $safe)) : null;
        $safe = $safe !== '' ? str_replace(' ', '_', $safe) : null;

        return 'ficha_tecnica_proveedor_natural_'.($safe ?: $doctorNurse->id).'.pdf';
    }

    public static function pdfCacheVersion(DoctorNurse $doctorNurse): string
    {
        $id = $doctorNurse->id;

        $parts = [
            (string) $id,
            (string) ($doctorNurse->updated_at ?? ''),
            (string) DB::table('doctor_nurse_observacions')->where('doctor_nurse_id', $id)->max('updated_at'),
        ];

        return hash('sha256', implode('|', $parts));
    }

    public static function doctorNurseWithFichaRelations(DoctorNurse $doctorNurse): DoctorNurse
    {
        return DoctorNurse::query()
            ->with(['supplierClasificacion', 'doctorNurseObservacions'])
            ->findOrFail($doctorNurse->id);
    }

    public static function outputBinary(DoctorNurse $doctorNurse): string
    {
        $doctorNurse = self::doctorNurseWithFichaRelations($doctorNurse);

        return Pdf::loadView('documents.doctor-nurse-ficha', [
            'doctorNurse' => $doctorNurse,
        ])
            ->setPaper('a4', 'portrait')
            ->setWarnings(false)
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'sans-serif',
            ])
            ->output();
    }

    public static function outputBinaryCached(DoctorNurse $doctorNurse): string
    {
        $ttl = (int) config('supplier-report.pdf_cache_ttl_seconds', self::PDF_CACHE_TTL_FALLBACK_SECONDS);

        $encoded = Cache::remember(
            self::PDF_CACHE_KEY_PREFIX.self::pdfCacheVersion($doctorNurse),
            max(60, $ttl),
            fn (): string => base64_encode(self::outputBinary($doctorNurse)),
        );

        $binary = base64_decode((string) $encoded, true);

        return is_string($binary) ? $binary : self::outputBinary($doctorNurse);
    }
}
