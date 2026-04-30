<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\OperationCoordinationClinicDocument;
use App\Support\SecurityAudit;
use App\Support\Telemedicine\TelemedicineDerivedServiceBadge;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OperationCoordinationClinicDocumentDownloadController extends Controller
{
    public function download(OperationCoordinationClinicDocument $document): BinaryFileResponse
    {
        if (! Auth::check()) {
            abort(403);
        }

        $service = $document->operationCoordinationService;

        if ($service === null || ! TelemedicineDerivedServiceBadge::specificServiceIsIngresoAClinica($service->specific_service)) {
            abort(404);
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($document->path)) {
            abort(404, 'Archivo no encontrado.');
        }

        SecurityAudit::log(
            action: 'AUDIT_OPERATIONS_COORDINATION_CLINIC_DOCUMENT_DOWNLOADED',
            route: 'operations.coordination.clinic-documents.download',
            details: [
                'document_id' => $document->id,
                'operation_coordination_service_id' => $document->operation_coordination_service_id,
                'category' => $document->category,
                'path' => $document->path,
            ],
        );

        $downloadName = filled($document->original_filename)
            ? $document->original_filename
            : basename($document->path);

        return response()->download(
            file: $disk->path($document->path),
            name: $downloadName,
        );
    }
}
