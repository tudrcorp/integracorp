<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Filament\Operations\Resources\OperationMedicationInvoices\OperationMedicationInvoiceResource;
use App\Models\OperationMedicationInvoice;
use App\Services\MedicationInvoicePdfService;
use App\Support\SecurityAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vista previa y descarga del PDF de la factura de medicamentos, y el enlace firmado para WhatsApp.
 *
 * Quien no puede ver la factura (sin acceso a la pantalla o fuera del alcance de su proveedor)
 * recibe 404: no debe saber que existe. La huella del contenido viaja como ETag.
 */
class MedicationInvoicePdfController extends Controller
{
    public function preview(Request $request, OperationMedicationInvoice $operationMedicationInvoice): Response
    {
        return $this->respond($request, $operationMedicationInvoice, 'inline');
    }

    public function download(Request $request, OperationMedicationInvoice $operationMedicationInvoice): Response
    {
        return $this->respond($request, $operationMedicationInvoice, 'attachment');
    }

    /**
     * Copia publicada para WhatsApp: solo con firma vigente.
     */
    public function shared(Request $request, string $share): Response
    {
        $path = MedicationInvoicePdfService::sharedPath($share);

        abort_if($path === null, 404);

        return response((string) Storage::disk(MedicationInvoicePdfService::DISK)->get($path), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition('inline', 'factura-medicamentos.pdf'),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public static function userCanView(OperationMedicationInvoice $invoice): bool
    {
        return OperationMedicationInvoiceResource::canViewAny()
            && OperationMedicationInvoiceResource::getEloquentQuery()->whereKey($invoice->getKey())->exists();
    }

    private function respond(Request $request, OperationMedicationInvoice $invoice, string $disposition): Response
    {
        if (! self::userCanView($invoice)) {
            SecurityAudit::log('AUDIT_OPERATIONS_MEDICATION_INVOICE_PDF_DENIED', 'operations.medication-invoices.pdf', ['operation_medication_invoice_id' => $invoice->getKey()]);

            abort(404);
        }

        $document = MedicationInvoicePdfService::document($invoice);
        $etag = '"'.$document['fingerprint'].'"';
        $headers = ['Cache-Control' => 'private, no-cache', 'ETag' => $etag];

        if (in_array($etag, $request->getETags(), true)) {
            return response('', Response::HTTP_NOT_MODIFIED, $headers);
        }

        return response($document['contents'], Response::HTTP_OK, [
            ...$headers,
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition($disposition, $document['filename']),
        ]);
    }
}
