<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\ObservationCase;
use App\Models\OperationCoordinationService;
use App\Models\OperationMedicalAppointment;
use App\Models\OperationServiceOrder;
use App\Models\TelemedicineAmdPhysicalExam;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineFollowUp;
use App\Models\TelemedicineOperationsLog;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicinePatientStudy;
use App\Support\Filament\Operations\OperationsSupplierScope;
use App\Support\Telemedicine\TelemedicineAmdBitacoraCatalog;
use App\Support\Telemedicine\TelemedicineAmdPhysicalExamTemplate;
use App\Support\Telemedicine\TelemedicineCaseDocumentsCatalog;
use App\Support\Telemedicine\TelemedicineCaseFilamentListQuery;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class TelemedicineCaseBitacora
{
    public const SCOPE_OPERATIONS = 'operations';

    public const SCOPE_TELEMEDICINA = 'telemedicina';

    /**
     * @var array<int, array<string, mixed>>
     */
    private static array $requestDossiers = [];

    /**
     * @return Builder<TelemedicineCase>
     */
    public static function baseQuery(string $scope = self::SCOPE_OPERATIONS): Builder
    {
        $query = TelemedicineCase::query();

        if ($scope === self::SCOPE_TELEMEDICINA) {
            return TelemedicineCaseFilamentListQuery::applyTelemedicinaBitacoraConstraints($query);
        }

        return OperationsSupplierScope::applyToQuery($query);
    }

    public static function findAccessible(int $caseId, string $scope = self::SCOPE_OPERATIONS): ?TelemedicineCase
    {
        return self::baseQuery($scope)->whereKey($caseId)->first();
    }

    /**
     * @return Builder<TelemedicineCase>
     */
    public static function searchQuery(string $term, string $scope = self::SCOPE_OPERATIONS): Builder
    {
        $term = trim($term);
        $query = self::baseQuery($scope)->with([
            'telemedicinePatient:id,full_name,nro_identificacion,phone,email,email_contact,phone_contact',
            'telemedicineDoctor:id,full_name',
        ]);

        if ($term === '') {
            return $query->whereRaw('0 = 1');
        }

        $like = '%'.$term.'%';

        return $query
            ->where(function (Builder $inner) use ($like): void {
                $inner->where('code', 'like', $like)
                    ->orWhere('patient_name', 'like', $like)
                    ->orWhereHas('telemedicinePatient', function (Builder $patient) use ($like): void {
                        $patient->where('full_name', 'like', $like)
                            ->orWhere('nro_identificacion', 'like', $like);
                    });
            })
            ->orderByDesc('created_at')
            ->limit(20);
    }

    /**
     * @return list<array{id: int, label: string, status: string, patient: string, document: string}>
     */
    public static function searchResults(string $term, string $scope = self::SCOPE_OPERATIONS): array
    {
        return self::searchQuery($term, $scope)
            ->get()
            ->map(fn (TelemedicineCase $case): array => [
                'id' => (int) $case->id,
                'label' => self::resultLabel($case),
                'status' => self::text($case->status),
                'patient' => self::patientName($case),
                'document' => self::patientDocument($case),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function dossier(TelemedicineCase $case): array
    {
        $caseId = (int) $case->id;

        if (isset(self::$requestDossiers[$caseId])) {
            return self::$requestDossiers[$caseId];
        }

        $case->loadMissing([
            'telemedicinePatient.plan',
            'telemedicinePatient.businessLine',
            'telemedicineDoctor',
            'priority',
            'country',
            'state',
            'city',
            'consultations.telemedicineDoctor',
            'observations.createdBy',
            'operationLogs',
            'caseMessages.user',
            'amdInforms.telemedicineDoctor',
            'amdInforms.createdBy',
            'amdInforms.supplier',
            'telemedicineDocuments',
        ]);

        $patient = $case->telemedicinePatient;
        $isDischarge = mb_strtoupper(trim((string) $case->status)) === 'ALTA MEDICA';

        return self::$requestDossiers[$caseId] = [
            'case_id' => $caseId,
            'code' => self::text($case->code, 'Caso #'.$case->id),
            'status' => self::text($case->status),
            'is_discharge' => $isDischarge,
            'header' => [
                'Código' => self::text($case->code, 'Caso #'.$case->id),
                'Estatus' => self::text($case->status),
                'Apertura' => self::dateTime($case->created_at),
                'Última actualización' => self::dateTime($case->updated_at),
                'Motivo' => self::text($case->reason),
                'Prioridad' => self::text($case->priority?->name),
                'Médico' => self::text($case->telemedicineDoctor?->full_name),
                'Asignado por' => self::text($case->assigned_by),
                'Gestionado por' => self::text($case->managed_by),
                'Pertenece a' => self::text($case->belongs_to),
            ],
            'patient' => [
                'Nombre' => self::patientName($case),
                'Cédula' => self::patientDocument($case),
                'Edad' => self::text($case->patient_age ?? $patient?->age),
                'Sexo' => self::text($case->patient_sex ?? $patient?->sex),
                'Teléfono' => self::text($case->patient_phone ?: $patient?->phone),
                'Teléfono 2' => self::text($case->patient_phone_2 ?: $patient?->phone_contact),
                'Correo' => self::text($patient?->email ?: $patient?->email_contact),
                'Dirección' => self::text($case->patient_address ?: $patient?->address),
                'Ubicación' => self::location($case),
                'Plan' => self::text($patient?->plan?->description),
                'Línea' => self::text($patient?->businessLine?->definition),
            ],
            'contacts' => [
                'phone' => trim((string) ($case->patient_phone ?: $patient?->phone ?: $patient?->phone_contact ?: '')),
                'email' => trim((string) ($patient?->email ?: $patient?->email_contact ?: '')),
                'patient_name' => self::patientName($case),
            ],
            'clinical_summary' => self::clinicalSummary($case->consultations, (int) $case->id),
            'consultations' => self::consultationRows($case->consultations),
            'follow_ups' => self::followUpRows($case->id),
            'amd_reports' => TelemedicineAmdBitacoraCatalog::entries($case),
            'amd_physical_exams' => self::amdPhysicalExamRows((int) $case->id),
            'observations' => self::observationRows($case->observations),
            'operation_logs' => self::operationLogRows($case->operationLogs),
            'labs' => self::labRows($case->id),
            'medications' => self::medicationRows($case->id),
            'studies' => self::studyRows($case->id),
            'specialties' => self::specialtyRows($case->id),
            'coordinations' => self::coordinationRows($case->id),
            'service_orders' => self::serviceOrderRows($case->id),
            'appointments' => self::appointmentRows($case->id),
            'documents' => TelemedicineCaseDocumentsCatalog::entries($case),
            'messages' => self::messageRows($case),
            'discharge' => $isDischarge ? self::dischargeSummary($case) : null,
        ];
    }

    public static function documentName(TelemedicineCase $case): string
    {
        $code = preg_replace('/[^A-Za-z0-9\-]+/', '-', (string) ($case->code ?: $case->id)) ?: (string) $case->id;

        return 'BITACORA-'.$code.'.pdf';
    }

    /**
     * Parte textos largos para que DomPDF pagine entre filas y no deje
     * cabeceras huérfanas ni páginas en blanco.
     *
     * @return list<string>
     */
    public static function pdfTextChunks(string $text, int $maxChars = 800): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        if (mb_strlen($text) <= $maxChars) {
            return [$text];
        }

        $chunks = [];
        $remaining = $text;

        while (mb_strlen($remaining) > $maxChars) {
            $window = mb_substr($remaining, 0, $maxChars);
            $break = mb_strrpos($window, "\n");

            if ($break === false || $break < (int) ($maxChars * 0.45)) {
                $space = mb_strrpos($window, ' ');
                if ($space !== false && $space >= (int) ($maxChars * 0.35)) {
                    $break = $space;
                }
            }

            if ($break === false || $break < 1) {
                $break = $maxChars;
            }

            $chunks[] = trim(mb_substr($remaining, 0, $break));
            $remaining = ltrim(mb_substr($remaining, $break));
        }

        if ($remaining !== '') {
            $chunks[] = $remaining;
        }

        return $chunks;
    }

    /**
     * @param  array<string, mixed>  $map
     * @return list<array{type: 'pair'|'stack', cells: list<array{label: string, value: string, chunks: list<string>}>}>
     */
    public static function pdfFieldRows(array $map): array
    {
        $pairs = [];

        foreach ($map as $label => $value) {
            if (is_bool($value) || is_array($value)) {
                continue;
            }

            $text = trim((string) $value);

            if ($text === '' || $text === '—') {
                continue;
            }

            $pairs[] = [
                'label' => (string) $label,
                'value' => $text,
                'chunks' => self::pdfTextChunks($text),
            ];
        }

        $rows = [];
        $buffer = [];

        foreach ($pairs as $pair) {
            $isLong = mb_strlen($pair['value']) > 90 || count($pair['chunks']) > 1;

            if ($isLong) {
                if ($buffer !== []) {
                    $rows[] = ['type' => 'pair', 'cells' => $buffer];
                    $buffer = [];
                }

                $rows[] = ['type' => 'stack', 'cells' => [$pair]];

                continue;
            }

            $buffer[] = $pair;

            if (count($buffer) === 2) {
                $rows[] = ['type' => 'pair', 'cells' => $buffer];
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            $rows[] = ['type' => 'pair', 'cells' => $buffer];
        }

        return $rows;
    }

    private static function resultLabel(TelemedicineCase $case): string
    {
        $code = self::text($case->code, 'Caso #'.$case->id);
        $patient = self::patientName($case);
        $document = self::patientDocument($case);

        return $code.' · '.$patient.' · CI '.$document;
    }

    private static function patientName(TelemedicineCase $case): string
    {
        return self::text($case->patient_name ?: $case->telemedicinePatient?->full_name, 'Paciente');
    }

    private static function patientDocument(TelemedicineCase $case): string
    {
        return self::text($case->telemedicinePatient?->nro_identificacion);
    }

    private static function location(TelemedicineCase $case): string
    {
        $line = collect([
            $case->city?->definition,
            $case->state?->definition,
            $case->country?->name,
        ])->filter()->unique()->implode(' · ');

        return self::text($line);
    }

    /**
     * @param  Collection<int, TelemedicineConsultationPatient>|mixed  $consultations
     * @return list<array<string, string>>
     */
    private static function consultationRows(mixed $consultations): array
    {
        return collect($consultations)
            ->sortBy('created_at')
            ->values()
            ->map(fn (TelemedicineConsultationPatient $consultation): array => [
                'Referencia' => self::text($consultation->code_reference, 'CONS-'.$consultation->id),
                'Fecha' => self::dateTime($consultation->created_at),
                'Estatus' => self::text($consultation->status),
                'Médico' => self::text($consultation->telemedicineDoctor?->full_name),
                'Motivo' => self::text($consultation->reason_consultation),
                'Patología actual' => self::text($consultation->actual_phatology),
                'Antecedentes' => self::text($consultation->background),
                'Impresión diagnóstica' => self::text($consultation->diagnostic_impression),
                'Historia de enfermedad actual' => self::text($consultation->current_illness_history),
                'Evolución' => self::text($consultation->patient_evolution),
                'Observaciones médicas' => self::text($consultation->observations),
                'PA' => self::text($consultation->pa),
                'FC' => self::text($consultation->fc),
                'FR' => self::text($consultation->fr),
                'Temp' => self::text($consultation->temp),
                'Saturación' => self::text($consultation->saturacion),
                'Peso' => self::text($consultation->peso),
                'Estatura' => self::text($consultation->estatura),
                'IMC' => self::text($consultation->imc),
            ])
            ->all();
    }

    /**
     * Largo máximo de cada texto del resumen clínico (~2-3 líneas). La ficha
     * completa sigue en «Consultas y notas médicas».
     */
    public const SUMMARY_TEXT_LIMIT = 280;

    /**
     * Historia del caso en orden: consulta inicial, seguimientos y alta.
     *
     * Los seguimientos y el alta se registran como consultas del mismo caso con
     * estatus EN SEGUIMIENTO / ALTA MEDICA; `telemedicine_follow_ups` se suma
     * por si tiene registros. Laboratorios, medicamentos, estudios y
     * especialistas quedan fuera: tienen sus propias secciones.
     *
     * @param  Collection<int, TelemedicineConsultationPatient>|mixed  $consultations
     * @return list<array{stage: string, tone: string, date: string, doctor: string, reference: string, fields: array<string, string>, truncated: bool}>
     */
    public static function clinicalSummary(mixed $consultations, int $caseId): array
    {
        $events = collect($consultations)
            ->map(fn (TelemedicineConsultationPatient $consultation): array => [
                'at' => $consultation->created_at?->getTimestamp() ?? 0,
                'id' => (int) $consultation->id,
                'status' => mb_strtoupper(trim((string) $consultation->status)),
                'reference' => self::text($consultation->code_reference, 'CONS-'.$consultation->id),
                'date' => self::dateTime($consultation->created_at),
                'doctor' => self::text($consultation->telemedicineDoctor?->full_name),
                /*
                 * La consulta inicial llena motivo e impresión; los seguimientos
                 * y el alta responden el cuestionario (cuestion_1…5). Los vacíos
                 * se omiten, así que cada etapa muestra sólo lo que registró.
                 */
                'fields' => [
                    'Motivo' => $consultation->reason_consultation,
                    'Impresión diagnóstica' => $consultation->diagnostic_impression,
                    'Evolución' => $consultation->patient_evolution,
                    'Cómo se siente' => $consultation->cuestion_1,
                    'Respuesta al tratamiento' => $consultation->cuestion_2,
                    'Mejoría de síntomas' => $consultation->cuestion_3,
                    'Estudios realizados' => $consultation->cuestion_4,
                    'Ajuste de indicaciones' => $consultation->cuestion_5,
                    'Observaciones' => $consultation->observations,
                    'Signos vitales' => self::vitalSigns($consultation),
                ],
            ]);

        $followUps = TelemedicineFollowUp::query()
            ->with('telemedicineDoctor:id,full_name')
            ->where('telemedicine_case_id', $caseId)
            ->get()
            ->map(fn (TelemedicineFollowUp $followUp): array => [
                'at' => $followUp->created_at?->getTimestamp() ?? 0,
                'id' => (int) $followUp->id,
                'status' => 'EN SEGUIMIENTO',
                'reference' => self::text($followUp->code, 'SEG-'.$followUp->id),
                'date' => self::dateTime($followUp->created_at),
                'doctor' => self::text($followUp->telemedicineDoctor?->full_name),
                'fields' => [
                    'Motivo' => $followUp->reason_consultation,
                    'Impresión diagnóstica' => $followUp->diagnostic_impression,
                    'Patología actual' => $followUp->actual_phatology,
                    'Cómo se siente' => $followUp->cuestion_1,
                    'Respuesta al tratamiento' => $followUp->cuestion_2,
                    'Mejoría de síntomas' => $followUp->cuestion_3,
                    'Estudios realizados' => $followUp->cuestion_4,
                    'Ajuste de indicaciones' => $followUp->cuestion_5,
                    'Próximo seguimiento' => $followUp->next_follow_up,
                ],
            ]);

        $followUpNumber = 0;
        $seenInitial = false;

        return $events
            ->concat($followUps)
            ->sortBy([['at', 'asc'], ['id', 'asc']])
            ->values()
            ->map(function (array $event) use (&$followUpNumber, &$seenInitial): array {
                [$stage, $tone] = match (true) {
                    $event['status'] === 'ALTA MEDICA' => ['Alta médica', 'discharge'],
                    $event['status'] === 'EN SEGUIMIENTO', $seenInitial => ['Seguimiento '.(++$followUpNumber), 'follow_up'],
                    default => ['Consulta inicial', 'initial'],
                };

                $seenInitial = true;
                $fields = [];
                $truncated = false;

                foreach ($event['fields'] as $label => $value) {
                    $text = trim(preg_replace('/\s+/u', ' ', (string) ($value ?? '')) ?? '');

                    if ($text === '' || $text === '—') {
                        continue;
                    }

                    if (mb_strlen($text) > self::SUMMARY_TEXT_LIMIT) {
                        $truncated = true;
                        $text = Str::limit($text, self::SUMMARY_TEXT_LIMIT, '…');
                    }

                    $fields[$label] = $text;
                }

                return [
                    'stage' => $stage,
                    'tone' => $tone,
                    'date' => $event['date'],
                    'doctor' => $event['doctor'],
                    'reference' => $event['reference'],
                    'fields' => $fields,
                    'truncated' => $truncated,
                ];
            })
            ->all();
    }

    /**
     * Exámenes físicos AMD del caso, del más antiguo al más reciente.
     *
     * @return list<array{date: string, doctor: string, reference: string, vitals: list<array{label: string, value: string}>, systems: list<array{label: string, text: string, is_default: bool}>}>
     */
    public static function amdPhysicalExamRows(int $caseId): array
    {
        return TelemedicineAmdPhysicalExam::query()
            ->with(['telemedicineDoctor:id,full_name', 'telemedicineConsultationPatient:id,code_reference'])
            ->where('telemedicine_case_id', $caseId)
            ->whereNotNull('telemedicine_consultation_patient_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (TelemedicineAmdPhysicalExam $exam): array => [
                'date' => self::dateTime($exam->created_at),
                'doctor' => self::text($exam->telemedicineDoctor?->full_name),
                'reference' => self::text($exam->telemedicineConsultationPatient?->code_reference, 'CONS-'.$exam->telemedicine_consultation_patient_id),
                ...TelemedicineAmdPhysicalExamTemplate::display($exam),
            ])
            ->all();
    }

    private static function vitalSigns(TelemedicineConsultationPatient $consultation): string
    {
        return collect([
            'PA' => $consultation->pa,
            'FC' => $consultation->fc,
            'FR' => $consultation->fr,
            'Temp' => $consultation->temp,
            'Sat' => $consultation->saturacion,
        ])
            ->map(fn (mixed $value): string => trim((string) ($value ?? '')))
            ->filter(fn (string $value): bool => $value !== '' && $value !== '—')
            ->map(fn (string $value, string $label): string => $label.' '.$value)
            ->implode(' · ');
    }

    /**
     * @return list<array<string, string>>
     */
    private static function followUpRows(int $caseId): array
    {
        return TelemedicineFollowUp::query()
            ->with('telemedicineDoctor:id,full_name')
            ->where('telemedicine_case_id', $caseId)
            ->orderBy('created_at')
            ->get()
            ->map(fn (TelemedicineFollowUp $followUp): array => [
                'Código' => self::text($followUp->code, 'SEG-'.$followUp->id),
                'Fecha' => self::dateTime($followUp->created_at),
                'Estatus' => self::text($followUp->status),
                'Médico' => self::text($followUp->telemedicineDoctor?->full_name),
                'Motivo' => self::text($followUp->reason_consultation),
                'Patología actual' => self::text($followUp->actual_phatology),
                'Impresión diagnóstica' => self::text($followUp->diagnostic_impression),
                'Próximo seguimiento' => self::text($followUp->next_follow_up),
            ])
            ->all();
    }

    /**
     * @param  Collection<int, ObservationCase>|mixed  $observations
     * @return list<array<string, string>>
     */
    private static function observationRows(mixed $observations): array
    {
        return collect($observations)
            ->sortBy('created_at')
            ->values()
            ->map(fn (ObservationCase $observation): array => [
                'Fecha' => self::dateTime($observation->created_at),
                'Registró' => self::text($observation->createdBy?->name),
                'Observación' => self::text($observation->description),
            ])
            ->all();
    }

    /**
     * @param  Collection<int, TelemedicineOperationsLog>|mixed  $logs
     * @return list<array<string, string>>
     */
    private static function operationLogRows(mixed $logs): array
    {
        return collect($logs)
            ->sortBy('created_at')
            ->values()
            ->map(fn (TelemedicineOperationsLog $log): array => [
                'Fecha' => self::dateTime($log->created_at),
                'Operación' => self::text($log->operation),
                'Estatus' => self::text($log->status),
                'Responsable' => self::text($log->responsable),
                'Descripción' => self::text($log->description),
                'Observaciones' => self::text($log->observations),
            ])
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private static function labRows(int $caseId): array
    {
        return TelemedicinePatientLab::query()
            ->where('telemedicine_case_id', $caseId)
            ->orderBy('created_at')
            ->get()
            ->map(fn (TelemedicinePatientLab $lab): array => [
                'Fecha' => self::dateTime($lab->created_at),
                'Laboratorio' => self::text($lab->laboratory),
                'Tipo' => self::text($lab->type),
                'Estatus' => self::text($lab->status),
            ])
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private static function medicationRows(int $caseId): array
    {
        return TelemedicinePatientMedications::query()
            ->where('telemedicine_case_id', $caseId)
            ->orderBy('created_at')
            ->get()
            ->map(fn (TelemedicinePatientMedications $medication): array => [
                'Fecha' => self::dateTime($medication->created_at),
                'Medicamento' => self::text($medication->medicine),
                'Indicaciones' => self::text($medication->indications),
                'Cantidad' => self::text($medication->quantity),
                'Duración' => self::text($medication->duration),
                'Estatus' => self::text($medication->status),
            ])
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private static function studyRows(int $caseId): array
    {
        return TelemedicinePatientStudy::query()
            ->where('telemedicine_case_id', $caseId)
            ->orderBy('created_at')
            ->get()
            ->map(fn (TelemedicinePatientStudy $study): array => [
                'Fecha' => self::dateTime($study->created_at),
                'Estudio' => self::text($study->study),
                'Tipo' => self::text($study->type),
                'Estatus' => self::text($study->status),
            ])
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private static function specialtyRows(int $caseId): array
    {
        return TelemedicinePatientSpecialty::query()
            ->where('telemedicine_case_id', $caseId)
            ->orderBy('created_at')
            ->get()
            ->map(fn (TelemedicinePatientSpecialty $specialty): array => [
                'Fecha' => self::dateTime($specialty->created_at),
                'Especialidad' => self::text($specialty->specialty),
                'Tipo' => self::text($specialty->type),
                'Estatus' => self::text($specialty->status),
            ])
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private static function coordinationRows(int $caseId): array
    {
        return OperationCoordinationService::query()
            ->where('telemedicine_case_id', $caseId)
            ->orderBy('created_at')
            ->get()
            ->map(fn (OperationCoordinationService $coordination): array => [
                'Fecha solicitud' => self::text($coordination->date_solicitud) !== '—'
                    ? self::text($coordination->date_solicitud)
                    : self::dateTime($coordination->created_at),
                'Referencia' => self::text($coordination->reference_number, '#'.$coordination->id),
                'Servicio' => self::text($coordination->specific_service ?: $coordination->servicie),
                'Tipo' => self::text($coordination->type_service),
                'Estatus' => self::text($coordination->status),
                'Proveedor' => self::text($coordination->supplier_service),
                'Diagnóstico / síntomas' => self::text($coordination->symptoms_diagnosis),
                'Observaciones' => self::text($coordination->observations),
            ])
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private static function serviceOrderRows(int $caseId): array
    {
        $coordinationIds = OperationCoordinationService::query()
            ->where('telemedicine_case_id', $caseId)
            ->pluck('id')
            ->all();

        if ($coordinationIds === []) {
            return [];
        }

        return OperationServiceOrder::query()
            ->whereIn('operation_coordination_service_id', $coordinationIds)
            ->orderBy('created_at')
            ->get()
            ->map(fn (OperationServiceOrder $order): array => [
                'Orden' => self::text($order->order_number, '#'.$order->id),
                'Fecha' => self::dateTime($order->created_at),
                'Tipo' => self::text($order->service_type),
                'Estatus' => self::text($order->status),
                'Estatus administrativo' => self::text($order->administrative_status),
                'Monto USD' => self::text($order->total_amount_usd),
            ])
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private static function appointmentRows(int $caseId): array
    {
        return OperationMedicalAppointment::query()
            ->where('telemedicine_case_id', $caseId)
            ->orderBy('appointment_at')
            ->get()
            ->map(fn (OperationMedicalAppointment $appointment): array => [
                'Cita' => self::dateTime($appointment->appointment_at),
                'Estatus' => self::text($appointment->status),
                'Proveedor' => self::text($appointment->supplier_external),
                'Motivo de cambio' => self::text($appointment->last_change_reason),
            ])
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private static function messageRows(TelemedicineCase $case): array
    {
        return CaseMessagingAuditLog::messagesForCase($case)
            ->map(fn ($message): array => [
                'Fecha' => self::dateTime($message->created_at),
                'Autor' => self::text($message->user?->name, $message->user_id ? 'Usuario #'.$message->user_id : '—'),
                'Mensaje' => self::text($message->body),
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function dischargeSummary(TelemedicineCase $case): array
    {
        $lastConsultation = $case->consultations
            ->sortByDesc(fn (TelemedicineConsultationPatient $consultation): int => $consultation->created_at?->timestamp ?? 0)
            ->first();

        $lastObservation = $case->observations
            ->sortByDesc(fn (ObservationCase $observation): int => $observation->created_at?->timestamp ?? 0)
            ->first();

        return [
            'Estatus' => 'ALTA MEDICA',
            'Fecha de alta / última actualización' => self::dateTime($case->updated_at),
            'Diagnóstico de egreso' => self::text($lastConsultation?->diagnostic_impression),
            'Evolución final' => self::text($lastConsultation?->patient_evolution),
            'Última observación' => self::text($lastObservation?->description),
        ];
    }

    private static function text(mixed $value, string $empty = '—'): string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? $empty : $text;
    }

    private static function dateTime(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('d/m/Y H:i');
        }

        $text = trim((string) ($value ?? ''));

        if ($text === '') {
            return '—';
        }

        try {
            return Carbon::parse($text)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return $text;
        }
    }
}
