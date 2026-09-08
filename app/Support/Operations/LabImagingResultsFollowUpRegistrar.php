<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\ObservationCase;
use App\Models\OperationCoordinationService;
use App\Models\OperationDocumentList;
use App\Models\OperationServiceOrder;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineHistoryPatient;
use App\Models\TelemedicinePatient;
use App\Models\TelemedicineServiceList;
use App\Support\Telemedicine\ConsultationCreateRoute;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Cuando Operaciones carga resultados de laboratorio o imagenología, genera
 * (o reutiliza) un seguimiento en el caso cuyo servicio principal es
 * «SEGUIMIENTO MÉDICO/LECTURA DE RESULTADOS» para que el médico lo actualice.
 */
final class LabImagingResultsFollowUpRegistrar
{
    public const FOLLOW_UP_SERVICE_NAME = 'SEGUIMIENTO MÉDICO/LECTURA DE RESULTADOS';

    public const CONSULTATION_STATUS = 'EN SEGUIMIENTO';

    public const CASE_STATUS_ALTA_MEDICA = 'ALTA MEDICA';

    public const REASON_CONSULTATION = 'LECTURA DE RESULTADOS DE LABORATORIO Y/O IMAGENOLOGÍA.';

    /**
     * @var list<string>
     */
    public const RESULT_DOCUMENT_TYPE_NAMES = [
        'RESULTADOS DE LABORATORIO',
        'INFORME DE RESULTADOS DE LABORATORIOS',
        'INFORME DE ESTUDIO Y/O IMAGENOLOGIA',
    ];

    /**
     * @var list<string>
     */
    public const LAB_OR_IMAGING_SERVICE_TYPES = [
        'LABORATORIOS',
        'IMAGENOLOGIA',
    ];

    /**
     * @return array{
     *     triggered: bool,
     *     created: bool,
     *     reused: bool,
     *     case_reopened: bool,
     *     consultation: ?TelemedicineConsultationPatient,
     *     message: ?string
     * }
     */
    public static function emptyResult(): array
    {
        return [
            'triggered' => false,
            'created' => false,
            'reused' => false,
            'case_reopened' => false,
            'consultation' => null,
            'message' => null,
        ];
    }

    public static function normalize(?string $value): string
    {
        $collapsed = preg_replace('/\s+/', ' ', trim((string) $value));

        return strtoupper(Str::ascii((string) $collapsed));
    }

    public static function isResultDocumentTypeName(?string $name): bool
    {
        $normalized = self::normalize($name);

        if ($normalized === '') {
            return false;
        }

        foreach (self::RESULT_DOCUMENT_TYPE_NAMES as $expected) {
            if ($normalized === self::normalize($expected)) {
                return true;
            }
        }

        return false;
    }

    public static function isLabOrImagingServiceType(?string $serviceType): bool
    {
        $normalized = self::normalize($serviceType);

        return in_array($normalized, self::LAB_OR_IMAGING_SERVICE_TYPES, true);
    }

