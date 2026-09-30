<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Enums\ClinicalServiceChannel;
use App\Models\TelemedicineGeneralService;
use App\Models\TelemedicinePriority;
use App\Models\TelemedicineServiceList;
use App\Support\ClinicalEntitlements\ClinicalConsultationConsumption;
use App\Support\ClinicalEntitlements\TelemedicineConsultationClinicalUi;

/**
 * Resumen de lo que el médico cargó en el asistente, antes de registrar.
 *
 * Sólo lee el estado del formulario: no escribe nada. Las únicas consultas
 * son para traducir ids a nombres (servicio, prioridad, medicamentos) y
 * corren sólo cuando el médico abre el paso de revisión.
 */
final class TelemedicineConsultationReview
{
    /**
     * @param  array<string, mixed>  $state  Estado crudo del formulario.
     * @param  array{amd_inform: bool, amd_exam: bool}  $amd  Qué se cargó de la AMD.
     * @return array{
     *     kind: string,
     *     tone: string,
     *     is_discharge: bool,
     *     sections: list<array{title: string, step: string, fields: array<string, string>, lists: list<array{title: string, tone: string, items: list<string>}>, empty: string|null}>,
     *     warnings: list<string>,
     *     notes: list<string>
     * }
     */
    public static function build(array $state, array $amd = ['amd_inform' => false, 'amd_exam' => false]): array
    {
        $isFollowUp = mb_strtoupper(trim((string) ($state['status'] ?? ''))) === 'EN SEGUIMIENTO';
        $isDischarge = (bool) ($state['feedbackOne'] ?? false);
        $complements = array_map('intval', (array) ($state['complements'] ?? []));
        $serviceId = (int) ($state['telemedicine_service_list_id'] ?? 0);
        $isAmd = $serviceId === TelemedicineCaseTdgReassignmentCoordination::AMD_SERVICE_LIST_ID;

        $sections = [self::patientSection($state)];
        $sections[] = $isFollowUp ? self::followUpSection($state) : self::reasonSection($state);

        if (! $isDischarge && in_array(1, $complements, true)) {
            $sections[] = self::medicationsSection($state);
        }

        if (! $isDischarge && in_array(2, $complements, true)) {
            $sections[] = self::coveredListSection('Laboratorios y estudios', TelemedicineConsultationWizardSteps::LABS, [
                ['Laboratorios', $state['labs'] ?? [], $state['other_labs'] ?? []],
                ['Estudios de imagenología', $state['studies'] ?? [], $state['other_studies'] ?? []],
            ]);
        }

        if (! $isDischarge && in_array(TelemedicineConsultationClinicalUi::SPECIALIST_COMPLEMENT_KEY, $complements, true)) {
            $sections[] = self::coveredListSection('Interconsulta con especialista', TelemedicineConsultationWizardSteps::SPECIALIST, [
                ['Especialistas', $state['consult_specialist'] ?? [], $state['other_specialist'] ?? []],
            ]);
        }

        return [
            'kind' => $isDischarge ? 'Alta médica' : ($isFollowUp ? 'Seguimiento' : 'Consulta inicial'),
            'tone' => $isDischarge ? 'discharge' : ($isFollowUp ? 'follow_up' : 'initial'),
            'is_discharge' => $isDischarge,
            'sections' => $sections,
            'warnings' => self::warnings($state, $sections, $isFollowUp, $isDischarge, $isAmd, $amd),
            'notes' => self::notes($state, $isDischarge),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{title: string, step: string, fields: array<string, string>, lists: list<array{title: string, tone: string, items: list<string>}>, empty: string|null}
     */
    private static function patientSection(array $state): array
    {
        return self::section('Paciente', TelemedicineConsultationWizardSteps::PATIENT, [
            'Paciente' => $state['full_name'] ?? null,
            'Cédula' => $state['nro_identificacion'] ?? null,
            'Edad' => filled($state['age'] ?? null) ? $state['age'].' años' : null,
            'Caso' => $state['telemedicine_case_code'] ?? null,
            'Referencia' => $state['code_reference'] ?? null,
            'Teléfono' => $state['phone_ppal'] ?? null,
            'Prioridad' => self::priorityName($state['telemedicine_priority_id'] ?? null),
        ]);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{title: string, step: string, fields: array<string, string>, lists: list<array{title: string, tone: string, items: list<string>}>, empty: string|null}
     */
    private static function reasonSection(array $state): array
    {
        return self::section('Motivo y diagnóstico', TelemedicineConsultationWizardSteps::REASON, [
            'Signos vitales' => self::vitals($state),
            'Medidas' => self::measures($state),
            'Motivo de consulta' => $state['reason_consultation'] ?? null,
            'Enfermedad actual' => $state['actual_phatology'] ?? null,
            'Antecedentes' => $state['background'] ?? null,
            'Impresión diagnóstica' => $state['diagnostic_impression'] ?? null,
            ...self::serviceFields($state),
            'Observaciones' => $state['observations'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{title: string, step: string, fields: array<string, string>, lists: list<array{title: string, tone: string, items: list<string>}>, empty: string|null}
     */
    private static function followUpSection(array $state): array
    {
        return self::section('Cuestionario de seguimiento', TelemedicineConsultationWizardSteps::FOLLOW_UP, [
            'Historia de la enfermedad actual' => $state['current_illness_history'] ?? null,
            'Evolución' => $state['patient_evolution'] ?? null,
            'Cómo se siente' => $state['cuestion_1'] ?? null,
            'Respuesta al tratamiento' => $state['cuestion_2'] ?? null,
            'Mejoría de síntomas' => $state['cuestion_3'] ?? null,
            'Estudios realizados' => $state['cuestion_4'] ?? null,
            'Ajuste de indicaciones' => $state['cuestion_5'] ?? null,
            ...self::serviceFields($state),
            'Observaciones' => $state['observations'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{title: string, step: string, fields: array<string, string>, lists: list<array{title: string, tone: string, items: list<string>}>, empty: string|null}
     */
    private static function medicationsSection(array $state): array
    {
        $rows = TelemedicineMedicationsPdfRows::normalize(array_values(array_filter((array) ($state['medications'] ?? []), 'is_array')));

        $items = [];

        foreach ($rows as $row) {
            if (trim($row['medicines']) === '') {
                continue;
            }

            $detail = array_filter([
                trim($row['indications']),
                $row['quantity'] !== null ? 'Cantidad: '.$row['quantity'] : '',
                trim($row['duration']) !== '' ? 'Duración: '.trim($row['duration']) : '',
                trim($row['coverage']),
            ], fn (string $part): bool => $part !== '');

            $items[] = $row['medicines'].($detail === [] ? '' : ' — '.implode(' · ', $detail));
        }

        return [
            'title' => 'Medicamentos e indicaciones',
            'step' => TelemedicineConsultationWizardSteps::MEDICATIONS,
            'fields' => [],
            'lists' => $items === [] ? [] : [['title' => 'Medicamentos', 'tone' => 'neutral', 'items' => $items]],
            'empty' => $items === [] ? 'Marcó medicamentos pero no cargó ninguno.' : null,
        ];
    }

    /**
     * @param  list<array{0: string, 1: mixed, 2: mixed}>  $groups  [título, cubiertos, no cubiertos]
     * @return array{title: string, step: string, fields: array<string, string>, lists: list<array{title: string, tone: string, items: list<string>}>, empty: string|null}
     */
    private static function coveredListSection(string $title, string $step, array $groups): array
    {
        $lists = [];

        foreach ($groups as [$groupTitle, $covered, $notCovered]) {
            foreach ([[$covered, 'Cubiertos', 'covered'], [$notCovered, 'No cubiertos', 'not_covered']] as [$values, $suffix, $tone]) {
                $items = self::names($values);

                if ($items !== []) {
                    $lists[] = ['title' => $groupTitle.' · '.$suffix, 'tone' => $tone, 'items' => $items];
                }
            }
        }

        return [
            'title' => $title,
            'step' => $step,
            'fields' => [],
            'lists' => $lists,
            'empty' => $lists === [] ? 'Marcó este complemento pero no eligió ninguno.' : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  list<array<string, mixed>>  $sections
     * @param  array{amd_inform: bool, amd_exam: bool}  $amd
     * @return list<string>
     */
    private static function warnings(array $state, array $sections, bool $isFollowUp, bool $isDischarge, bool $isAmd, array $amd): array
    {
        $warnings = [];

        if (! $isFollowUp && blank($state['diagnostic_impression'] ?? null)) {
            $warnings[] = 'La impresión diagnóstica está vacía.';
        }

        if (! $isFollowUp && blank($state['reason_consultation'] ?? null)) {
            $warnings[] = 'El motivo de consulta está vacío.';
        }

        foreach ($sections as $section) {
            if ($section['empty'] !== null) {
                $warnings[] = $section['title'].': '.$section['empty'];
            }
        }

        if ($isAmd && ! $amd['amd_exam']) {
            $warnings[] = 'Es una AMD y todavía no cargó el examen físico.';
        }

        if ($isAmd && ! $amd['amd_inform']) {
            $warnings[] = 'Es una AMD y todavía no registró el informe AMD.';
        }

        return $warnings;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return list<string>
     */
    private static function notes(array $state, bool $isDischarge): array
    {
        $notes = [];

        if ($isDischarge) {
            $notes[] = 'Es un alta médica: al registrar se cierra el caso y no se crean medicamentos, laboratorios ni interconsultas.';
        }

        $channels = array_keys(ClinicalConsultationConsumption::requestedChannels($state));
        $labels = array_values(array_filter(array_map(
            static fn (string $channel): ?string => ClinicalServiceChannel::tryFrom($channel)?->shortLabel(),
            $channels,
        )));

        if ($labels !== [] && ! $isDischarge) {
            $notes[] = 'Esta consulta usará cupo del plan en: '.implode(', ', $labels).'.';
        }

        return $notes;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private static function serviceFields(array $state): array
    {
        return [
            'Servicio' => self::serviceName($state['telemedicine_service_list_id'] ?? null),
            'Servicio derivado' => self::serviceName($state['telemedicine_service_list_drift_id'] ?? null),
            'Servicio general' => self::generalServiceName($state['telemedicine_general_service_id'] ?? null),
            'Prioridad de monitoreo' => $state['priorityMonitoring'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array{title: string, step: string, fields: array<string, string>, lists: list<array{title: string, tone: string, items: list<string>}>, empty: string|null}
     */
    private static function section(string $title, string $step, array $fields): array
    {
        return [
            'title' => $title,
            'step' => $step,
            'fields' => array_filter(
                array_map(static fn (mixed $value): ?string => self::text($value), $fields),
                static fn (?string $value): bool => $value !== null,
            ),
            'lists' => [],
            'empty' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private static function vitals(array $state): ?string
    {
        $parts = [];

        foreach (['pa' => 'PA', 'fc' => 'FC', 'fr' => 'FR', 'temp' => 'Temp', 'saturacion' => 'Sat'] as $key => $label) {
            $value = self::text($state[$key] ?? null);

            if ($value !== null) {
                $parts[] = $label.' '.$value;
            }
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private static function measures(array $state): ?string
    {
        $parts = [];

        foreach (['peso' => ['Peso', 'kg'], 'estatura' => ['Estatura', 'm'], 'imc' => ['IMC', '']] as $key => [$label, $unit]) {
            $value = self::text($state[$key] ?? null);

            if ($value !== null) {
                $parts[] = trim($label.' '.$value.' '.$unit);
            }
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * @return list<string>
     */
    private static function names(mixed $values): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $value): ?string => is_scalar($value) ? self::text($value) : null,
            is_array($values) ? $values : [],
        )));
    }

    private static function serviceName(mixed $id): ?string
    {
        return filled($id) ? TelemedicineServiceList::query()->whereKey((int) $id)->value('name') : null;
    }

    private static function generalServiceName(mixed $id): ?string
    {
        return filled($id) ? TelemedicineGeneralService::query()->whereKey((int) $id)->value('name') : null;
    }

    private static function priorityName(mixed $id): ?string
    {
        return filled($id) ? TelemedicinePriority::query()->whereKey((int) $id)->value('name') : null;
    }

    private static function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' || $text === '—' ? null : (string) preg_replace("/\n{3,}/", "\n\n", $text);
    }
}
