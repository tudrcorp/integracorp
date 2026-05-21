<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OperationServiceOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;

class OperationServiceOrderMedicationQuotePdfService
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public static function make(OperationServiceOrder $order, array $quoteMeta, array $items): PdfDocument
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

        return Pdf::loadView('documents.operation-service-order-medication-quote-pdf', [
            'order' => $order,
            'quoteMeta' => $quoteMeta,
            'items' => $items,
            'logoDataUri' => $logoDataUri,
        ])->setPaper('a4', 'portrait');
    }
}
