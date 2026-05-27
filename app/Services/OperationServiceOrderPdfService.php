<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OperationServiceOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;

class OperationServiceOrderPdfService
{
    public static function make(OperationServiceOrder $order): PdfDocument
    {
        $order->loadMissing([
            'operationCoordinationService.state',
            'operationCoordinationService.city',
            'supplier',
            'doctorNurse',
            'telemedicinePriority',
            'operationInventoryUbication',
            'operationServiceOrderItems',
        ]);

        $logoPath = public_path('image/logoNewPdf.png');
        $logoDataUri = '';
        if (is_file($logoPath)) {
            $logoDataUri = 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath));
        }

        return Pdf::loadView('documents.operation-service-order-pdf', [
            'order' => $order,
            'logoDataUri' => $logoDataUri,
        ])->setPaper('a4', 'portrait');
    }

    public static function filename(OperationServiceOrder $order): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $order->order_number) ?: 'orden';

        return 'orden-servicio-'.$safe.'.pdf';
    }
}