    /**
     * @param  list<mixed>|mixed  $serviceItemKeys
     */
    public static function serviceKeysAreLabOrImaging(mixed $serviceItemKeys): bool
    {
        $keys = is_array($serviceItemKeys) ? $serviceItemKeys : [];

        foreach ($keys as $key) {
            $prefix = strstr((string) $key, ':', true);

            if (in_array($prefix, ['lab', 'study'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<int, string>  $typeNamesById
     */
    public static function documentHasResultType(array $document, array $typeNamesById = []): bool
    {
        $names = $document['document_types'] ?? [];

        if (is_array($names)) {
            foreach ($names as $name) {
                if (self::isResultDocumentTypeName(is_string($name) ? $name : null)) {
                    return true;
                }
            }
        }

        $ids = $document['document_type_ids'] ?? [];

        if (! is_array($ids)) {
            return false;
        }

        foreach ($ids as $id) {
            $name = $typeNamesById[(int) $id] ?? null;
            if (self::isResultDocumentTypeName(is_string($name) ? $name : null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     * @param  array<int, string>  $typeNamesById
     */
    public static function documentsHaveResultType(array $documents, array $typeNamesById = []): bool
    {
        foreach ($documents as $document) {
            if (is_array($document) && self::documentHasResultType($document, $typeNamesById)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     */
    public static function documentsHaveLabOrImagingKeys(array $documents): bool
    {
        foreach ($documents as $document) {
            if (is_array($document) && self::serviceKeysAreLabOrImaging($document['service_item_keys'] ?? [])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     */
    public static function shouldRegister(
        array $documents,
        ?string $orderServiceType,
        bool $coordinationHasLabOrImaging,
        array $typeNamesById = [],
    ): bool {
        if ($documents === [] || ! self::documentsHaveResultType($documents, $typeNamesById)) {
            return false;
        }

        return self::documentsHaveLabOrImagingKeys($documents)
            || self::isLabOrImagingServiceType($orderServiceType)
            || $coordinationHasLabOrImaging;
    }

    public static function isIncompleteReadingFollowUp(TelemedicineConsultationPatient $consultation, int $serviceId): bool
    {
        if ($serviceId < 1 || (int) $consultation->telemedicine_service_list_id !== $serviceId) {
            return false;
        }

        if (self::normalize((string) $consultation->status) !== self::CONSULTATION_STATUS) {
            return false;
        }

        return ! filled($consultation->current_illness_history)
            && ! filled($consultation->patient_evolution);
    }

    /**
     * @param  list<array<string, mixed>>  $existing
     * @param  list<array<string, mixed>>  $incoming
     * @return list<array<string, mixed>>
     */
    public static function mergeUploadedDocuments(array $existing, array $incoming): array
    {
        $paths = [];

        foreach ($existing as $document) {
            if (! is_array($document)) {
                continue;
            }

            $path = trim((string) ($document['file_path'] ?? ''));
            if ($path !== '') {
                $paths[$path] = true;
            }
        }

        $merged = array_values(array_filter(
            $existing,
            static fn (mixed $document): bool => is_array($document),
        ));

        foreach ($incoming as $document) {
            if (! is_array($document)) {
                continue;
            }

            $path = trim((string) ($document['file_path'] ?? ''));
            if ($path !== '' && isset($paths[$path])) {
                continue;
            }

            if ($path !== '') {
                $paths[$path] = true;
            }

            $merged[] = $document;
        }

        return array_values($merged);
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     * @param  array<int, string>  $typeNamesById
     * @return list<array<string, mixed>>
     */
    public static function resultDocuments(array $documents, array $typeNamesById = []): array
    {
        $out = [];

        foreach ($documents as $document) {
            if (is_array($document) && self::documentHasResultType($document, $typeNamesById)) {
                $out[] = $document;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $newDocuments
     * @return array{
     *     triggered: bool,
     *     created: bool,
     *     reused: bool,
     *     case_reopened: bool,
     *     consultation: ?TelemedicineConsultationPatient,
     *     message: ?string
     * }
     */
    public static function register(
        OperationCoordinationService $coordination,
        array $newDocuments,
        ?string $orderServiceType = null,
    ): array {
        try {
            $typeNamesById = self::documentTypeNamesById($newDocuments);

            if (! self::shouldRegister(
                $newDocuments,
                $orderServiceType,
                self::coordinationHasLabOrImaging($coordination),
                $typeNamesById,
            )) {
                return self::emptyResult();
            }

            $caseId = (int) ($coordination->telemedicine_case_id ?? 0);
            if ($caseId < 1) {
                return self::emptyResult();
            }

            $case = TelemedicineCase::query()->with('telemedicinePatient')->find($caseId);
            $patient = $case?->telemedicinePatient;

            if (! $case instanceof TelemedicineCase || ! $patient instanceof TelemedicinePatient) {
                Log::warning('LabImagingResultsFollowUpRegistrar: coordinación sin caso o paciente', [
                    'operation_coordination_service_id' => $coordination->id,
                    'telemedicine_case_id' => $caseId,
                ]);

                return self::emptyResult();
            }

            $serviceId = self::resolveFollowUpServiceId();
            if ($serviceId === null) {
                Log::error('LabImagingResultsFollowUpRegistrar: no existe el servicio de lectura de resultados', [
                    'expected' => self::FOLLOW_UP_SERVICE_NAME,
                    'telemedicine_case_id' => $case->id,
                ]);

                return self::emptyResult();
            }

            $resultDocuments = self::resultDocuments($newDocuments, $typeNamesById);
            $pending = self::findPendingIncomplete((int) $case->id, $serviceId);
            $created = false;
            $reused = false;
            $caseReopened = false;
            $consultation = null;

            DB::transaction(function () use (
                $case,
                $patient,
                $coordination,
                $serviceId,
                $resultDocuments,
                $pending,
                &$created,
                &$reused,
                &$caseReopened,
                &$consultation,
            ): void {
                $previousStatus = self::normalize((string) $case->status);
                $caseUpdates = [
                    'status' => self::CONSULTATION_STATUS,
                ];

                if ($previousStatus === self::CASE_STATUS_ALTA_MEDICA) {
                    $caseReopened = true;
                    TelemedicineConsultationPatient::query()
                        ->where('telemedicine_case_id', $case->id)
                        ->whereRaw('UPPER(TRIM(status)) = ?', [self::CASE_STATUS_ALTA_MEDICA])
                        ->update(['status' => self::CONSULTATION_STATUS]);
                }

                if ($previousStatus !== self::CONSULTATION_STATUS) {
                    TelemedicineCase::query()->whereKey($case->id)->update($caseUpdates);
                    $case->status = self::CONSULTATION_STATUS;
                }

                if ($pending instanceof TelemedicineConsultationPatient) {
                    $pending->update([
                        'uploaded_documents' => self::mergeUploadedDocuments(
                            is_array($pending->uploaded_documents) ? $pending->uploaded_documents : [],
                            $resultDocuments,
                        ),
                    ]);
                    $consultation = $pending->fresh() ?? $pending;
                    $reused = true;
                    self::writeBitacora(
                        (int) $case->id,
                        'Se adjuntaron nuevos resultados de laboratorio y/o imagenología al seguimiento de lectura de resultados pendiente (ref. '.($consultation->code_reference ?? 's/n').').',
                    );

                    return;
                }

                $last = TelemedicineConsultationPatient::query()
                    ->where('telemedicine_case_id', $case->id)
                    ->orderByDesc('id')
                    ->first();

                $attributes = self::consultationAttributes($case, $patient, $coordination, $serviceId, $resultDocuments, $last);
                $attributes['assigned_by'] = Auth::id() ?? $attributes['assigned_by'] ?? null;

                $consultation = TelemedicineConsultationPatient::query()->create($attributes);
                $created = true;
                self::writeBitacora(
                    (int) $case->id,
                    'Se cargaron resultados de laboratorio y/o imagenología. Se generó el seguimiento «'.self::FOLLOW_UP_SERVICE_NAME.'» (ref. '.($consultation->code_reference ?? 's/n').') para que el médico actualice la lectura.',
                );
            });

            $code = filled($case->code) ? (string) $case->code : '#'.$case->id;

            if ($created) {
                return [
                    'triggered' => true,
                    'created' => true,
                    'reused' => false,
                    'case_reopened' => $caseReopened,
                    'consultation' => $consultation,
                    'message' => 'Se generó un seguimiento de lectura de resultados en el caso '.$code.' para que el médico lo actualice.',
                ];
            }

            return [
                'triggered' => true,
                'created' => false,
                'reused' => $reused,
                'case_reopened' => $caseReopened,
                'consultation' => $consultation,
                'message' => 'Los resultados se adjuntaron al seguimiento de lectura pendiente del caso '.$code.'.',
            ];
        } catch (Throwable $exception) {
            Log::error('LabImagingResultsFollowUpRegistrar: no se pudo generar el seguimiento de lectura de resultados', [
                'operation_coordination_service_id' => $coordination->id,
                'telemedicine_case_id' => $coordination->telemedicine_case_id,
                'message' => $exception->getMessage(),
            ]);

            return self::emptyResult();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $newDocuments
     * @return array{
     *     triggered: bool,
     *     created: bool,
     *     reused: bool,
     *     case_reopened: bool,
     *     consultation: ?TelemedicineConsultationPatient,
     *     message: ?string
     * }
     */
    public static function registerFromServiceOrder(OperationServiceOrder $order, array $newDocuments): array
    {
        $coordination = $order->operationCoordinationService;

        if (! $coordination instanceof OperationCoordinationService) {
            $coordination = OperationCoordinationService::query()->find($order->operation_coordination_service_id);
        }

        if (! $coordination instanceof OperationCoordinationService) {
            return self::emptyResult();
        }

        return self::register($coordination, $newDocuments, $order->service_type);
    }

    public static function analystMessageSuffix(array $result): string
    {
        $message = trim((string) ($result['message'] ?? ''));

        return $message !== '' ? ' '.$message : '';
    }

    public static function startDoctorFollowUp(
        TelemedicineCase $case,
        TelemedicinePatient $patient,
        ?TelemedicineConsultationPatient $contextConsultation = null,
    ): string {
        $exitRecord = TelemedicineHistoryPatient::query()
            ->where('telemedicine_patient_id', $patient->id)
            ->exists();

        $serviceId = self::resolveFollowUpServiceId();
        $pending = $serviceId === null ? null : self::findPendingIncomplete((int) $case->id, $serviceId);

        session()->forget(['case', 'patient', 'exit_record', 'action', 'status', 'consultation']);
        session([
            'case' => $case,
            'patient' => $patient,
            'exit_record' => $exitRecord,
        ]);

        if ($pending instanceof TelemedicineConsultationPatient) {
            session(['consultation' => $pending]);

            return ConsultationCreateRoute::url($patient, $case, $pending);
        }

        return ConsultationCreateRoute::url($patient, $case, $contextConsultation);
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     * @return list<array{
     *     document_name: string,
     *     file_path: string,
     *     document_types: list<string>,
     *     services: list<string>,
     *     source: string,
     *     uploaded_at: ?string,
     *     extension: string,
     *     preview_url: ?string,
     *     is_pdf: bool,
     *     is_image: bool
     * }>
     */
    public static function collectResultPreviewDocuments(array $documents, bool $resolveUrl = false): array
    {
        $byPath = [];

        foreach ($documents as $document) {
            if (! is_array($document) || ! self::documentHasResultType($document)) {
                continue;
            }

            $decorated = self::decoratePreviewDocument($document, $resolveUrl);
            $path = (string) $decorated['file_path'];

            if ($path === '' || isset($byPath[$path])) {
                continue;
            }

            $byPath[$path] = $decorated;
        }

        $rows = array_values($byPath);

        usort(
            $rows,
            static fn (array $a, array $b): int => strcmp((string) ($b['uploaded_at'] ?? ''), (string) ($a['uploaded_at'] ?? ''))
        );

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array{
     *     document_name: string,
     *     file_path: string,
     *     document_types: list<string>,
     *     services: list<string>,
     *     source: string,
     *     uploaded_at: ?string,
     *     extension: string,
     *     preview_url: ?string,
     *     is_pdf: bool,
     *     is_image: bool
     * }
     */
    public static function decoratePreviewDocument(array $document, bool $resolveUrl = false): array
    {
        $path = trim((string) ($document['file_path'] ?? ''));
        $extension = strtoupper((string) pathinfo($path, PATHINFO_EXTENSION));
        $name = trim((string) ($document['document_name'] ?? ''));

        if ($name === '') {
            $name = $path !== '' ? (string) pathinfo($path, PATHINFO_FILENAME) : 'Documento sin nombre';
        }

        $types = collect(is_array($document['document_types'] ?? null) ? $document['document_types'] : [])
            ->map(static fn (mixed $value): string => trim((string) $value))
            ->filter(static fn (string $value): bool => $value !== '')
            ->values()
            ->all();

        $services = collect(is_array($document['services'] ?? null) ? $document['services'] : [])
            ->map(static fn (mixed $value): string => trim((string) $value))
            ->filter(static fn (string $value): bool => $value !== '')
            ->values()
            ->all();

        if ($services === [] && filled($document['service'] ?? null)) {
            $services = [trim((string) $document['service'])];
        }

        return [
            'document_name' => $name,
            'file_path' => $path,
            'document_types' => $types,
            'services' => $services,
            'source' => trim((string) ($document['source'] ?? '')) !== ''
                ? trim((string) $document['source'])
                : 'Operaciones',
            'uploaded_at' => isset($document['uploaded_at']) ? (string) $document['uploaded_at'] : null,
            'extension' => $extension,
            'preview_url' => $resolveUrl ? self::publicPreviewUrl($path) : null,
            'is_pdf' => $extension === 'PDF',
            'is_image' => in_array($extension, ['PNG', 'JPG', 'JPEG', 'WEBP', 'GIF'], true),
        ];
    }

    public static function publicPreviewUrl(string $path): ?string
    {
        $path = trim($path);

        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return asset('storage/'.$path);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function previewDocumentsForCase(int $caseId): array
    {
        if ($caseId < 1) {
            return [];
        }

        $aggregated = [];

        $coordinations = OperationCoordinationService::query()
            ->where('telemedicine_case_id', $caseId)
            ->orderByDesc('id')
            ->get();

        foreach ($coordinations as $coordination) {
            foreach (CoordinationServiceDocumentsAggregator::forCoordination($coordination) as $document) {
                $aggregated[] = $document;
            }
        }

        $consultations = TelemedicineConsultationPatient::query()
            ->where('telemedicine_case_id', $caseId)
            ->get(['uploaded_documents']);

        foreach ($consultations as $consultation) {
            $documents = is_array($consultation->uploaded_documents) ? $consultation->uploaded_documents : [];
            foreach ($documents as $document) {
                if (is_array($document)) {
                    $aggregated[] = $document;
                }
            }
        }

        return self::collectResultPreviewDocuments($aggregated, true);
    }

    public static function isReadingResultsServiceListId(?int $serviceListId): bool
    {
        if ($serviceListId === null || $serviceListId < 1) {
            return false;
        }

        return $serviceListId === self::resolveFollowUpServiceId();
    }

    public static function discardPlaceholderIfReplaced(TelemedicineConsultationPatient $saved): void
    {
        $caseId = (int) ($saved->telemedicine_case_id ?? 0);
        $serviceId = (int) ($saved->telemedicine_service_list_id ?? 0);

        if ($caseId < 1 || ! self::isReadingResultsServiceListId($serviceId)) {
            return;
        }

        $placeholders = TelemedicineConsultationPatient::query()
            ->where('telemedicine_case_id', $caseId)
            ->where('telemedicine_service_list_id', $serviceId)
            ->whereKeyNot($saved->getKey())
            ->whereRaw('UPPER(TRIM(status)) = ?', [self::CONSULTATION_STATUS])
            ->get();

        foreach ($placeholders as $placeholder) {
            if (self::isIncompleteReadingFollowUp($placeholder, $serviceId)) {
                $placeholder->delete();
            }
        }
    }

    public static function resolveFollowUpServiceId(): ?int
    {
        $expected = self::normalize(self::FOLLOW_UP_SERVICE_NAME);

        foreach (TelemedicineServiceList::query()->where('level', 1)->orderBy('id')->get(['id', 'name']) as $service) {
            if (self::normalize((string) $service->name) === $expected) {
                return (int) $service->id;
            }
        }

        foreach (TelemedicineServiceList::query()->orderBy('id')->get(['id', 'name']) as $service) {
            $normalized = self::normalize((string) $service->name);
            if (
                str_contains($normalized, 'SEGUIMIENTO MEDICO')
                && str_contains($normalized, 'LECTURA DE RESULTADOS')
            ) {
                return (int) $service->id;
            }
        }

        return null;
    }

    public static function findPendingIncomplete(int $caseId, int $serviceId): ?TelemedicineConsultationPatient
    {
        if ($caseId < 1 || $serviceId < 1) {
            return null;
        }

        $candidates = TelemedicineConsultationPatient::query()
            ->where('telemedicine_case_id', $caseId)
            ->where('telemedicine_service_list_id', $serviceId)
            ->whereRaw('UPPER(TRIM(status)) = ?', [self::CONSULTATION_STATUS])
            ->orderByDesc('id')
            ->get();

        foreach ($candidates as $candidate) {
            if (self::isIncompleteReadingFollowUp($candidate, $serviceId)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $newDocuments
     * @return array<int, string>
     */
    public static function documentTypeNamesById(array $newDocuments): array
    {
        $names = [];
        $missingIds = [];

        foreach ($newDocuments as $document) {
            if (! is_array($document)) {
                continue;
            }

            $typeNames = is_array($document['document_types'] ?? null) ? $document['document_types'] : [];
            $typeIds = is_array($document['document_type_ids'] ?? null) ? $document['document_type_ids'] : [];

            foreach ($typeIds as $index => $id) {
                $id = (int) $id;
                if ($id < 1) {
                    continue;
                }

                $name = $typeNames[$index] ?? null;
                if (is_string($name) && trim($name) !== '') {
                    $names[$id] = $name;

                    continue;
                }

                $missingIds[$id] = true;
            }
        }

        if ($missingIds === []) {
            return $names;
        }

        $fromCatalog = OperationDocumentList::query()
            ->whereIn('id', array_keys($missingIds))
            ->pluck('name', 'id');

        foreach ($fromCatalog as $id => $name) {
            $names[(int) $id] = (string) $name;
        }

        return $names;
    }

    public static function coordinationHasLabOrImaging(OperationCoordinationService $coordination): bool
    {
        if ($coordination->relationLoaded('telemedicinePatientLabs')) {
            if ($coordination->telemedicinePatientLabs->isNotEmpty()) {
                return true;
            }
        } elseif ($coordination->telemedicinePatientLabs()->exists()) {
            return true;
        }

        if ($coordination->relationLoaded('telemedicinePatientStudies')) {
            return $coordination->telemedicinePatientStudies->isNotEmpty();
        }

        return $coordination->telemedicinePatientStudies()->exists();
    }

    /**
     * @param  list<array<string, mixed>>  $resultDocuments
     * @return array<string, mixed>
     */
    public static function consultationAttributes(
        TelemedicineCase $case,
        TelemedicinePatient $patient,
        OperationCoordinationService $coordination,
        int $serviceId,
        array $resultDocuments,
        ?TelemedicineConsultationPatient $last = null,
    ): array {
        $doctorId = $case->telemedicine_doctor_id ?? $coordination->telemedicine_doctor_id;
        $assignedBy = $last?->assigned_by;

        $attributes = [
            'telemedicine_case_id' => $case->id,
            'telemedicine_case_code' => $case->code,
            'telemedicine_patient_id' => $patient->id,
            'telemedicine_doctor_id' => $doctorId,
            'telemedicine_service_list_id' => $serviceId,
            'telemedicine_priority_id' => $last?->telemedicine_priority_id ?? $case->telemedicine_priority_id,
            'assigned_by' => $assignedBy,
            'status' => self::CONSULTATION_STATUS,
            'code_reference' => 'REF-'.random_int(10000, 99999),
            'full_name' => $patient->full_name ?? $case->patient_name,
            'nro_identificacion' => $patient->nro_identificacion,
            'reason_consultation' => self::REASON_CONSULTATION,
            'uploaded_documents' => $resultDocuments,
        ];

        if ($last instanceof TelemedicineConsultationPatient) {
            foreach (['actual_phatology', 'background', 'diagnostic_impression', 'pa', 'fc', 'fr', 'temp', 'saturacion', 'peso', 'estatura', 'imc'] as $field) {
                $value = $last->getAttribute($field);
                if ($value !== null && $value !== '') {
                    $attributes[$field] = $value;
                }
            }
        }

        return $attributes;
    }

    private static function writeBitacora(int $caseId, string $description): void
    {
        $userId = Auth::id();

        ObservationCase::query()->create([
            'telemedicine_case_id' => $caseId,
            'description' => $description,
            'created_by' => $userId !== null ? (string) $userId : null,
        ]);
    }
}
