<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicinePatient;
use DateTimeInterface;
use Illuminate\Support\Str;

/**
 * Casos y consultas anteriores de un paciente, listos para leerse en la
 * pantalla de atención.
 *
 * Las consultas se agrupan por caso (más reciente primero) y, dentro de cada
 * caso, se cuentan en orden: consulta inicial, seguimientos y alta.
 */
final class TelemedicinePatientCareHistory
{
    /**
     * Largo de motivo y diagnóstico en las tarjetas (~2 líneas).
     */
    public const TEXT_LIMIT = 220;

    /**
     * @var array<string, string> columna => etiqueta del ítem indicado
     */
    private const COVERED_ITEMS = [
        'labs' => 'Laboratorio',
        'studies' => 'Estudio',
        'consult_specialist' => 'Especialista',
    ];

    /**
     * @var array<string, string>
     */
    private const NOT_COVERED_ITEMS = [
        'other_labs' => 'Laboratorio',
        'other_studies' => 'Estudio',
        'other_specialist' => 'Especialista',
    ];

    /**
     * @return list<array{
     *     case_id: int|null,
     *     code: string,
     *     status: string,
     *     is_current: bool,
     *     opened_at: string,
     *     consultations: list<array{
     *         stage: string,
     *         tone: string,
     *         date: string,
     *         doctor: string,
     *         service: string,
     *         reference: string,
     *         reason: string|null,
     *         diagnosis: string|null,
     *         covered: list<array{type: string, name: string}>,
     *         not_covered: list<array{type: string, name: string}>,
     *         physical_exam: array{vitals: list<array{label: string, value: string}>, systems: list<array{label: string, text: string, is_default: bool}>}|null
     *     }>
     * }>
     */
    public static function consultationsByCase(TelemedicinePatient $patient, ?int $currentCaseId = null): array
    {
        $consultations = $patient->telemedicineConsultationPatients()
            ->with([
                'telemedicineDoctor:id,full_name',
                'telemedicineServiceList:id,name',
                'telemedicineCase:id,code,status,created_at',
                'amdPhysicalExam',
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return $consultations
            ->groupBy(fn (TelemedicineConsultationPatient $consultation): string => (string) ($consultation->telemedicine_case_id ?? 'sin-caso'))
            ->map(function ($group) use ($currentCaseId): array {
                /** @var TelemedicineConsultationPatient $first */
                $first = $group->first();
                $case = $first->telemedicineCase;
                $caseId = $first->telemedicine_case_id !== null ? (int) $first->telemedicine_case_id : null;
                $followUp = 0;
                $seenInitial = false;

                return [
                    'case_id' => $caseId,
                    'code' => self::text($case?->code) ?? ($caseId !== null ? 'Caso #'.$caseId : 'Sin caso'),
                    'status' => self::text($case?->status) ?? '—',
                    'is_current' => $caseId !== null && $caseId === $currentCaseId,
                    'opened_at' => self::date($case?->created_at ?? $first->created_at),
                    'sort' => ($case?->created_at ?? $first->created_at)?->getTimestamp() ?? 0,
                    'consultations' => $group->values()->map(function (TelemedicineConsultationPatient $consultation) use (&$followUp, &$seenInitial): array {
                        $status = mb_strtoupper(trim((string) $consultation->status));
                        [$stage, $tone] = match (true) {
                            $status === 'ALTA MEDICA' => ['Alta médica', 'discharge'],
                            $status === 'EN SEGUIMIENTO', $seenInitial => ['Seguimiento '.(++$followUp), 'follow_up'],
                            default => ['Consulta inicial', 'initial'],
                        };
                        $seenInitial = true;

                        return [
                            'stage' => $stage,
                            'tone' => $tone,
                            'date' => self::date($consultation->created_at, withTime: true),
                            'doctor' => self::text($consultation->telemedicineDoctor?->full_name) ?? '—',
                            'service' => self::text($consultation->telemedicineServiceList?->name) ?? '—',
                            'reference' => self::text($consultation->code_reference) ?? 'CONS-'.$consultation->id,
                            'reason' => self::limited($consultation->reason_consultation),
                            'diagnosis' => self::limited($consultation->diagnostic_impression),
                            'covered' => self::items($consultation, self::COVERED_ITEMS),
                            'not_covered' => self::items($consultation, self::NOT_COVERED_ITEMS),
                            'physical_exam' => $consultation->amdPhysicalExam !== null
                                ? TelemedicineAmdPhysicalExamTemplate::display($consultation->amdPhysicalExam)
                                : null,
                        ];
                    })->all(),
                ];
            })
            ->sortByDesc('sort')
            ->map(function (array $case): array {
                unset($case['sort']);

                return $case;
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{
     *     id: int,
     *     code: string,
     *     status: string,
     *     tone: string,
     *     is_current: bool,
     *     opened_at: string,
     *     reason: string|null,
     *     doctor: string,
     *     priority: string|null,
     *     consultations: int
     * }>
     */
    public static function cases(TelemedicinePatient $patient, ?int $currentCaseId = null): array
    {
        return $patient->telemedicineCases()
            ->with(['telemedicineDoctor:id,full_name', 'priority:id,name'])
            ->withCount('consultations')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (TelemedicineCase $case): array => [
                'id' => (int) $case->id,
                'code' => self::text($case->code) ?? 'Caso #'.$case->id,
                'status' => self::text($case->status) ?? '—',
                'tone' => self::statusTone($case->status),
                'is_current' => (int) $case->id === $currentCaseId,
                'opened_at' => self::date($case->created_at, withTime: true),
                'reason' => self::limited($case->reason),
                'doctor' => self::text($case->telemedicineDoctor?->full_name) ?? 'Sin asignar',
                'priority' => self::text($case->priority?->name),
                'consultations' => (int) $case->consultations_count,
            ])
            ->all();
    }

    public static function statusTone(?string $status): string
    {
        $status = mb_strtoupper(trim((string) $status));

        return match (true) {
            $status === 'ALTA MEDICA' => 'success',
            str_contains($status, 'SEGUIMIENTO') => 'warning',
            str_contains($status, 'NEGAD'), str_contains($status, 'ANULAD'), str_contains($status, 'REVERS') => 'danger',
            default => 'info',
        };
    }

    /**
     * @param  array<string, string>  $columns
     * @return list<array{type: string, name: string}>
     */
    private static function items(TelemedicineConsultationPatient $consultation, array $columns): array
    {
        $items = [];

        foreach ($columns as $column => $type) {
            $values = $consultation->getAttribute($column);

            foreach (is_array($values) ? $values : [] as $value) {
                $name = is_scalar($value) ? self::text($value) : null;

                if ($name !== null) {
                    $items[] = ['type' => $type, 'name' => $name];
                }
            }
        }

        return $items;
    }

    private static function limited(mixed $value): ?string
    {
        $text = self::text($value);

        if ($text === null) {
            return null;
        }

        return Str::limit((string) preg_replace('/\s+/u', ' ', $text), self::TEXT_LIMIT, '…');
    }

    private static function text(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' || $text === '—' ? null : $text;
    }

    private static function date(mixed $value, bool $withTime = false): string
    {
        return $value instanceof DateTimeInterface
            ? $value->format($withTime ? 'd/m/Y h:i A' : 'd/m/Y')
            : '—';
    }
}
