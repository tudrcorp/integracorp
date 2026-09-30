<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineHistoryPatient;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Historia clínica del paciente ordenada para leerse de un vistazo.
 *
 * Primero lo que cambia la conducta médica (alergias, enfermedades
 * personales activas, medicación habitual) y después cada bloque de
 * antecedentes con sólo lo que el médico registró.
 */
final class TelemedicinePatientHistorySummary
{
    /**
     * Condiciones del formulario: columna del sí/no => [etiqueta, columna de detalle].
     * Los antecedentes personales usan las mismas columnas con sufijo `_app`.
     *
     * @var array<string, array{0: string, 1: string|null}>
     */
    private const CONDITIONS = [
        'tension_alta' => ['Hipertensión arterial', 'input_tension_alta'],
        'diabetes' => ['Diabetes mellitus', 'input_diabetes'],
        'asma' => ['Asma bronquial', 'input_asma'],
        'cardiacos' => ['Enfermedades cardíacas', 'input_cardiacos'],
        'gastritis_ulceras' => ['Gastropatías', 'input_gastritis_ulceras'],
        'enfermedad_autoimmune' => ['Enfermedad autoinmune', 'input_enfermedad_autoimmune'],
        'trombosis_embooleanas' => ['Insuficiencia venosa', 'input_trombosis_embooleanas'],
        'fracturas' => ['Traumatismos', 'input_fracturas'],
        'cancer' => ['Cáncer', 'input_cancer'],
        'tranfusiones_sanguineas' => ['Anemia', 'input_ftranfusiones_sanguineas'],
        'tiroides' => ['Tiroides', 'input_tiroides'],
        'hepatitis' => ['Hepatitis', 'input_hepatitis'],
        'moretones_frecuentes' => ['Enfermedades hematológicas', 'input_moretones_frecuentes'],
        'psiquiatricas' => ['Enfermedades psiquiátricas', 'input_psiquiatricas'],
        'covid' => ['COVID-19', null],
    ];

    /**
     * @var array<string, string>
     */
    private const HABITS = [
        'tabaco' => 'Tabaquismo',
        'alcohol' => 'Alcohol',
        'drogas' => 'Drogas',
    ];

    /**
     * @return array{
     *     exists: bool,
     *     meta: array<string, string>,
     *     alerts: list<array{title: string, items: list<string>}>,
     *     sections: list<array{title: string, icon: string, items: list<array{label: string, detail: string|null}>, notes: list<string>}>
     * }
     */
    public static function summarize(?TelemedicineHistoryPatient $history): array
    {
        if ($history === null) {
            return ['exists' => false, 'meta' => [], 'alerts' => [], 'sections' => []];
        }

        $history->loadMissing([
            'pathologicalHistories',
            'noPathologicalHistories',
            'surgicalHistories',
            'familyHistories',
            'gynecologicalHistories',
        ]);

        $personal = self::conditions($history, '_app');

        if (self::flag($history, 'vih_app')) {
            $personal[] = ['label' => 'VIH', 'detail' => null];
        }

        $allergies = self::allergies($history);
        $medications = self::texts([$history->medications_supplements]);

        $alerts = array_values(array_filter([
            $allergies !== [] ? ['title' => 'Alergias', 'items' => $allergies] : null,
            $personal !== [] ? ['title' => 'Enfermedades del paciente', 'items' => array_map(
                static fn (array $item): string => $item['detail'] === null ? $item['label'] : $item['label'].': '.$item['detail'],
                $personal,
            )] : null,
            $medications !== [] ? ['title' => 'Medicación habitual', 'items' => $medications] : null,
        ]));

        return [
            'exists' => true,
            'meta' => array_filter([
                'Nro. de historia' => self::text($history->code),
                'Registrada' => self::date($history->created_at),
                'Registrada por' => self::text($history->created_by),
                'Última actualización' => self::date($history->updated_at),
            ], static fn (?string $value): bool => $value !== null),
            'alerts' => $alerts,
            'sections' => [
                [
                    'title' => 'Antecedentes personales patológicos',
                    'icon' => 'heroicon-o-user',
                    'items' => $personal,
                    'notes' => self::texts([$history->observations_pathological, ...self::relatedNotes($history->pathologicalHistories)]),
                ],
                [
                    'title' => 'Antecedentes familiares',
                    'icon' => 'heroicon-o-user-group',
                    'items' => self::conditions($history, ''),
                    'notes' => self::texts([$history->observations_personal, ...self::relatedNotes($history->familyHistories)]),
                ],
                [
                    'title' => 'Antecedentes quirúrgicos',
                    'icon' => 'heroicon-o-scissors',
                    'items' => [],
                    'notes' => self::texts([$history->history_surgical, ...self::relatedNotes($history->surgicalHistories)]),
                ],
                [
                    'title' => 'Hábitos (no patológicos)',
                    'icon' => 'heroicon-o-sun',
                    'items' => array_values(array_map(
                        static fn (string $label): array => ['label' => $label, 'detail' => null],
                        array_filter(self::HABITS, static fn (string $label, string $column): bool => self::flag($history, $column), ARRAY_FILTER_USE_BOTH),
                    )),
                    'notes' => self::texts([$history->observations_not_pathological, ...self::relatedNotes($history->noPathologicalHistories)]),
                ],
                [
                    'title' => 'Medicamentos y suplementos',
                    'icon' => 'heroicon-o-beaker',
                    'items' => [],
                    'notes' => self::texts([$history->medications_supplements, $history->observations_medication]),
                ],
                [
                    'title' => 'Antecedentes ginecológicos',
                    'icon' => 'heroicon-o-heart',
                    'items' => self::gynecological($history),
                    'notes' => self::texts([$history->observations_ginecologica, ...self::relatedNotes($history->gynecologicalHistories)]),
                ],
            ],
        ];
    }

