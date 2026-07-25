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
use Illuminate\Support\Facades\Bus;
use InvalidArgumentException;

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
    public function regenerate(TelemedicineCase $case, array $documentKeys, User $user): array
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

        $doctor = TelemedicineDoctor::query()->find($consultation->telemedicine_doctor_id ?? $case->telemedicine_doctor_id);
        $patient = TelemedicinePatient::query()->find($consultation->telemedicine_patient_id ?? $case->telemedicine_patient_id);

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
                $jobs[] = $job;
            }
        }

        if ($jobs === []) {
            throw new InvalidArgumentException('No se pudieron preparar los documentos seleccionados.');
        }

        Bus::batch($jobs)
            ->name('telemedicina-case-docs-regenerate-'.$case->id.'-'.now()->timestamp)
            ->onQueue('telemedicina')
            ->dispatch();

        return $selected;
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
            'name_patient' => $consultation->full_name ?? $case->patient_name ?? $patient->full_name,
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
            ])
            ->values()
            ->all();

        return [
            'fecha' => now()->format('d/m/Y'),
            'code_reference' => $consultation->code_reference,
            'name_patiente' => $consultation->full_name ?? $case->patient_name ?? $patient->full_name,
            'ci_patiente' => $consultation->nro_identificacion ?? $patient->nro_identificacion,
            'age_patiente' => $patient->age ?? $case->patient_age,
            'medicationsArr' => $medicationsArr,
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
        return [
            'fecha' => now()->format('d/m/Y'),
            'code_reference' => $consultation->code_reference,
            'name_patiente' => $consultation->full_name ?? $case->patient_name ?? $patient->full_name,
            'ci_patiente' => $consultation->nro_identificacion ?? $patient->nro_identificacion,
            'age_patiente' => $patient->age ?? $case->patient_age,
            'labs' => $this->labsForCase($case),
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
        return [
            'fecha' => now()->format('d/m/Y'),
            'code_reference' => $consultation->code_reference,
            'name_patiente' => $consultation->full_name ?? $case->patient_name ?? $patient->full_name,
            'ci_patiente' => $consultation->nro_identificacion ?? $patient->nro_identificacion,
            'age_patiente' => $patient->age ?? $case->patient_age,
            'studies' => $this->studiesForCase($case),
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
        return [
            'fecha' => now()->format('d/m/Y'),
            'code_reference' => $consultation->code_reference,
            'name_patiente' => $consultation->full_name ?? $case->patient_name ?? $patient->full_name,
            'ci_patiente' => $consultation->nro_identificacion ?? $patient->nro_identificacion,
            'age_patiente' => $patient->age ?? $case->patient_age,
            'consultSpecialistArr' => $this->specialistsForCase($case),
            'code_cm' => $doctor->code_cm,
            'code_mpps' => $doctor->code_mpps,
            'signature' => $doctor->signature,
            'telemedicine_case_id' => $case->id,
            'telemedicine_consultation_id' => $consultation->id,
            'telemedicine_patient_id' => $patient->id,
        ];
    }
}
