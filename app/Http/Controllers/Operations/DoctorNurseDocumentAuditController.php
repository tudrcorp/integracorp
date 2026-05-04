<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\DoctorNurse;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DoctorNurseDocumentAuditController extends Controller
{
    public function previewCartaAcceptance(DoctorNurse $doctorNurse): BinaryFileResponse
    {
        $path = $doctorNurse->carta_acceptance;

        if (! is_string($path) || trim($path) === '' || ! Storage::disk('public')->exists($path)) {
            abort(404, 'Carta de aceptación no encontrada.');
        }

        SecurityAudit::log('AUDIT_OPERATIONS_DOCTOR_NURSE_DOCUMENT_VIEWED', 'operations.doctor-nurses.carta-acceptance.preview', [
            'doctor_nurse_id' => $doctorNurse->id,
            'doctor_nurse_name' => $doctorNurse->name,
            'document_type' => 'CARTA_ACEPTACION',
            'path' => $path,
        ]);

        return response()->file(Storage::disk('public')->path($path));
    }

    public function downloadAffiliationDocument(DoctorNurse $doctorNurse, int $index): BinaryFileResponse
    {
        $documents = is_array($doctorNurse->documents) ? array_values($doctorNurse->documents) : [];
        $path = $documents[$index] ?? null;

        if (! is_string($path) || trim($path) === '' || ! Storage::disk('public')->exists($path)) {
            abort(404, 'Documento no encontrado.');
        }

        SecurityAudit::log('AUDIT_OPERATIONS_DOCTOR_NURSE_DOCUMENT_DOWNLOADED', 'operations.doctor-nurses.documents.download', [
            'doctor_nurse_id' => $doctorNurse->id,
            'doctor_nurse_name' => $doctorNurse->name,
            'document_type' => 'AFILIACION',
            'document_index' => $index,
            'path' => $path,
        ]);

        return response()->download(
            file: Storage::disk('public')->path($path),
            name: basename($path),
        );
    }

    public function downloadCartaAcceptance(DoctorNurse $doctorNurse): BinaryFileResponse
    {
        $path = $doctorNurse->carta_acceptance;

        if (! is_string($path) || trim($path) === '' || ! Storage::disk('public')->exists($path)) {
            abort(404, 'Carta de aceptación no encontrada.');
        }

        SecurityAudit::log('AUDIT_OPERATIONS_DOCTOR_NURSE_DOCUMENT_DOWNLOADED', 'operations.doctor-nurses.carta-acceptance.download', [
            'doctor_nurse_id' => $doctorNurse->id,
            'doctor_nurse_name' => $doctorNurse->name,
            'document_type' => 'CARTA_ACEPTACION',
            'path' => $path,
        ]);

        return response()->download(
            file: Storage::disk('public')->path($path),
            name: basename($path),
        );
    }
}
