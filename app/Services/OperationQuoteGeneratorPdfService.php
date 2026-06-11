<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OperationCoordinationService;
use App\Models\OperationQuoteGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Facades\Storage;

class OperationQuoteGeneratorPdfService
{
    public static function make(
        OperationQuoteGenerator $quote,
        OperationCoordinationService $coordination,
        float $bcvRate
    ): PdfDocument {
        $quote->loadMissing('supplier');

        $logoPath = public_path('image/logoNewPdf.png');
        $logoDataUri = '';
        if (is_file($logoPath)) {
            $logoDataUri = 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath));
        }

        return Pdf::loadView('documents.operation-quote-generator-pdf', [
            'quote' => $quote,
            'coordination' => $coordination,
            'bcvRate' => $bcvRate,
            'logoDataUri' => $logoDataUri,
        ])->setPaper('a4', 'portrait');
    }

    public static function filename(OperationQuoteGenerator $quote): string
    {
        return 'cotizacion-coordinacion-'.((int) $quote->id).'.pdf';
    }

    public static function store(
        OperationQuoteGenerator $quote,
        OperationCoordinationService $coordination,
        float $bcvRate
    ): string {
        $disk = Storage::disk('public');
        $baseDirectory = 'operation-quote-generators/generated-pdf';
        $timestamp = now()->format('YmdHis');
        $relativePath = $baseDirectory.'/quote-'.((int) $quote->id).'-'.$timestamp.'.pdf';

        $disk->put($relativePath, self::make($quote, $coordination, $bcvRate)->output());

        return $relativePath;
    }
}
