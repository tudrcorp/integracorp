<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\OperationDocumentList;
use App\Models\TelemedicineAmdInform;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicineDocument;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

final class TelemedicineAmdInformRegistrar
{
    public const SESSION_PENDING_INFORM_ID = 'pending_amd_inform_id';

    /**
     * @param  array<string, mixed>  $clinicalData
     */
    public static function register(
        TelemedicineConsultationPatient $consultation,
        array $clinicalData,
        ?User $user = null,
    ): TelemedicineAmdInform {
        $user ??= Auth::user();

        $consultation->loadMissing(['telemedicineCase', 'telemedicineDoctor']);

        $doctor = $consultation->telemedicineDoctor
            ?? TelemedicineDoctor::query()->findOrFail($consultation->telemedicine_doctor_id);

        self::syncConsultationClinicalFields($consultation, $clinicalData);

        $context = array_merge($consultation->toArray(), [
            'telemedicine_consultation_id' => $consultation->id,
            'patient_phone' => $consultation->telemedicineCase?->patient_phone,
        ]);

        return self::persistInform(
            context: $context,
            clinicalData: $clinicalData,
            doctor: $doctor,
            consultationId: $consultation->id,
            supplierId: $consultation->telemedicineCase?->supplier_id ?? $doctor->supplier_id,
            user: $user,
            existingInform: self::findExistingInform($consultation->id, (int) $consultation->telemedicine_case_id),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $clinicalData
     */
    public static function registerPending(
        array $context,
        array $clinicalData,
        ?User $user = null,
        ?int $existingInformId = null,
    ): TelemedicineAmdInform {
        $user ??= Auth::user();

        $doctor = TelemedicineDoctor::query()->findOrFail((int) $context['telemedicine_doctor_id']);
        $case = TelemedicineCase::query()->find((int) $context['telemedicine_case_id']);

        $existingInform = $existingInformId
            ? TelemedicineAmdInform::query()->find($existingInformId)
            : self::findPendingInformForCase((int) $context['telemedicine_case_id'], $user?->id);

        return self::persistInform(
            context: array_merge($context, [
                'telemedicine_consultation_id' => 0,
                'patient_phone' => $case?->patient_phone,
            ]),
            clinicalData: $clinicalData,
            doctor: $doctor,
            consultationId: null,
            supplierId: $case?->supplier_id ?? $doctor->supplier_id,
            user: $user,
            existingInform: $existingInform,
        );
    }

    public static function attachPendingToConsultation(
        TelemedicineConsultationPatient $consultation,
        ?int $pendingInformId = null,
    ): ?TelemedicineAmdInform {
        $inform = self::resolvePendingInform($consultation, $pendingInformId);

        if (! $inform instanceof TelemedicineAmdInform) {
            return null;
        }

        self::syncConsultationClinicalFields($consultation, $inform->toArray());

        $inform->update([
            'telemedicine_consultation_patient_id' => $consultation->id,
            'telemedicine_doctor_id' => $consultation->telemedicine_doctor_id,
            'telemedicine_patient_id' => $consultation->telemedicine_patient_id,
        ]);

        $inform = $inform->refresh();

        self::ensureInformPdfExists($inform, $consultation);

        self::syncConsultationUploadedDocument($consultation, $inform);
        self::syncTelemedicineDocument($consultation, $inform);

        session()->forget(self::SESSION_PENDING_INFORM_ID);

        return $inform->refresh();
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $clinicalData
     */
    private static function persistInform(
        array $context,
        array $clinicalData,
        TelemedicineDoctor $doctor,
        ?int $consultationId,
        mixed $supplierId,
        ?User $user,
        ?TelemedicineAmdInform $existingInform = null,
    ): TelemedicineAmdInform {
        $pdfData = TelemedicineInformeLargoDataBuilder::buildFromContext($context, $doctor, $clinicalData);
        $pdfDocumentName = TelemedicineInformeLargoDataBuilder::pdfDocumentName($pdfData);
        $pdfFilePath = 'telemedicina-doc/'.$pdfDocumentName;

        $attributes = [
            'telemedicine_patient_id' => (int) $context['telemedicine_patient_id'],
            'telemedicine_case_id' => (int) $context['telemedicine_case_id'],
            'telemedicine_consultation_patient_id' => $consultationId,
            'telemedicine_doctor_id' => (int) $context['telemedicine_doctor_id'],
            'supplier_id' => $supplierId,
            'reason_consultation' => $clinicalData['reason_consultation'] ?? null,
            'actual_phatology' => $clinicalData['actual_phatology'] ?? null,
            'background' => $clinicalData['background'] ?? null,
            'diagnostic_impression' => $clinicalData['diagnostic_impression'] ?? null,
            'pa' => $clinicalData['pa'] ?? null,
            'fc' => $clinicalData['fc'] ?? null,
            'fr' => $clinicalData['fr'] ?? null,
            'temp' => $clinicalData['temp'] ?? null,
            'saturacion' => $clinicalData['saturacion'] ?? null,
            'peso' => $clinicalData['peso'] ?? null,
            'estatura' => $clinicalData['estatura'] ?? null,
            'imc' => $clinicalData['imc'] ?? null,
            'pdf_document_name' => $pdfDocumentName,
            'pdf_file_path' => $pdfFilePath,
            'created_by' => $user?->id,
        ];

        $inform = $existingInform instanceof TelemedicineAmdInform
            ? tap($existingInform)->update($attributes)
            : TelemedicineAmdInform::query()->create($attributes);

        self::ensureInformPdfExists($inform->refresh(), null, $pdfData, $doctor, $clinicalData, $context);

        if ($consultationId !== null) {
            $consultation = TelemedicineConsultationPatient::query()->find($consultationId);

            if ($consultation) {
                $inform->refresh();
                self::syncConsultationUploadedDocument($consultation, $inform);
                self::syncTelemedicineDocument($consultation, $inform);
            }
        }

        return $inform;
    }

    /**
     * @param  array<string, mixed>  $clinicalData
     */
    private static function syncConsultationClinicalFields(
        TelemedicineConsultationPatient $consultation,
        array $clinicalData,
    ): void {
        $consultation->update([
            'reason_consultation' => $clinicalData['reason_consultation'] ?? $consultation->reason_consultation,
            'actual_phatology' => $clinicalData['actual_phatology'] ?? $consultation->actual_phatology,
            'background' => $clinicalData['background'] ?? $consultation->background,
            'diagnostic_impression' => $clinicalData['diagnostic_impression'] ?? $consultation->diagnostic_impression,
            'pa' => $clinicalData['pa'] ?? $consultation->pa,
            'fc' => $clinicalData['fc'] ?? $consultation->fc,
            'fr' => $clinicalData['fr'] ?? $consultation->fr,
            'temp' => $clinicalData['temp'] ?? $consultation->temp,
            'saturacion' => $clinicalData['saturacion'] ?? $consultation->saturacion,
            'peso' => $clinicalData['peso'] ?? $consultation->peso,
            'estatura' => $clinicalData['estatura'] ?? $consultation->estatura,
            'imc' => $clinicalData['imc'] ?? $consultation->imc,
        ]);
    }

    private static function syncConsultationUploadedDocument(
        TelemedicineConsultationPatient $consultation,
        TelemedicineAmdInform $inform,
    ): void {
        if (! filled($inform->pdf_document_name)) {
            return;
        }

        if (! Schema::hasColumn($consultation->getTable(), 'uploaded_documents')) {
            return;
        }

        $defaultDocumentTypeId = 9;
        $defaultDocumentTypeName = trim((string) OperationDocumentList::query()
            ->whereKey($defaultDocumentTypeId)
            ->value('name'));

        if ($defaultDocumentTypeName === '') {
            $defaultDocumentTypeName = 'INFORME MEDICO CONSULTA INICIAL (LARGO)';
        }

        $existingDocuments = is_array($consultation->uploaded_documents)
            ? $consultation->uploaded_documents
            : [];

        $filePath = $inform->pdf_file_path ?? 'telemedicina-doc/'.$inform->pdf_document_name;

        $existingDocuments = array_values(array_filter(
            $existingDocuments,
            static fn (mixed $document): bool => ! is_array($document)
                || ($document['document_name'] ?? null) !== $inform->pdf_document_name,
        ));

        $existingDocuments[] = [
            'document_name' => $inform->pdf_document_name,
            'file_path' => $filePath,
            'document_type_ids' => [$defaultDocumentTypeId],
            'document_types' => [$defaultDocumentTypeName],
            'uploaded_at' => now()->toDateTimeString(),
        ];

        $consultation->update([
            'uploaded_documents' => $existingDocuments,
        ]);
    }

    private static function syncTelemedicineDocument(
        TelemedicineConsultationPatient $consultation,
        TelemedicineAmdInform $inform,
    ): void {
        if (! filled($inform->pdf_document_name)) {
            return;
        }

        if (! Schema::hasTable('telemedicine_documents')) {
            return;
        }

        $searchAttributes = [
            'telemedicine_case_id' => $consultation->telemedicine_case_id,
            'name' => $inform->pdf_document_name,
        ];

        $valueAttributes = $searchAttributes;

        if (Schema::hasColumn('telemedicine_documents', 'telemedicine_consultation_id')) {
            $valueAttributes['telemedicine_consultation_id'] = $consultation->id;
        }

        if (Schema::hasColumn('telemedicine_documents', 'telemedicine_patient_id')) {
            $valueAttributes['telemedicine_patient_id'] = $consultation->telemedicine_patient_id;
        }

        if (Schema::hasColumn('telemedicine_documents', 'telemedicine_case_code')) {
            $valueAttributes['telemedicine_case_code'] = $consultation->telemedicine_case_code;
        }

        TelemedicineDocument::query()->updateOrCreate($searchAttributes, $valueAttributes);
    }

    private static function resolvePendingInform(
        TelemedicineConsultationPatient $consultation,
        ?int $pendingInformId,
    ): ?TelemedicineAmdInform {
        if ($pendingInformId) {
            $inform = TelemedicineAmdInform::query()->find($pendingInformId);

            if ($inform && $inform->telemedicine_case_id === $consultation->telemedicine_case_id) {
                return $inform;
            }
        }

        return self::findPendingInformForCase((int) $consultation->telemedicine_case_id);
    }

    private static function findPendingInformForCase(int $caseId, ?int $createdBy = null): ?TelemedicineAmdInform
    {
        $query = TelemedicineAmdInform::query()
            ->where('telemedicine_case_id', $caseId)
            ->whereNull('telemedicine_consultation_patient_id')
            ->latest('id');

        if ($createdBy) {
            $query->where('created_by', $createdBy);
        }

        return $query->first();
    }

    private static function findExistingInform(?int $consultationId, int $caseId): ?TelemedicineAmdInform
    {
        if ($consultationId) {
            $byConsultation = TelemedicineAmdInform::query()
                ->where('telemedicine_consultation_patient_id', $consultationId)
                ->latest('id')
                ->first();

            if ($byConsultation) {
                return $byConsultation;
            }
        }

        return self::findPendingInformForCase($caseId);
    }

    /**
     * @param  array<string, mixed>|null  $pdfData
     * @param  array<string, mixed>|null  $clinicalData
     * @param  array<string, mixed>|null  $context
     */
    private static function ensureInformPdfExists(
        TelemedicineAmdInform $inform,
        ?TelemedicineConsultationPatient $consultation = null,
        ?array $pdfData = null,
        ?TelemedicineDoctor $doctor = null,
        ?array $clinicalData = null,
        ?array $context = null,
    ): void {
        if (filled($inform->pdf_document_name) && TelemedicineInformeLargoPdfGenerator::fileExists($inform->pdf_document_name)) {
            return;
        }

        if ($pdfData === null || $doctor === null) {
            $consultation ??= $inform->telemedicine_consultation_patient_id
                ? TelemedicineConsultationPatient::query()->find($inform->telemedicine_consultation_patient_id)
                : null;

            if (! $consultation instanceof TelemedicineConsultationPatient) {
                return;
            }

            $consultation->loadMissing(['telemedicineCase', 'telemedicineDoctor']);
            $doctor = $consultation->telemedicineDoctor
                ?? TelemedicineDoctor::query()->find($consultation->telemedicine_doctor_id);

            if (! $doctor instanceof TelemedicineDoctor) {
                return;
            }

            $clinicalData = $inform->toArray();
            $context = array_merge($consultation->toArray(), [
                'telemedicine_consultation_id' => $consultation->id,
                'patient_phone' => $consultation->telemedicineCase?->patient_phone,
            ]);
        }

        $pdfData ??= TelemedicineInformeLargoDataBuilder::buildFromContext($context, $doctor, $clinicalData ?? []);
        $fileName = TelemedicineInformeLargoPdfGenerator::generateAndSave($pdfData, 'informe-largo');

        $inform->update([
            'pdf_document_name' => $fileName,
            'pdf_file_path' => TelemedicineInformeLargoPdfGenerator::STORAGE_DIRECTORY.'/'.$fileName,
        ]);
    }
}
