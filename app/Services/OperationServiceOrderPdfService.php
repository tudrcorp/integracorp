<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OperationServiceOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Facades\Storage;

class OperationServiceOrderPdfService
{
    public static function make(OperationServiceOrder $order): PdfDocument
    {
        $order->loadMissing([
            'operationCoordinationService.state',
            'operationCoordinationService.city',
            'supplier.state',
            'supplier.city',
            'doctorNurse',
            'approvedOperationQuote',
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
        $safe = preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string) $order->order_number) ?: 'orden';

        return 'orden-servicio-'.$safe.'.pdf';
    }

    /**
     * Garantiza un PDF persistido en disco público y actualiza service_order_pdf_path.
     * Útil para órdenes creadas antes de persistir el PDF o sin pasar por el flujo de coordinación.
     */
    public static function ensurePersisted(OperationServiceOrder $order, bool $force = false): string
    {
        $disk = Storage::disk('public');
        $current = trim((string) ($order->service_order_pdf_path ?? ''));

        if (! $force && $current !== '' && $disk->exists($current) && $disk->size($current) > 100) {
            return $current;
        }

        $safeOrder = preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string) $order->order_number) ?: (string) $order->id;
        $relativePath = 'operation-service-orders/generated-pdf/service-order-'.$safeOrder.'-'.now()->format('YmdHis').'.pdf';

        $disk->put($relativePath, self::make($order)->output());

        $order->service_order_pdf_path = $relativePath;
        $order->save();

        return $relativePath;
    }
}
