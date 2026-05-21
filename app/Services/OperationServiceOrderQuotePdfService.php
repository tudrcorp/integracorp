<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OperationServiceOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;

class OperationServiceOrderQuotePdfService
{
    public static function make(OperationServiceOrder $order, array $quoteData): PdfDocument
    {
        $order->loadMissing([
            'operationCoordinationService',
            'supplier',
            'telemedicinePriority',
        ]);

        $logoPath = public_path('image/logoNewPdf.png');
        $logoDataUri = '';
        if (is_file($logoPath)) {
            $logoDataUri = 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath));
        }

        return Pdf::loadView('documents.operation-service-order-quote-pdf', [
            'order' => $order,
            'quoteData' => $quoteData,
            'logoDataUri' => $logoDataUri,
        ])->setPaper('a4', 'portrait');
    }

    public static function filename(OperationServiceOrder $order): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $order->order_number) ?: 'orden';

        return 'cotizacion-asociada-'.$safe.'.pdf';
    }
}
