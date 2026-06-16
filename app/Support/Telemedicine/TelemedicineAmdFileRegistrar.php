<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineDocument;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class TelemedicineAmdFileRegistrar
{
    public const SESSION_PENDING_DOCUMENT_IDS = 'pending_amd_document_ids';

    public const DOCUMENT_TYPE_LABEL = 'ARCHIVO AMD';

    public static function register(
        int $caseId,
        int $patientId,
        mixed $fileState,
        ?int $consultationId = null,
    ): ?TelemedicineDocument {
        $relativePath = self::resolveStoredFilePath($fileState);

        if ($relativePath === null) {
            return null;
        }

        $fileName = basename($relativePath);

        $document = self::createTelemedicineDocument($caseId, $patientId, $consultationId, $fileName);

        if ($consultationId !== null && $consultationId > 0) {
            $consultation = TelemedicineConsultationPatient::query()->find($consultationId);

            if ($consultation instanceof TelemedicineConsultationPatient) {
                self::syncConsultationUploadedDocument($consultation, $fileName, $relativePath);
            }
        } else {
            $pendingIds = session()->get(self::SESSION_PENDING_DOCUMENT_IDS, []);

            if (! is_array($pendingIds)) {
                $pendingIds = [];
            }

            $pendingIds[] = $document->id;
            session()->put(self::SESSION_PENDING_DOCUMENT_IDS, array_values(array_unique($pendingIds)));
        }

        return $document;
    }

    public static function attachPendingToConsultation(TelemedicineConsultationPatient $consultation): void
    {
        $pendingIds = session()->pull(self::SESSION_PENDING_DOCUMENT_IDS, []);

        if (! is_array($pendingIds) || $pendingIds === []) {
            return;
        }

        TelemedicineDocument::query()
            ->whereIn('id', $pendingIds)
            ->where('telemedicine_case_id', $consultation->telemedicine_case_id)
            ->get()
            ->each(function (TelemedicineDocument $document) use ($consultation): void {
                if (Schema::hasColumn($document->getTable(), 'telemedicine_consultation_id')) {
                    $document->update([
                        'telemedicine_consultation_id' => $consultation->id,
                    ]);
                }

                self::syncConsultationUploadedDocument(
                    $consultation,
                    (string) $document->name,
                    'telemedicina-doc/'.ltrim((string) $document->name, '/'),
                );
            });
    }

    public static function resolveStoredFilePath(mixed $fileState): ?string
    {
        $path = null;

        if (is_string($fileState) && $fileState !== '') {
            $path = $fileState;
        }

        if (is_array($fileState)) {
            $first = reset($fileState);

            if (is_string($first) && $first !== '') {
                $path = $first;
            }
        }

        if ($path === null) {
            return null;
        }

        $path = ltrim($path, '/');

        if (str_starts_with($path, 'telemedicina-doc/')) {
            return Storage::disk('public')->exists($path) ? $path : null;
        }

        $candidate = 'telemedicina-doc/'.$path;

        return Storage::disk('public')->exists($candidate) ? $candidate : null;
    }

    private static function createTelemedicineDocument(
        int $caseId,
        int $patientId,
        ?int $consultationId,
        string $fileName,
    ): TelemedicineDocument {
        if (! Schema::hasTable('telemedicine_documents')) {
            throw new \RuntimeException('La tabla telemedicine_documents no está disponible.');
        }

        $attributes = [
            'telemedicine_case_id' => $caseId,
            'name' => $fileName,
        ];

        if (Schema::hasColumn('telemedicine_documents', 'telemedicine_patient_id')) {
            $attributes['telemedicine_patient_id'] = $patientId;
        }

        if (Schema::hasColumn('telemedicine_documents', 'telemedicine_consultation_id')) {
            $attributes['telemedicine_consultation_id'] = $consultationId ?? 0;
        }

        return TelemedicineDocument::query()->create($attributes);
    }

    private static function syncConsultationUploadedDocument(
        TelemedicineConsultationPatient $consultation,
        string $fileName,
        string $relativePath,
    ): void {
        if (! Schema::hasColumn($consultation->getTable(), 'uploaded_documents')) {
            return;
        }

        $existingDocuments = is_array($consultation->uploaded_documents)
            ? $consultation->uploaded_documents
            : [];

        $existingDocuments = array_values(array_filter(
            $existingDocuments,
            static fn (mixed $document): bool => ! is_array($document)
                || ($document['document_name'] ?? null) !== $fileName,
        ));

        $existingDocuments[] = [
            'document_name' => $fileName,
            'file_path' => $relativePath,
            'document_type_ids' => [],
            'document_types' => [self::DOCUMENT_TYPE_LABEL],
            'uploaded_at' => now()->toDateTimeString(),
        ];

        $consultation->update([
            'uploaded_documents' => $existingDocuments,
        ]);
    }
}
