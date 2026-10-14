<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\OperationServiceOrder;
use App\Services\OperationServiceOrderPdfService;
use App\Support\Operations\ServiceOrderPdfAccess;
use App\Support\SecurityAudit;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OperationServiceOrderPdfController extends Controller
{
    public function download(Request $request, OperationServiceOrder $operationServiceOrder): Response
    {
        $this->authorizeOrder($request, $operationServiceOrder, 'operations.operation-service-orders.pdf');

        OperationServiceOrderPdfService::ensurePersisted($operationServiceOrder);

        $pdf = OperationServiceOrderPdfService::make($operationServiceOrder);

        return $pdf->download(OperationServiceOrderPdfService::filename($operationServiceOrder));
    }

    public function preview(Request $request, OperationServiceOrder $operationServiceOrder): Response
    {
        $this->authorizeOrder($request, $operationServiceOrder, 'operations.operation-service-orders.pdf.preview');

        OperationServiceOrderPdfService::ensurePersisted($operationServiceOrder);

        $pdf = OperationServiceOrderPdfService::make($operationServiceOrder);

        return $pdf->stream(OperationServiceOrderPdfService::filename($operationServiceOrder));
    }

    /**
     * 404 y no 403: quien no puede ver la orden tampoco debe saber que existe.
     * Se corta antes de generar o guardar el PDF.
     */
    private function authorizeOrder(Request $request, OperationServiceOrder $order, string $route): void
    {
        if (ServiceOrderPdfAccess::canView($request->user(), $order)) {
            return;
        }

        SecurityAudit::log('AUDIT_OPERATIONS_SERVICE_ORDER_PDF_DENIED', $route, [
            'operation_service_order_id' => $order->getKey(),
        ]);

        abort(404);
    }
}
