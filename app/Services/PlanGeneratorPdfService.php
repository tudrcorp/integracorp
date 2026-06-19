<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PlanGenerator;
use App\Support\PlanGenerators\PlanGeneratorBrandColor;
use App\Support\PlanGenerators\PlanGeneratorPdfImageUri;
use App\Support\PlanGenerators\PlanGeneratorPreviewBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PlanGeneratorPdfService
{
    private const LOGO_CACHE_PREFIX = 'plan_generator_pdf_logo_uri:v1:';

    private const PDF_CACHE_KEY_PREFIX = 'plan_generator_pdf:v1:';

    private const PDF_CACHE_TTL_FALLBACK_SECONDS = 900;

    /**
     * @return list<string>
     */
    private static function logoCandidatePaths(): array
    {
        return [
            public_path('storage/administracion/logoNewPdfTDEC.png'),
            public_path('storage/logo1-pdf.png'),
            public_path('image/logoNewPdf.png'),
        ];
    }

    public static function cachedLogoDataUri(): string
    {
        foreach (self::logoCandidatePaths() as $path) {
            if (! is_file($path)) {
                continue;
            }

            $mtime = (int) (@filemtime($path) ?: 0);

            return Cache::remember(
                self::LOGO_CACHE_PREFIX.$mtime,
                60 * 60 * 24 * 30,
                static function () use ($path): string {
                    $raw = @file_get_contents($path);

                    if ($raw === false || $raw === '') {
                        return '';
                    }

                    return 'data:image/png;base64,'.base64_encode($raw);
                },
            );
        }

        return '';
    }

    public static function pdfCacheVersion(PlanGenerator $planGenerator): string
    {
        $planGenerator->loadMissing(['quotationPages']);

        $parts = [
            (string) $planGenerator->getKey(),
            (string) ($planGenerator->updated_at ?? ''),
            (string) ($planGenerator->quotation_page_count ?? ''),
            (string) ($planGenerator->plan_page_number ?? ''),
            (string) ($planGenerator->control_number ?? ''),
            (string) ($planGenerator->client_data ?? ''),
            (string) optional($planGenerator->issued_at)->format('Y-m-d'),
            (string) ($planGenerator->agent_name ?? ''),
            (string) ($planGenerator->population_summary ?? ''),
            PlanGeneratorBrandColor::resolve($planGenerator->brand_color),
        ];

        $matrixFingerprint = DB::table('plan_generator_columns')
            ->where('plan_generator_id', $planGenerator->getKey())
            ->selectRaw('count(*) as c, max(updated_at) as max_updated')
            ->first();

        $parts[] = (string) ($matrixFingerprint->c ?? 0);
        $parts[] = (string) ($matrixFingerprint->max_updated ?? '');

        $rateFingerprint = DB::table('plan_generator_rate_rows')
            ->where('plan_generator_id', $planGenerator->getKey())
            ->selectRaw('count(*) as c, max(updated_at) as max_updated')
            ->first();

        $parts[] = (string) ($rateFingerprint->c ?? 0);
        $parts[] = (string) ($rateFingerprint->max_updated ?? '');

        foreach ($planGenerator->quotationPages->sortBy('page_number') as $page) {
            $relativePath = (string) ($page->image_path ?? '');
            $parts[] = $relativePath;

            if ($relativePath === '') {
                continue;
            }

            $absolutePath = Storage::disk('public')->path($relativePath);
            $parts[] = (string) (is_file($absolutePath) ? @filemtime($absolutePath) : '');
            $parts[] = (string) (is_file($absolutePath) ? @filesize($absolutePath) : '');
        }

        return hash('sha256', implode('|', $parts));
    }

    public static function outputBinary(PlanGenerator $planGenerator): string
    {
        return self::make($planGenerator)->output();
    }

    public static function outputBinaryCached(PlanGenerator $planGenerator): string
    {
        $ttl = (int) config('plan-generator.pdf_cache_ttl_seconds', self::PDF_CACHE_TTL_FALLBACK_SECONDS);

        $encoded = Cache::remember(
            self::PDF_CACHE_KEY_PREFIX.self::pdfCacheVersion($planGenerator),
            max(60, $ttl),
            static fn (): string => base64_encode(self::outputBinary($planGenerator)),
        );

        $binary = base64_decode($encoded, true);

        return is_string($binary) ? $binary : self::outputBinary($planGenerator);
    }

    public static function make(PlanGenerator $planGenerator): PdfDocument
    {
        $planGenerator->loadMissing(['quotationPages']);

        $matrix = PlanGeneratorPreviewBuilder::fullMatrixFromModel($planGenerator);
        $quotationPages = self::quotationPagesForPdf($planGenerator);
        $useQuotationBody = $quotationPages !== [];

        $generatedAt = $planGenerator->updated_at instanceof Carbon
            ? $planGenerator->updated_at->timezone(config('app.timezone'))
            : now()->timezone(config('app.timezone'));

        $brandColor = PlanGeneratorBrandColor::resolve($planGenerator->brand_color);
        $brandColorBorder = PlanGeneratorBrandColor::headerBorderColor($brandColor);

        $pdf = Pdf::loadView('documents.plan-generator-preview', [
            'planGenerator' => $planGenerator,
            'columns' => $matrix['columns'],
            'rows' => $matrix['rows'],
            'rateRows' => $matrix['rate_rows'],
            'logoDataUri' => self::cachedLogoDataUri(),
            'generatedAt' => $generatedAt,
            'brandColor' => $brandColor,
            'brandColorBorder' => $brandColorBorder,
            'useQuotationBody' => $useQuotationBody,
            'quotationPages' => $quotationPages,
        ])->setPaper('a4', 'portrait');

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

    /**
     * @return list<array{page_number: int, is_plan_page: bool, image_data_uri: string}>
     */
    public static function quotationPagesForPdf(PlanGenerator $planGenerator): array
    {
        $pageCount = (int) ($planGenerator->quotation_page_count ?? 0);
        $planPageNumber = (int) ($planGenerator->plan_page_number ?? 0);

        if ($pageCount < 1 || $planPageNumber < 1 || $planGenerator->quotationPages->isEmpty()) {
            return [];
        }

        $pagesByNumber = $planGenerator->quotationPages->keyBy('page_number');
        $pages = [];

        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $pageModel = $pagesByNumber->get($pageNumber);

            $pages[] = [
                'page_number' => $pageNumber,
                'is_plan_page' => $pageNumber === $planPageNumber,
                'image_data_uri' => $pageModel !== null
                    ? PlanGeneratorPdfImageUri::forPublicPath($pageModel->image_path)
                    : '',
            ];
        }

        return $pages;
    }

    public static function filename(PlanGenerator $planGenerator): string
    {
        $slug = filled($planGenerator->name)
            ? (string) $planGenerator->name
            : 'plan-'.$planGenerator->getKey();
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $slug) ?: 'plan-generado';

        return 'plan-generado-'.$safe.'.pdf';
    }

    public static function codeLabel(PlanGenerator $planGenerator): string
    {
        return 'PLAN-'.str_pad((string) $planGenerator->getKey(), 4, '0', STR_PAD_LEFT);
    }

    public static function generatedAtLabel(Carbon $generatedAt): string
    {
        return $generatedAt->format('d/m/Y H:i');
    }
}
