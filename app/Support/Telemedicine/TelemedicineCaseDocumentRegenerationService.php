<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Jobs\GeneratePdfEspecialista;
use App\Jobs\GeneratePdfImagenologia;
use App\Jobs\GeneratePdfInformeMedicoLargo;
use App\Jobs\GeneratePdfInformeSeguimiento;
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
    /**
     * El informe corto salió de circulación: ya no se emite al guardar la
     * consulta ni se ofrece para regenerar. La constante y su job siguen vivos
     * porque 462 consultas históricas lo tienen registrado y deben poder
     * listarse y descargarse.
     *
     * @deprecated
     */
    public const DOCUMENT_INFORME_CORTO = 'informe-corto';

    /**
     * Antes «informe largo». Es el único informe de la consulta inicial y se
     * emite también en los servicios AMD.
     */
    public const DOCUMENT_INFORME_MEDICO = 'informe-medico';

    public const DOCUMENT_INFORME_SEGUIMIENTO = 'informe-seguimiento';

    public const DOCUMENT_MEDICAMENTOS = 'medicamentos';

    public const DOCUMENT_LABORATORIOS = 'laboratorios';

    public const DOCUMENT_IMAGENOLOGIA = 'imagenologia';

    public const DOCUMENT_ESPECIALISTA = 'especialista';

    /**
     * La ventana arma selector, aviso y lista de documentos en el mismo render:
     * las consultas del caso se leen una sola vez por instancia.
     *
     * @var array<int, Collection<int, TelemedicineConsultationPatient>>
     */
    private array $consultationsByCase = [];

    /**
     * Aviso cuando una consulta no tiene médico registrado. Firmarla con el médico
     * del caso era justo el error que se quiere evitar: en el pool (TDG o equipo
     * de un proveedor) quien atiende no es necesariamente el asignado.
     */
    public const MISSING_DOCTOR_MESSAGE = 'Esta consulta no tiene médico registrado, así que sus documentos no se regeneran: saldrían con la firma de otro médico. Reporte el caso a soporte para corregir la consulta.';

    /**
     * Consultas del caso, en orden cronológico.
     *
     * Los documentos se regeneran **por consulta**: cada una lleva la firma del
     * médico que la atendió y solo lo que ese médico indicó en ella. Juntar los
     * ítems de todo el caso bajo una sola firma estampaba el sello del médico de
     * la consulta inicial en lo que recetaron los médicos de los seguimientos.
     *
     * @return Collection<int, TelemedicineConsultationPatient>
     */
    public function consultationsOf(TelemedicineCase $case): Collection
    {
        return $this->consultationsByCase[(int) $case->id] ??= TelemedicineConsultationPatient::query()
            ->with('telemedicineDoctor')
            ->where('telemedicine_case_id', $case->id)
            ->orderBy('id')
            ->get();
    }

    /**
     * Una consulta del caso por id. El id llega del formulario: se busca solo
     * entre las del caso para que un valor manipulado no regenere documentos de
     * otro paciente.
     */
    public function consultationOf(TelemedicineCase $case, mixed $consultationId): ?TelemedicineConsultationPatient
    {
        $id = (int) $consultationId;

        if ($id < 1) {
            return null;
        }

        return $this->consultationsOf($case)
            ->first(static fn (TelemedicineConsultationPatient $row): bool => (int) $row->id === $id);
    }

    /**
     * Si el caso tiene al menos una consulta. Es lo único que se consulta para
     * mostrar la acción: se evalúa por fila de tabla y no debe armar documentos.
     */
    public function caseHasConsultations(TelemedicineCase $case): bool
    {
        return TelemedicineConsultationPatient::query()
            ->where('telemedicine_case_id', $case->id)
            ->exists();
    }

    /**
     * Opciones del selector de consulta: id => etiqueta con tipo, fecha y médico.
     *
     * @return array<int, string>
     */
    public function consultationOptions(TelemedicineCase $case): array
    {
        $options = [];

        foreach ($this->consultationsOf($case) as $consultation) {
            $options[(int) $consultation->id] = $this->consultationLabel($consultation);
        }

        return $options;
    }

    public function consultationLabel(TelemedicineConsultationPatient $consultation): string
    {
        $type = $this->isInitialConsultation($consultation)
            ? 'Consulta inicial'
            : 'Seguimiento';

        $date = $consultation->created_at?->format('d/m/Y h:i A') ?? 'sin fecha';
        $doctor = $this->signingDoctorFor($consultation);
        $doctorLabel = $doctor !== null
            ? trim((string) $doctor->full_name)
            : 'sin médico registrado';

        return "{$type} · {$date} · {$doctorLabel}";
    }

    /**
     * Consulta preseleccionada: la más reciente que sí puede regenerarse.
     */
    public function defaultConsultationId(TelemedicineCase $case): ?int
    {
        foreach ($this->consultationsOf($case)->reverse() as $consultation) {
            if ($this->signingDoctorFor($consultation) !== null && $this->availableOptions($consultation) !== []) {
                return (int) $consultation->id;
            }
        }

        return null;
    }

    /**
     * Ids de las consultas del caso que no tienen médico y por eso no se regeneran.
     *
     * @return list<int>
     */
    public function consultationIdsWithoutDoctor(TelemedicineCase $case): array
    {
        return $this->consultationsOf($case)
            ->filter(fn (TelemedicineConsultationPatient $consultation): bool => $this->signingDoctorFor($consultation) === null)
            ->map(static fn (TelemedicineConsultationPatient $consultation): int => (int) $consultation->id)
            ->values()
            ->all();
    }

    /**
     * Documentos que puede emitir una consulta concreta, con lo que se registró
     * en ella y nada más.
     *
     * @return array<string, string>
     */
    public function availableOptions(TelemedicineConsultationPatient $consultation): array
    {
        $options = [];

        if ($this->isInitialConsultation($consultation)) {
            if ($this->canGenerateInforme($consultation)) {
                $options[self::DOCUMENT_INFORME_MEDICO] = 'Informe médico (consulta inicial)';
            }
        } elseif (TelemedicineFollowUpReportDocument::appliesTo((string) $consultation->status)) {
            $options[self::DOCUMENT_INFORME_SEGUIMIENTO] = 'Informe de seguimiento';
        }

        if ($this->medicationsForConsultation($consultation)->isNotEmpty()) {
            $options[self::DOCUMENT_MEDICAMENTOS] = 'Recipe de medicamentos';
        }

        if ($this->labsForConsultation($consultation) !== []) {
            $options[self::DOCUMENT_LABORATORIOS] = 'Orden de laboratorios';
        }

        if ($this->studiesForConsultation($consultation) !== []) {
            $options[self::DOCUMENT_IMAGENOLOGIA] = 'Orden de estudios / imagenología';
        }

        if ($this->specialistsForConsultation($consultation) !== []) {
            $options[self::DOCUMENT_ESPECIALISTA] = 'Referencia a especialistas';
        }

        return $options;
    }

    /**
     * Regenera los documentos seleccionados de **una** consulta, dentro del
     * request y sin pasar por la cola.
     *
     * Esta acción es el plan B del médico justo cuando la cola de documentos ha
     * fallado: encolar aquí reproduciría el fallo que se quiere sortear. Se
     * ejecuta cada job de forma aislada para que un documento roto no impida los
     * demás, y se devuelve el detalle de lo que salió y lo que no.
     *
     * @param  list<string>  $documentKeys
     */
    public function regenerate(
        TelemedicineCase $case,
        ?int $consultationId,
        array $documentKeys,
        User $user,
    ): TelemedicineCaseDocumentRegenerationResult {
        $documentKeys = array_values(array_unique(array_filter($documentKeys, static fn (mixed $key): bool => is_string($key) && $key !== '')));

        if ($documentKeys === []) {
            throw new InvalidArgumentException('Debe seleccionar al menos un documento.');
        }

        $consultation = $this->consultationOf($case, $consultationId);

        if ($consultation === null) {
            throw new InvalidArgumentException('Seleccione una consulta de este caso para regenerar sus documentos.');
        }

        $doctor = $this->signingDoctorFor($consultation);

        if ($doctor === null) {
            throw new InvalidArgumentException(self::MISSING_DOCTOR_MESSAGE);
        }

        $available = $this->availableOptions($consultation);
        $selected = array_values(array_filter(
            $documentKeys,
            static fn (string $key): bool => array_key_exists($key, $available),
        ));

        if ($selected === []) {
            throw new InvalidArgumentException('Ninguno de los documentos seleccionados está disponible para esta consulta.');
        }

        $patient = $this->resolvePatient($consultation, $case);

        if ($patient === null) {
            throw new InvalidArgumentException('No se encontró el paciente de la consulta.');
        }

        $jobs = [];

        foreach ($selected as $documentKey) {
            $job = match ($documentKey) {
                self::DOCUMENT_INFORME_MEDICO => new GeneratePdfInformeMedicoLargo(
                    $this->buildInformePayload($consultation, $doctor, $patient, $case, includeVitals: true),
                    $user,
                    self::DOCUMENT_INFORME_MEDICO,
                ),
                self::DOCUMENT_INFORME_SEGUIMIENTO => $this->makeFollowUpReportJob($consultation, $doctor, $patient, $user),
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

    /**
     * Médico que firma: el de la consulta, sin respaldo. Ni el médico asignado al
     * caso ni el usuario que regenera hicieron ese acto clínico.
     */
    protected function signingDoctorFor(TelemedicineConsultationPatient $consultation): ?TelemedicineDoctor
    {
        $doctorId = (int) ($consultation->telemedicine_doctor_id ?? 0);

        if ($doctorId < 1) {
            return null;
        }

        $doctor = $consultation->relationLoaded('telemedicineDoctor')
            ? $consultation->getRelation('telemedicineDoctor')
            : null;

        if ($doctor instanceof TelemedicineDoctor && (int) $doctor->id === $doctorId) {
            return $doctor;
        }

        return TelemedicineDoctor::query()->find($doctorId);
    }

    protected function resolvePatient(TelemedicineConsultationPatient $consultation, TelemedicineCase $case): ?TelemedicinePatient
    {
        return TelemedicinePatient::query()->find($consultation->telemedicine_patient_id ?? $case->telemedicine_patient_id);
    }

    protected function isInitialConsultation(TelemedicineConsultationPatient $consultation): bool
    {
        return trim((string) $consultation->status) === TelemedicineInitialDiagnosisUpdater::INITIAL_STATUS;
    }

    protected function makeFollowUpReportJob(
        TelemedicineConsultationPatient $consultation,
        TelemedicineDoctor $doctor,
        TelemedicinePatient $patient,
        User $user,
    ): ?GeneratePdfInformeSeguimiento {
        $payload = TelemedicineFollowUpReportDocument::payloadFromConsultation(
            $consultation,
            $doctor,
            $patient,
        );

        if ($payload === null) {
            return null;
        }

        return TelemedicineFollowUpReportDocument::makeJob($payload, $user);
    }

    protected function canGenerateInforme(TelemedicineConsultationPatient $consultation): bool
    {
        return filled($consultation->reason_consultation)
            || filled($consultation->actual_phatology)
            || filled($consultation->diagnostic_impression)
            || filled($consultation->code_reference);
    }

    /**
     * @return Collection<int, TelemedicinePatientMedications>
     */
    protected function medicationsForConsultation(TelemedicineConsultationPatient $consultation): Collection
    {
        return TelemedicinePatientMedications::query()
            ->with('operationInventory')
            ->where('telemedicine_consultation_patient_id', $consultation->id)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<string>
     */
    protected function labsForConsultation(TelemedicineConsultationPatient $consultation): array
    {
        [$covered, $other] = $this->labsSplitForConsultation($consultation);

        return array_values(array_merge($covered, $other));
    }

    /**
     * @return list<string>
     */
    protected function studiesForConsultation(TelemedicineConsultationPatient $consultation): array
    {
        [$covered, $other] = $this->studiesSplitForConsultation($consultation);

        return array_values(array_merge($covered, $other));
    }

    /**
     * @return list<string>
     */
    protected function specialistsForConsultation(TelemedicineConsultationPatient $consultation): array
    {
        [$covered, $other] = $this->specialistsSplitForConsultation($consultation);

        return array_values(array_merge($covered, $other));
    }

    /**
     * Las filas de la relación mandan; los campos JSON de la consulta quedan
     * como respaldo para consultas anteriores a esas tablas.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    protected function labsSplitForConsultation(TelemedicineConsultationPatient $consultation): array
    {
        $fromRelation = TelemedicinePatientLab::query()
            ->where('telemedicine_consultation_patient_id', $consultation->id)
            ->orderBy('id')
            ->get(['laboratory', 'type']);

        if ($fromRelation->isNotEmpty()) {
            return $this->partitionByCoverageType($fromRelation, 'laboratory');
        }

        return [
            $this->stringList($consultation->labs),
            $this->stringList($consultation->other_labs),
        ];
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    protected function studiesSplitForConsultation(TelemedicineConsultationPatient $consultation): array
    {
        $fromRelation = TelemedicinePatientStudy::query()
            ->where('telemedicine_consultation_patient_id', $consultation->id)
            ->orderBy('id')
            ->get(['study', 'type']);

        if ($fromRelation->isNotEmpty()) {
            return $this->partitionByCoverageType($fromRelation, 'study');
        }

        return [
            $this->stringList($consultation->studies),
            $this->stringList($consultation->other_studies),
        ];
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    protected function specialistsSplitForConsultation(TelemedicineConsultationPatient $consultation): array
    {
        $fromRelation = TelemedicinePatientSpecialty::query()
            ->where('telemedicine_consultation_patient_id', $consultation->id)
            ->orderBy('id')
            ->get(['specialty', 'type']);

        if ($fromRelation->isNotEmpty()) {
            return $this->partitionByCoverageType($fromRelation, 'specialty');
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
        $medicationsArr = $this->medicationsForConsultation($consultation)
            ->map(static fn (TelemedicinePatientMedications $medication): array => [
                'medicines' => (string) ($medication->medicine ?? ''),
                'indications' => (string) ($medication->indications ?? ''),
                'duration' => (string) ($medication->duration ?? ''),
            ])
            ->values()
            ->all();

        $labsArr = $this->labsForConsultation($consultation);
        $studiesArr = $this->studiesForConsultation($consultation);
        $consultSpecialistArr = $this->specialistsForConsultation($consultation);
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
            'observations' => $consultation->observations,
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
        $medicationsArr = $this->medicationsForConsultation($consultation)
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
        [$labs, $otherLabs] = $this->labsSplitForConsultation($consultation);

        return [
            'fecha' => now()->format('d/m/Y'),
            'consultation_status' => $consultation->status,
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
        [$studies, $otherStudies] = $this->studiesSplitForConsultation($consultation);

        return [
            'fecha' => now()->format('d/m/Y'),
            'consultation_status' => $consultation->status,
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
        [$specialists, $otherSpecialists] = $this->specialistsSplitForConsultation($consultation);

        return [
            'fecha' => now()->format('d/m/Y'),
            'consultation_status' => $consultation->status,
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
