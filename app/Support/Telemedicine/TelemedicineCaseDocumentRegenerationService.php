<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Jobs\GeneratePdfEspecialista;
use App\Jobs\GeneratePdfImagenologia;
use App\Jobs\GeneratePdfInformeMedicoCorto;
use App\Jobs\GeneratePdfInformeMedicoLargo;
use App\Jobs\GeneratePdfLaboratorio;
use App\Jobs\GeneratePdfMedicamentos;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicinePatient;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicinePatientStudy;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class TelemedicineCaseDocumentRegenerationService
{
    public const DOCUMENT_INFORME_CORTO = 'informe-corto';

    public const DOCUMENT_INFORME_LARGO = 'informe-largo';

    public const DOCUMENT_MEDICAMENTOS = 'medicamentos';

    public const DOCUMENT_LABORATORIOS = 'laboratorios';

    public const DOCUMENT_IMAGENOLOGIA = 'imagenologia';

    public const DOCUMENT_ESPECIALISTA = 'especialista';

    /**
     * @return array<string, string>
     */
    public function availableOptions(TelemedicineCase $case): array
    {
        $consultation = $this->resolveConsultation($case);

        if ($consultation === null) {
            return [];
        }

        $options = [];

        if ($this->canGenerateInforme($consultation)) {
            $options[self::DOCUMENT_INFORME_CORTO] = 'Informe médico (consulta inicial)';

            if (! $this->isAmdConsultation($consultation)) {
                $options[self::DOCUMENT_INFORME_LARGO] = 'Informe médico largo (consulta inicial)';
            }
        }

        if ($this->medicationsForCase($case)->isNotEmpty()) {
            $options[self::DOCUMENT_MEDICAMENTOS] = 'Recipe de medicamentos';
        }

        if ($this->labsForCase($case) !== []) {
            $options[self::DOCUMENT_LABORATORIOS] = 'Orden de laboratorios';
        }

        if ($this->studiesForCase($case) !== []) {
            $options[self::DOCUMENT_IMAGENOLOGIA] = 'Orden de estudios / imagenología';
        }

        if ($this->specialistsForCase($case) !== []) {
            $options[self::DOCUMENT_ESPECIALISTA] = 'Referencia a especialistas';
        }

        return $options;
    }

    /**
     * @param  list<string>  $documentKeys
     * @return list<string>
     */
    /**
     * Regenera los documentos seleccionados **dentro del request**, sin pasar por
     * la cola.
     *
     * Esta acción es el plan B del médico justo cuando la cola de documentos ha
     * fallado: encolar aquí reproduciría el fallo que se quiere sortear. Se
     * ejecuta cada job de forma aislada para que un documento roto no impida los
     * demás, y se devuelve el detalle de lo que salió y lo que no.
     *
     * @param  list<string>  $documentKeys
     */
    public function regenerate(TelemedicineCase $case, array $documentKeys, User $user): TelemedicineCaseDocumentRegenerationResult
    {
        $documentKeys = array_values(array_unique(array_filter($documentKeys, static fn (mixed $key): bool => is_string($key) && $key !== '')));

        if ($documentKeys === []) {
            throw new InvalidArgumentException('Debe seleccionar al menos un documento.');
        }

        $available = $this->availableOptions($case);
        $selected = array_values(array_filter(
            $documentKeys,
            static fn (string $key): bool => array_key_exists($key, $available),
        ));

        if ($selected === []) {
            throw new InvalidArgumentException('Ninguno de los documentos seleccionados está disponible para este caso.');
        }

        $consultation = $this->resolveConsultation($case);

        if ($consultation === null) {
            throw new InvalidArgumentException('El caso no tiene consultas para regenerar documentos.');
        }

        $doctor = $this->resolveDoctor($consultation, $case);
        $patient = $this->resolvePatient($consultation, $case);

        if ($doctor === null || $patient === null) {
            throw new InvalidArgumentException('No se encontró el médico o el paciente del caso.');
        }

        $jobs = [];

        foreach ($selected as $documentKey) {
            $job = match ($documentKey) {
                self::DOCUMENT_INFORME_CORTO => new GeneratePdfInformeMedicoCorto(
                    $this->buildInformePayload($consultation, $doctor, $patient, $case, includeVitals: false),
                    $user,
                    self::DOCUMENT_INFORME_CORTO,
                ),
                self::DOCUMENT_INFORME_LARGO => new GeneratePdfInformeMedicoLargo(
                    $this->buildInformePayload($consultation, $doctor, $patient, $case, includeVitals: true),
                    $user,
                    self::DOCUMENT_INFORME_LARGO,
                ),
                self::DOCUMENT_MEDICAMENTOS => new GeneratePdfMedicamentos(
                    $this->buildMedicamentosPayload($consultation, $doctor, $patient, $case),
                    $user,
                    self::DOCUMENT_MEDICAMENTOS,
                ),
                self::DOCUMENT_LABORATORIOS => new GeneratePdfLaboratorio(
                    $this->buildLaboratoriosPayload($consultation, $doctor, $patient, $case),
                    $user,
                    self::DOCUMENT_LABORATORIOS,
                ),
                self::DOCUMENT_IMAGENOLOGIA => new GeneratePdfImagenologia(
                    $this->buildImagenologiaPayload($consultation, $doctor, $patient, $case),
                    $user,
                    self::DOCUMENT_IMAGENOLOGIA,
                ),
                self::DOCUMENT_ESPECIALISTA => new GeneratePdfEspecialista(
                    $this->buildEspecialistaPayload($consultation, $doctor, $patient, $case),
                    $user,
                    self::DOCUMENT_ESPECIALISTA,
                ),
                default => null,
            };

            if ($job !== null) {
                $jobs[$documentKey] = $job;
            }
        }

        if ($jobs === []) {
            throw new InvalidArgumentException('No se pudieron preparar los documentos seleccionados.');
        }

        return $this->runJobsSynchronously($jobs, $case, $available);
    }

    /**
     * Ejecuta los jobs en el propio request, uno a uno y sin cola.
     *
     * @param  array<string, object>  $jobs  Clave de documento => job.
     * @param  array<string, string>  $labels
     */
    protected function runJobsSynchronously(array $jobs, TelemedicineCase $case, array $labels): TelemedicineCaseDocumentRegenerationResult
    {
        // Hasta seis PDF en un mismo request: el límite por defecto se queda corto.
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $generated = [];
        $failed = [];

        foreach ($jobs as $documentKey => $job) {
            try {
                $this->runJob($job);
                $generated[] = $documentKey;
            } catch (Throwable $exception) {
                $failed[$documentKey] = $exception->getMessage();

                Log::error('TelemedicineCaseDocumentRegenerationService: documento no generado', [
                    'telemedicine_case_id' => $case->id,
                    'document' => $documentKey,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return new TelemedicineCaseDocumentRegenerationResult($generated, $failed, $labels);
    }

    /**
     * Aislado para que las pruebas puedan sustituir la ejecución del job.
     */
    protected function runJob(object $job): void
    {
        dispatch_sync($job);
    }

    protected function resolveDoctor(TelemedicineConsultationPatient $consultation, TelemedicineCase $case): ?TelemedicineDoctor
    {
        return TelemedicineDoctor::query()->find($consultation->telemedicine_doctor_id ?? $case->telemedicine_doctor_id);
    }

    protected function resolvePatient(TelemedicineConsultationPatient $consultation, TelemedicineCase $case): ?TelemedicinePatient
    {
        return TelemedicinePatient::query()->find($consultation->telemedicine_patient_id ?? $case->telemedicine_patient_id);
    }

    public function resolveConsultation(TelemedicineCase $case): ?TelemedicineConsultationPatient
    {
        $initial = TelemedicineConsultationPatient::query()
            ->where('telemedicine_case_id', $case->id)
            ->where('status', 'CONSULTA INICIAL')
            ->orderBy('id')
            ->first();

        if ($initial !== null) {
            return $initial;
        }

        return TelemedicineConsultationPatient::query()
            ->where('telemedicine_case_id', $case->id)
            ->orderBy('id')
            ->first();
    }

    protected function canGenerateInforme(TelemedicineConsultationPatient $consultation): bool
    {
        return filled($consultation->reason_consultation)
            || filled($consultation->actual_phatology)
            || filled($consultation->diagnostic_impression)
            || filled($consultation->code_reference);
    }

    protected function isAmdConsultation(TelemedicineConsultationPatient $consultation): bool
    {
        return (int) ($consultation->telemedicine_service_list_id ?? 0) === TelemedicineCaseTdgReassignmentCoordination::AMD_SERVICE_LIST_ID;
    }

    /**
     * @return Collection<int, TelemedicinePatientMedications>
     */
    protected function medicationsForCase(TelemedicineCase $case): Collection
    {
        return TelemedicinePatientMedications::query()
            ->with('operationInventory')
            ->where('telemedicine_case_id', $case->id)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<string>
     */
    protected function labsForCase(TelemedicineCase $case): array
    {
        $fromRelation = TelemedicinePatientLab::query()
            ->where('telemedicine_case_id', $case->id)
            ->orderBy('id')
            ->pluck('laboratory')
            ->filter(static fn (mixed $value): bool => filled($value))
            ->map(static fn (mixed $value): string => (string) $value)
            ->values()
            ->all();

        if ($fromRelation !== []) {
            return $fromRelation;
        }

        $consultation = $this->resolveConsultation($case);

        if ($consultation === null) {
            return [];
        }

        return array_values(array_filter(array_merge(
            is_array($consultation->labs) ? $consultation->labs : [],
            is_array($consultation->other_labs) ? $consultation->other_labs : [],
        ), static fn (mixed $value): bool => filled($value)));
    }

    /**
     * @return list<string>
     */
    protected function studiesForCase(TelemedicineCase $case): array
    {
        $fromRelation = TelemedicinePatientStudy::query()
            ->where('telemedicine_case_id', $case->id)
            ->orderBy('id')
            ->pluck('study')
            ->filter(static fn (mixed $value): bool => filled($value))
            ->map(static fn (mixed $value): string => (string) $value)
            ->values()
            ->all();

        if ($fromRelation !== []) {
            return $fromRelation;
        }

        $consultation = $this->resolveConsultation($case);

        if ($consultation === null) {
            return [];
        }

        return array_values(array_filter(array_merge(
            is_array($consultation->studies) ? $consultation->studies : [],
            is_array($consultation->other_studies) ? $consultation->other_studies : [],
        ), static fn (mixed $value): bool => filled($value)));
    }

    /**
     * @return list<string>
     */
    protected function specialistsForCase(TelemedicineCase $case): array
    {
        $fromRelation = TelemedicinePatientSpecialty::query()
            ->where('telemedicine_case_id', $case->id)
            ->orderBy('id')
            ->pluck('specialty')
            ->filter(static fn (mixed $value): bool => filled($value))
            ->map(static fn (mixed $value): string => (string) $value)
            ->values()
            ->all();

        if ($fromRelation !== []) {
            return $fromRelation;
        }

        $consultation = $this->resolveConsultation($case);

        if ($consultation === null) {
            return [];
        }

        return array_values(array_filter(array_merge(
            is_array($consultation->consult_specialist) ? $consultation->consult_specialist : [],
            is_array($consultation->other_specialist) ? $consultation->other_specialist : [],
        ), static fn (mixed $value): bool => filled($value)));
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    protected function labsSplitForCase(TelemedicineCase $case): array
    {
        $fromRelation = TelemedicinePatientLab::query()
            ->where('telemedicine_case_id', $case->id)
            ->orderBy('id')
            ->get(['laboratory', 'type']);

        if ($fromRelation->isNotEmpty()) {
            return $this->partitionByCoverageType($fromRelation, 'laboratory');
        }

        $consultation = $this->resolveConsultation($case);

        if ($consultation === null) {
            return [[], []];
        }

        return [
            $this->stringList($consultation->labs),
            $this->stringList($consultation->other_labs),
        ];
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    protected function studiesSplitForCase(TelemedicineCase $case): array
    {
        $fromRelation = TelemedicinePatientStudy::query()
            ->where('telemedicine_case_id', $case->id)
            ->orderBy('id')
            ->get(['study', 'type']);

        if ($fromRelation->isNotEmpty()) {
            return $this->partitionByCoverageType($fromRelation, 'study');
        }

        $consultation = $this->resolveConsultation($case);

        if ($consultation === null) {
            return [[], []];
        }

        return [
            $this->stringList($consultation->studies),
            $this->stringList($consultation->other_studies),
        ];
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    protected function specialistsSplitForCase(TelemedicineCase $case): array
    {
        $fromRelation = TelemedicinePatientSpecialty::query()
            ->where('telemedicine_case_id', $case->id)
            ->orderBy('id')
            ->get(['specialty', 'type']);

        if ($fromRelation->isNotEmpty()) {
            return $this->partitionByCoverageType($fromRelation, 'specialty');
        }

        $consultation = $this->resolveConsultation($case);

        if ($consultation === null) {
            return [[], []];
        }

        return [
            $this->stringList($consultation->consult_specialist),
            $this->stringList($consultation->other_specialist),
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array{0: list<string>, 1: list<string>}
     */
    protected function partitionByCoverageType(Collection $rows, string $nameAttribute): array
    {
        $covered = [];
        $other = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row->{$nameAttribute} ?? ''));
            if ($name === '') {
                continue;
            }

            $type = isset($row->type) ? (string) $row->type : null;

            if (TelemedicineCoverageCatalog::itemIsCoveredFromCatalogType($type !== '' ? $type : null)) {
                $covered[] = $name;
            } else {
                $other[] = $name;
            }
        }

        return [$covered, $other];
    }

    /**
     * @return list<string>
     */
    protected function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $labels = [];

        foreach ($value as $item) {
            $label = is_array($item)
                ? trim((string) ($item['name'] ?? $item['specialty'] ?? $item['study'] ?? $item['laboratory'] ?? ''))
                : trim((string) $item);

            if ($label !== '') {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    private function documentPatientName(
        TelemedicineConsultationPatient $consultation,
        TelemedicinePatient $patient,
        TelemedicineCase $case,
    ): string {
        return TelemedicinePatientDisplayName::fromPatientOrFallback(
            $patient,
            $consultation->full_name ?? $case->patient_name ?? $patient->full_name,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInformePayload(
        TelemedicineConsultationPatient $consultation,
        TelemedicineDoctor $doctor,
        TelemedicinePatient $patient,
        TelemedicineCase $case,
        bool $includeVitals,
    ): array {
        $medicationsArr = $this->medicationsForCase($case)
            ->map(static fn (TelemedicinePatientMedications $medication): array => [
                'medicines' => (string) ($medication->medicine ?? ''),
                'indications' => (string) ($medication->indications ?? ''),
                'duration' => (string) ($medication->duration ?? ''),
            ])
            ->values()
            ->all();

        $labsArr = $this->labsForCase($case);
        $studiesArr = $this->studiesForCase($case);
        $consultSpecialistArr = $this->specialistsForCase($case);
        $payload = [
            'fecha' => now()->format('d/m/Y'),
            'code_reference' => $consultation->code_reference,
            'name_patient' => $this->documentPatientName($consultation, $patient, $case),
            'ci_patient' => $consultation->nro_identificacion ?? $patient->nro_identificacion,
            'age_patient' => $patient->age ?? $case->patient_age,
            'reason' => $consultation->reason_consultation,
            'actual_phatology' => $consultation->actual_phatology,
            'background' => $consultation->background,
            'diagnostic_impression' => $consultation->diagnostic_impression,
            'peso' => $consultation->peso,
            'estatura' => $consultation->estatura,
            'imc' => $consultation->imc,
            'phone' => $case->patient_phone ?? $patient->phone,
            'medicationsArr' => $medicationsArr,
            'labsArr' => $labsArr,
            'otherLabsArr' => [],
            'studiesArr' => $studiesArr,
            'otherStudiesArr' => [],
            'consultSpecialistArr' => $consultSpecialistArr,
            'otherSpecialistArr' => [],
            'doctor_name' => $doctor->full_name,
            'code_cm' => $doctor->code_cm,
            'code_mpps' => $doctor->code_mpps,
            'signature' => $doctor->signature,
            'telemedicine_case_id' => $case->id,
            'telemedicine_consultation_id' => $consultation->id,
            'telemedicine_patient_id' => $patient->id,
        ];

        if ($includeVitals) {
            $payload['pa'] = $consultation->pa;
            $payload['fc'] = $consultation->fc;
            $payload['fr'] = $consultation->fr;
            $payload['temp'] = $consultation->temp;
            $payload['saturacion'] = $consultation->saturacion;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMedicamentosPayload(
        TelemedicineConsultationPatient $consultation,
        TelemedicineDoctor $doctor,
        TelemedicinePatient $patient,
        TelemedicineCase $case,
    ): array {
        $medicationsArr = $this->medicationsForCase($case)
            ->map(static fn (TelemedicinePatientMedications $medication): array => [
                'medicines' => (string) ($medication->medicine ?? ''),
                'indications' => (string) ($medication->indications ?? ''),
                'duration' => (string) ($medication->duration ?? ''),
                'operation_inventory_id' => $medication->operation_inventory_id,
                'is_covered' => TelemedicineMedicationCoverage::isCovered($medication),
            ])
            ->values()
            ->all();

        return [
            'fecha' => now()->format('d/m/Y'),
            'code_reference' => $consultation->code_reference,
            'name_patiente' => $this->documentPatientName($consultation, $patient, $case),
            'ci_patiente' => $consultation->nro_identificacion ?? $patient->nro_identificacion,
            'age_patiente' => $patient->age ?? $case->patient_age,
            'medicationsArr' => $medicationsArr,
            'doctor_name' => $doctor->full_name,
            'code_cm' => $doctor->code_cm,
            'code_mpps' => $doctor->code_mpps,
            'signature' => $doctor->signature,
            'telemedicine_case_id' => $case->id,
            'telemedicine_consultation_id' => $consultation->id,
            'telemedicine_patient_id' => $patient->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLaboratoriosPayload(
        TelemedicineConsultationPatient $consultation,
        TelemedicineDoctor $doctor,
        TelemedicinePatient $patient,
        TelemedicineCase $case,
    ): array {
        [$labs, $otherLabs] = $this->labsSplitForCase($case);

        return [
            'fecha' => now()->format('d/m/Y'),
            'code_reference' => $consultation->code_reference,
            'name_patiente' => $this->documentPatientName($consultation, $patient, $case),
            'ci_patiente' => $consultation->nro_identificacion ?? $patient->nro_identificacion,
            'age_patiente' => $patient->age ?? $case->patient_age,
            'labs' => $labs,
            'other_labs' => $otherLabs,
            'doctor_name' => $doctor->full_name,
            'code_cm' => $doctor->code_cm,
            'code_mpps' => $doctor->code_mpps,
            'signature' => $doctor->signature,
            'telemedicine_case_id' => $case->id,
            'telemedicine_consultation_id' => $consultation->id,
            'telemedicine_patient_id' => $patient->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildImagenologiaPayload(
        TelemedicineConsultationPatient $consultation,
        TelemedicineDoctor $doctor,
        TelemedicinePatient $patient,
        TelemedicineCase $case,
    ): array {
        [$studies, $otherStudies] = $this->studiesSplitForCase($case);

        return [
            'fecha' => now()->format('d/m/Y'),
            'code_reference' => $consultation->code_reference,
            'name_patiente' => $this->documentPatientName($consultation, $patient, $case),
            'ci_patiente' => $consultation->nro_identificacion ?? $patient->nro_identificacion,
            'age_patiente' => $patient->age ?? $case->patient_age,
            'studies' => $studies,
            'other_studies' => $otherStudies,
            'doctor_name' => $doctor->full_name,
            'code_cm' => $doctor->code_cm,
            'code_mpps' => $doctor->code_mpps,
            'signature' => $doctor->signature,
            'telemedicine_case_id' => $case->id,
            'telemedicine_consultation_id' => $consultation->id,
            'telemedicine_patient_id' => $patient->id,
            'phone' => $case->patient_phone ?? $patient->phone,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEspecialistaPayload(
        TelemedicineConsultationPatient $consultation,
        TelemedicineDoctor $doctor,
        TelemedicinePatient $patient,
        TelemedicineCase $case,
    ): array {
        [$specialists, $otherSpecialists] = $this->specialistsSplitForCase($case);

        return [
            'fecha' => now()->format('d/m/Y'),
            'code_reference' => $consultation->code_reference,
            'name_patiente' => $this->documentPatientName($consultation, $patient, $case),
            'ci_patiente' => $consultation->nro_identificacion ?? $patient->nro_identificacion,
            'age_patiente' => $patient->age ?? $case->patient_age,
            'consultSpecialistArr' => $specialists,
            'other_specialist' => $otherSpecialists,
            'doctor_name' => $doctor->full_name,
            'code_cm' => $doctor->code_cm,
            'code_mpps' => $doctor->code_mpps,
            'signature' => $doctor->signature,
            'telemedicine_case_id' => $case->id,
            'telemedicine_consultation_id' => $consultation->id,
            'telemedicine_patient_id' => $patient->id,
        ];
    }
}