    /**
     * @return list<array{label: string, detail: string|null}>
     */
    private static function conditions(TelemedicineHistoryPatient $history, string $suffix): array
    {
        $items = [];

        foreach (self::CONDITIONS as $column => [$label, $detailColumn]) {
            if (! self::flag($history, $column.$suffix)) {
                continue;
            }

            $items[] = [
                'label' => $label,
                'detail' => $detailColumn === null ? null : self::text($history->getAttribute($detailColumn.$suffix)),
            ];
        }

        return $items;
    }

    /**
     * @return list<array{label: string, detail: string|null}>
     */
    private static function gynecological(TelemedicineHistoryPatient $history): array
    {
        $items = [];

        foreach ([
            'numero_embarazos' => 'Embarazos',
            'numero_partos' => 'Partos',
            'cesareas' => 'Cesáreas',
            'numero_abortos' => 'Abortos',
        ] as $column => $label) {
            $value = (int) ($history->getAttribute($column) ?? 0);

            if ($value > 0) {
                $items[] = ['label' => $label, 'detail' => (string) $value];
            }
        }

        foreach ([
            'edad_primera_menstruation' => 'Edad de la primera menstruación',
            'fecha_ultima_regla' => 'Fecha de última regla',
        ] as $column => $label) {
            $value = self::text($history->getAttribute($column));

            if ($value !== null) {
                $items[] = ['label' => $label, 'detail' => $value];
            }
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    private static function allergies(TelemedicineHistoryPatient $history): array
    {
        $listed = is_array($history->allergies) ? $history->allergies : [];

        return self::texts([...$listed, $history->observations_allergies]);
    }

    /**
     * Notas fechadas que se registran al editar la historia.
     *
     * @param  Collection<int, \Illuminate\Database\Eloquent\Model>|null  $records
     * @return list<string>
     */
    private static function relatedNotes(?Collection $records): array
    {
        if ($records === null) {
            return [];
        }

        return $records
            ->sortByDesc('created_at')
            ->map(function ($record): ?string {
                $text = self::text($record->observations ?? null);

                if ($text === null) {
                    return null;
                }

                $date = self::date($record->created_at ?? null);

                return $date === null ? $text : $date.' — '.$text;
            })
            ->filter()
            ->values()
            ->all();
    }

    private static function flag(TelemedicineHistoryPatient $history, string $column): bool
    {
        return (bool) $history->getAttribute($column);
    }

    /**
     * Textos no vacíos y sin repetir.
     *
     * @param  list<mixed>  $values
     * @return list<string>
     */
    private static function texts(array $values): array
    {
        $out = [];

        foreach ($values as $value) {
            $text = self::text(is_scalar($value) ? $value : null);

            if ($text !== null && ! in_array($text, $out, true)) {
                $out[] = $text;
            }
        }

        return $out;
    }

    private static function text(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' || $text === '—' || $text === '-' ? null : $text;
    }

    private static function date(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        $text = self::text($value);

        if ($text === null) {
            return null;
        }

        try {
            return Carbon::parse($text)->format('d/m/Y');
        } catch (\Throwable) {
            return $text;
        }
    }
}
