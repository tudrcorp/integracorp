<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PlanGenerator;
use App\Support\PlanGenerators\PlanGeneratorPreviewBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class PlanGeneratorPdfService
{
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

    public static function logoDataUri(): string
    {
        foreach (self::logoCandidatePaths() as $path) {
            if (is_file($path)) {
                return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
            }
        }

        return '';
    }

    public static function imageDataUri(?string $relativePath): string
    {
        if (! filled($relativePath)) {
            return '';
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($relativePath)) {
            return '';
        }

        $contents = $disk->get($relativePath);
        $extension = strtolower((string) pathinfo($relativePath, PATHINFO_EXTENSION));

        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return 'data:'.$mime.';base64,'.base64_encode((string) $contents);
    }

    public static function make(PlanGenerator $planGenerator): PdfDocument
    {
        $planGenerator->loadMissing(['quotationPages']);

        $matrix = PlanGeneratorPreviewBuilder::fullMatrixFromModel($planGenerator);
        $quotationPages = self::quotationPagesForPdf($planGenerator);
        $useQuotationBody = $quotationPages !== [];

        return Pdf::loadView('documents.plan-generator-preview', [
            'planGenerator' => $planGenerator,
            'columns' => $matrix['columns'],
            'rows' => $matrix['rows'],
            'rateRows' => $matrix['rate_rows'],
            'logoDataUri' => self::logoDataUri(),
            'generatedAt' => now()->timezone(config('app.timezone')),
            'useQuotationBody' => $useQuotationBody,
            'quotationPages' => $quotationPages,
        ])->setPaper('a4', 'portrait');
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
                    ? self::imageDataUri($pageModel->image_path)
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
