<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Affiliation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

class AffiliationFichaPdfService
{
    private const PDF_CACHE_KEY_PREFIX = 'affiliation_individual_ficha_pdf:v1:';

    private const PDF_CACHE_TTL_FALLBACK_SECONDS = 900;

    public static function downloadFilename(Affiliation $affiliation): string
    {
        return 'Ficha-Afiliacion-Individual-'.$affiliation->id.'.pdf';
    }

    public static function pdfCacheVersion(Affiliation $affiliation): string
    {
        $id = $affiliation->id;

        $affiliateStats = DB::table('affiliates')
            ->where('affiliation_id', $id)
            ->selectRaw('max(updated_at) as max_updated, count(*) as c')
            ->first();

        $code = trim((string) ($affiliation->code ?? ''));
        $collectionStats = $code !== ''
            ? DB::table('collections')
                ->where('affiliation_code', $code)
                ->selectRaw('max(updated_at) as max_updated, count(*) as c')
                ->first()
            : null;

        $parts = [
            (string) $id,
            (string) ($affiliation->updated_at ?? ''),
            (string) ($affiliateStats->max_updated ?? ''),
            (string) ($affiliateStats->c ?? '0'),
            (string) ($collectionStats?->max_updated ?? ''),
            (string) ($collectionStats?->c ?? '0'),
        ];

        return hash('sha256', implode('|', $parts));
    }

    public static function affiliationWithFichaRelations(Affiliation $affiliation): Affiliation
    {
        return $affiliation->load([
            'plan',
            'coverage',
            'state',
            'city',
            'country',
            'agent',
            'agency',
            'billingCollections' => function ($query): void {
                $query->orderByRaw('COALESCE(next_payment_date, expiration_date) ASC')
                    ->orderBy('id');
            },
            'affiliates' => function ($query): void {
                $query
                    ->orderBy('full_name')
                    ->orderBy('id')
                    ->with(['plan', 'coverage', 'ageRange']);
            },
        ]);
    }

    public static function outputBinary(Affiliation $affiliation): string
    {
        $html = View::make('documents.affiliation-individual-ficha', [
            'affiliation' => self::affiliationWithFichaRelations($affiliation),
        ])->render();

        return Pdf::loadHTML($html)
            ->setPaper('a4', 'landscape')
            ->setWarnings(false)
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'sans-serif',
            ])
            ->output();
    }

    public static function outputBinaryCached(Affiliation $affiliation): string
    {
        $ttl = (int) config('supplier-report.pdf_cache_ttl_seconds', self::PDF_CACHE_TTL_FALLBACK_SECONDS);

        $encoded = Cache::remember(
            self::PDF_CACHE_KEY_PREFIX.self::pdfCacheVersion($affiliation),
            max(60, $ttl),
            fn (): string => base64_encode(self::outputBinary($affiliation)),
        );

        $binary = base64_decode($encoded, true);

        return is_string($binary) ? $binary : self::outputBinary($affiliation);
    }
}
