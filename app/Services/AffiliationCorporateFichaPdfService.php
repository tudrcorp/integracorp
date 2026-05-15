<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AffiliationCorporate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

class AffiliationCorporateFichaPdfService
{
    private const PDF_CACHE_KEY_PREFIX = 'affiliation_corporate_ficha_pdf:v1:';

    private const PDF_CACHE_TTL_FALLBACK_SECONDS = 900;

    public static function downloadFilename(AffiliationCorporate $affiliationCorporate): string
    {
        return 'Ficha-Afiliacion-Corporativa-'.$affiliationCorporate->id.'.pdf';
    }

    public static function pdfCacheVersion(AffiliationCorporate $affiliationCorporate): string
    {
        $id = $affiliationCorporate->id;

        $affiliateStats = DB::table('affiliate_corporates')
            ->where('affiliation_corporate_id', $id)
            ->selectRaw('max(updated_at) as max_updated, count(*) as c')
            ->first();

        $code = trim((string) ($affiliationCorporate->code ?? ''));
        $collectionStats = $code !== ''
            ? DB::table('collections')
                ->where('affiliation_code', $code)
                ->selectRaw('max(updated_at) as max_updated, count(*) as c')
                ->first()
            : null;

        $parts = [
            (string) $id,
            (string) ($affiliationCorporate->updated_at ?? ''),
            (string) ($affiliateStats->max_updated ?? ''),
            (string) ($affiliateStats->c ?? '0'),
            (string) ($collectionStats?->max_updated ?? ''),
            (string) ($collectionStats?->c ?? '0'),
        ];

        return hash('sha256', implode('|', $parts));
    }

    public static function affiliationWithFichaRelations(AffiliationCorporate $affiliationCorporate): AffiliationCorporate
    {
        return $affiliationCorporate->load([
            'state',
            'city',
            'country',
            'region',
            'agent',
            'agency',
            'billingCollections' => function ($query): void {
                $query->orderByRaw('COALESCE(next_payment_date, expiration_date) ASC')
                    ->orderBy('id');
            },
            'corporateAffiliates' => function ($query): void {
                $query
                    ->orderBy('last_name')
                    ->orderBy('first_name')
                    ->orderBy('id')
                    ->with(['plan', 'coverage']);
            },
        ]);
    }

    public static function outputBinary(AffiliationCorporate $affiliationCorporate): string
    {
        $html = View::make('documents.affiliation-corporate-ficha', [
            'affiliationCorporate' => self::affiliationWithFichaRelations($affiliationCorporate),
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

    public static function outputBinaryCached(AffiliationCorporate $affiliationCorporate): string
    {
        $ttl = (int) config('supplier-report.pdf_cache_ttl_seconds', self::PDF_CACHE_TTL_FALLBACK_SECONDS);

        $encoded = Cache::remember(
            self::PDF_CACHE_KEY_PREFIX.self::pdfCacheVersion($affiliationCorporate),
            max(60, $ttl),
            fn (): string => base64_encode(self::outputBinary($affiliationCorporate)),
        );

        $binary = base64_decode($encoded, true);

        return is_string($binary) ? $binary : self::outputBinary($affiliationCorporate);
    }
}
