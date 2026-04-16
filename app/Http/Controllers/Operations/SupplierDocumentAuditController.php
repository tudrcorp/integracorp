<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SupplierDocumentAuditController extends Controller
{
    public function previewCartaAcceptance(Supplier $supplier): BinaryFileResponse
    {
        $path = $supplier->carta_acceptance;

        if (! is_string($path) || trim($path) === '' || ! Storage::disk('public')->exists($path)) {
            abort(404, 'Carta de aceptación no encontrada.');
        }

        SecurityAudit::log('AUDIT_OPERATIONS_SUPPLIER_DOCUMENT_VIEWED', 'operations.suppliers.carta-acceptance.preview', [
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'document_type' => 'CARTA_ACEPTACION',
            'path' => $path,
        ]);

        return response()->file(Storage::disk('public')->path($path));
    }

    public function downloadAffiliationDocument(Supplier $supplier, int $index): BinaryFileResponse
    {
        $documents = is_array($supplier->documents) ? array_values($supplier->documents) : [];
        $path = $documents[$index] ?? null;

        if (! is_string($path) || trim($path) === '' || ! Storage::disk('public')->exists($path)) {
            abort(404, 'Documento no encontrado.');
        }

        SecurityAudit::log('AUDIT_OPERATIONS_SUPPLIER_DOCUMENT_DOWNLOADED', 'operations.suppliers.documents.download', [
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'document_type' => 'AFILIACION',
            'document_index' => $index,
            'path' => $path,
        ]);

        return response()->download(
            file: Storage::disk('public')->path($path),
            name: basename($path),
        );
    }

    public function downloadCartaAcceptance(Supplier $supplier): BinaryFileResponse
    {
        $path = $supplier->carta_acceptance;

        if (! is_string($path) || trim($path) === '' || ! Storage::disk('public')->exists($path)) {
            abort(404, 'Carta de aceptación no encontrada.');
        }

        SecurityAudit::log('AUDIT_OPERATIONS_SUPPLIER_DOCUMENT_DOWNLOADED', 'operations.suppliers.carta-acceptance.download', [
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'document_type' => 'CARTA_ACEPTACION',
            'path' => $path,
        ]);

        return response()->download(
            file: Storage::disk('public')->path($path),
            name: basename($path),
        );
    }
}
