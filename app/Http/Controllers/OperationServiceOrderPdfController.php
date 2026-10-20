<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\OperationServiceOrder;
use App\Services\OperationServiceOrderPdfService;
use App\Support\Operations\ServiceOrderPdfAccess;
use App\Support\SecurityAudit;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

class OperationServiceOrderPdfController extends Controller
{
    public function download(Request $request, OperationServiceOrder $operationServiceOrder): Response
    {
        $this->authorizeOrder($request, $operationServiceOrder, 'operations.operation-service-orders.pdf');

        return $this->pdfResponse($request, $operationServiceOrder, 'attachment');
    }

    public function preview(Request $request, OperationServiceOrder $operationServiceOrder): Response
    {
        $this->authorizeOrder($request, $operationServiceOrder, 'operations.operation-service-orders.pdf.preview');

        return $this->pdfResponse($request, $operationServiceOrder, 'inline');
    }

    /**
     * Entrega la copia vigente (la misma que se guarda y se envía), renderizando solo si cambió.
     * La huella del contenido viaja como ETag: si el navegador ya tiene esa versión responde 304.
     */
    private function pdfResponse(Request $request, OperationServiceOrder $order, string $disposition): Response
    {
        $document = OperationServiceOrderPdfService::document($order);
        $etag = '"'.$document['fingerprint'].'"';
        $headers = [
            'Cache-Control' => 'private, no-cache',
            'ETag' => $etag,
        ];

        if (in_array($etag, $request->getETags(), true)) {
            return response('', Response::HTTP_NOT_MODIFIED, $headers);
        }

        return response($document['contents'], Response::HTTP_OK, [
            ...$headers,
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $disposition,
                OperationServiceOrderPdfService::filename($order),
            ),
        ]);
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
