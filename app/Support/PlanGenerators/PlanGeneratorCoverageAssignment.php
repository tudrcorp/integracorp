<?php

declare(strict_types=1);

namespace App\Support\PlanGenerators;

use App\Models\PlanGenerator;
use App\Models\PlanGeneratorPopulation;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Ubicación manual de cada persona del padrón en una de las coberturas elegidas
 * para la pre-afiliación corporativa de un plan generado.
 *
 * La regla es una sola y vive aquí: una persona solo puede ir a una cobertura si
 * su edad —la del padrón importado— cae en un rango etario de esa cobertura con
 * tarifa cargada. La aplican la acción de asignar, el botón «Continuar» y la
 * creación de la afiliación, que vuelve a validar todo contra la base: la
 * sesión y el navegador nunca se dan por buenos.
 *
 * El rango y la tarifa no se guardan con la persona: se resuelven siempre
 * contra la matriz vigente del plan. Si el plan se edita después de asignar,
 * la asignación que deja de corresponder se reporta y bloquea, en vez de
 * afiliar con una tarifa que ya no existe.
 *
 * Los límites del rango se leen con el mismo parser que publica `age_ranges`
 * al catálogo (`PlanGeneratorCatalogPublisher::ageRangeBounds()`), para que el
 * sincronizador de afiliados corporativos llegue después al mismo rango.
 */
final class PlanGeneratorCoverageAssignment
{
    public const PLAN_FAILURE_PREFIX = 'Falla en la creación del plan';

    /**
     * Informes ya calculados en este request: el estado de la página, el
     * tooltip del botón y cada fila de la tabla lo consultan por separado.
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $reports = [];

    public static function flush(): void
    {
        self::$reports = [];
    }

    /**
     * Coberturas elegidas con sus rangos tarifados, en el orden de la matriz.
     *
     * `defects` lista lo que impide usar la cobertura tal como está armada:
     * rangos sin edad mínima y máxima, rangos solapados o ningún rango con
     * tarifa.
     *
     * @param  list<string>  $columnKeys
     * @return array<string, array{
     *     column_key: string,
     *     label: string,
     *     ranges: list<array{label: string, min: int, max: int, fee: float}>,
     *     defects: list<string>
     * }>
     */
    public static function coverages(PlanGenerator $plan, array $columnKeys): array
    {
        $columns = $plan->columns()->get(['id', 'plan_generator_id', 'column_key', 'header_label', 'sort_order']);
        $rateRows = $plan->rateRows()->with('cells')->get();

        $coverages = [];

        foreach ($columns as $column) {
            $columnKey = (string) $column->column_key;

            if (! in_array($columnKey, $columnKeys, true)) {
                continue;
            }

            $label = trim((string) $column->header_label);
            $label = $label === '' ? 'Cobertura sin nombre' : $label;
            $ranges = [];
            $defects = [];

            foreach ($rateRows as $rateRow) {
                $cell = $rateRow->cells->firstWhere('plan_generator_column_id', $column->getKey());
                $fee = PlanGeneratorGroupTotalCalculator::parseAmount($cell?->rate_amount);

                if ($fee <= 0) {
                    continue;
                }

                $rangeLabel = trim((string) $rateRow->age_range_label);
                [$min, $max] = PlanGeneratorCatalogPublisher::ageRangeBounds($rangeLabel);

                if ($rangeLabel === '' || $min === null || $max === null || $min > $max) {
                    $defects[] = 'el rango «'.($rangeLabel === '' ? 'sin nombre' : $rangeLabel).'» de '.$label
                        .' no indica una edad mínima y una máxima válidas (use el formato «0 a 45 años»).';

                    continue;
                }

                $ranges[] = ['label' => $rangeLabel, 'min' => $min, 'max' => $max, 'fee' => round($fee, 2)];
            }

            usort($ranges, static fn (array $a, array $b): int => $a['min'] <=> $b['min']);

            for ($i = 1, $count = count($ranges); $i < $count; $i++) {
                if ($ranges[$i]['min'] <= $ranges[$i - 1]['max']) {
                    $defects[] = 'los rangos «'.$ranges[$i - 1]['label'].'» y «'.$ranges[$i]['label'].'» de '.$label
                        .' se solapan: una misma edad tendría dos tarifas.';
                }
            }

            if ($ranges === [] && $defects === []) {
                $defects[] = $label.' no tiene ningún rango de edad con tarifa cargada.';
            }

            $coverages[$columnKey] = [
                'column_key' => $columnKey,
                'label' => $label,
                'ranges' => $ranges,
                'defects' => $defects,
            ];
        }

        return $coverages;
    }

    /**
     * Edad del padrón como entero, o null si no es un número de edad válido.
     */
    public static function parseAge(mixed $age): ?int
    {
        $age = trim((string) $age);

        if (preg_match('/^\d{1,3}$/', $age) !== 1) {
            return null;
        }

        $value = (int) $age;

        return $value <= 120 ? $value : null;
    }

    /**
     * Rango tarifado de la cobertura que contiene la edad, o null si ninguno.
     *
     * @param  array{ranges: list<array{label: string, min: int, max: int, fee: float}>}  $coverage
     * @return array{label: string, min: int, max: int, fee: float}|null
     */
    public static function rangeForAge(array $coverage, ?int $age): ?array
    {
        if ($age === null) {
            return null;
        }

        foreach ($coverage['ranges'] as $range) {
            if ($age >= $range['min'] && $age <= $range['max']) {
                return $range;
            }
        }

        return null;
    }

    /**
     * «0 a 45 años · 46 a 75 años», para que el analista vea qué edades admite.
     *
     * @param  array{ranges: list<array{label: string}>}  $coverage
     */
    public static function rangesLine(array $coverage): string
    {
        $labels = array_column($coverage['ranges'], 'label');

        return $labels === [] ? 'sin rangos tarifados' : implode(' · ', $labels);
    }

    /**
     * Estado completo del padrón frente a las coberturas elegidas.
     *
     * @return array{
     *     column_keys: list<string>,
     *     coverages: array<string, array<string, mixed>>,
     *     total: int,
     *     unassigned: int,
     *     invalid_age: list<string>,
     *     unplaceable: list<array{name: string, age: int}>,
     *     misassigned: list<array{name: string, reason: string}>,
     *     by_coverage: array<string, array{persons: int, annual: float, ranges: array<string, array{persons: int, fee: float}>}>,
     *     placements: array<int, array{column_key: string, range_label: string, fee: float}>,
     *     plan_defects: list<string>,
     *     signature: string
     * }
     */
    public static function report(PlanGenerator $plan): array
    {
        $columnKeys = PlanGeneratorPreAffiliationSession::selectedColumnKeys();
        $cacheKey = $plan->getKey().'|'.implode(',', $columnKeys);

        if (isset(self::$reports[$cacheKey])) {
            return self::$reports[$cacheKey];
        }

        $coverages = self::coverages($plan, $columnKeys);
        $planDefects = [];

        foreach ($columnKeys as $columnKey) {
            if (! isset($coverages[$columnKey])) {
                $planDefects[] = 'una de las coberturas elegidas ya no existe en la matriz del plan.';
            }
        }

        foreach ($coverages as $coverage) {
            array_push($planDefects, ...$coverage['defects']);
        }

        $byCoverage = [];

        foreach ($coverages as $columnKey => $coverage) {
            $byCoverage[$columnKey] = ['persons' => 0, 'annual' => 0.0, 'ranges' => []];
        }

        $people = PlanGeneratorPopulation::query()
            ->where('plan_generator_id', $plan->getKey())
            ->orderBy('id')
            ->toBase()
            ->get(['id', 'first_name', 'last_name', 'age', 'column_key']);

        $unassigned = 0;
        $invalidAge = [];
        $unplaceable = [];
        $misassigned = [];
        $placements = [];
        $signature = [implode(',', $columnKeys)];

        foreach ($people as $person) {
            $name = trim($person->last_name.' '.$person->first_name);
            $age = self::parseAge($person->age);
            $columnKey = $person->column_key !== null ? (string) $person->column_key : null;

            if ($age === null) {
                $invalidAge[] = $name;
            } elseif (! self::fitsAnyCoverage($coverages, $age)) {
                $unplaceable[] = ['name' => $name, 'age' => $age];
            }

            if ($columnKey === null) {
                $unassigned++;
                $signature[] = $person->id.':-';

                continue;
            }

            $coverage = $coverages[$columnKey] ?? null;

            if ($coverage === null) {
                $misassigned[] = ['name' => $name, 'reason' => 'su cobertura ya no forma parte de esta pre-afiliación'];

                continue;
            }

            $range = self::rangeForAge($coverage, $age);

            if ($range === null) {
                if ($age !== null) {
                    $misassigned[] = [
                        'name' => $name,
                        'reason' => 'tiene '.$age.' años y '.$coverage['label'].' cubre '.self::rangesLine($coverage),
                    ];
                }

                continue;
            }

            $byCoverage[$columnKey]['persons']++;
            $byCoverage[$columnKey]['annual'] += $range['fee'];
            $byCoverage[$columnKey]['ranges'][$range['label']] ??= ['persons' => 0, 'fee' => $range['fee']];
            $byCoverage[$columnKey]['ranges'][$range['label']]['persons']++;

            $placements[(int) $person->id] = [
                'column_key' => $columnKey,
                'range_label' => $range['label'],
                'fee' => $range['fee'],
            ];

            $signature[] = $person->id.':'.$columnKey.':'.$range['label'].':'.number_format($range['fee'], 2, '.', '');
        }

        foreach ($byCoverage as $columnKey => $amounts) {
            $byCoverage[$columnKey]['annual'] = round($amounts['annual'], 2);
        }

        return self::$reports[$cacheKey] = [
            'column_keys' => $columnKeys,
            'coverages' => $coverages,
            'total' => $people->count(),
            'unassigned' => $unassigned,
            'invalid_age' => $invalidAge,
            'unplaceable' => $unplaceable,
            'misassigned' => $misassigned,
            'by_coverage' => $byCoverage,
            'placements' => $placements,
            'plan_defects' => array_values(array_unique($planDefects)),
            'signature' => sha1(implode('|', $signature)),
        ];
    }

    /**
     * Falla de armado del plan que impide ubicar al padrón, o null si no hay.
     *
     * Se distingue del resto de bloqueos porque no se arregla en esta pantalla:
     * hay que corregir los rangos de edad o las tarifas del plan generado.
     *
     * @param  array<string, mixed>  $report
     */
    public static function planFailure(array $report): ?string
    {
        $defects = $report['plan_defects'];

        if ($defects !== []) {
            $extra = count($defects) > 1 ? ' (y '.(count($defects) - 1).' problema(s) más)' : '';

            return self::PLAN_FAILURE_PREFIX.': '.self::capitalize($defects[0]).$extra
                .' Corrija el plan generado y vuelva a «Aprobar cotización».';
        }

        $unplaceable = $report['unplaceable'];

        if ($unplaceable !== []) {
            $examples = array_map(
                static fn (array $person): string => $person['name'].' ('.$person['age'].' años)',
                array_slice($unplaceable, 0, 3),
            );

            $ranges = array_map(
                static fn (array $coverage): string => $coverage['label'].': '.self::rangesLine($coverage),
                $report['coverages'],
            );

            return self::PLAN_FAILURE_PREFIX.': '.count($unplaceable).' persona(s) del padrón tienen una edad que no cabe en ningún rango de las coberturas elegidas — '
                .implode(', ', $examples).(count($unplaceable) > 3 ? ', …' : '').'. '
                .'Rangos tarifados: '.implode(' / ', $ranges).'. Corrija los rangos de edad del plan generado.';
        }

        return null;
    }

    /**
     * Motivo por el que el padrón todavía no se puede afiliar, o null si cada
     * persona está en una cobertura que le corresponde.
     *
     * @param  array<string, mixed>  $report
     */
    public static function blockedReason(array $report): ?string
    {
        if (($failure = self::planFailure($report)) !== null) {
            return $failure;
        }

        if ($report['invalid_age'] !== []) {
            return count($report['invalid_age']).' persona(s) del padrón no tienen una edad válida ('
                .self::namesLine($report['invalid_age']).'). Corrija el archivo y vuelva a importar la población.';
        }

        if ($report['misassigned'] !== []) {
            $first = $report['misassigned'][0];

            return count($report['misassigned']).' persona(s) están en una cobertura que ya no les corresponde ('
                .$first['name'].': '.$first['reason'].(count($report['misassigned']) > 1 ? '; …' : '').'). '
                .'Reasígnelas antes de continuar.';
        }

        if ($report['unassigned'] > 0) {
            return 'Faltan '.$report['unassigned'].' persona(s) por ubicar en una cobertura. '
                .'Selecciónelas en la tabla y use «Asignar a cobertura».';
        }

        return null;
    }

    /**
     * Opciones del selector de cobertura: «PLAN IDEAL 3K · 0 a 45 años · 46 a 75 años».
     *
     * Con `$age` se limita a las coberturas que admiten esa edad, para la
     * acción por fila. La validación real igual corre en `assign()`.
     *
     * @return array<string, string>
     */
    public static function coverageOptions(PlanGenerator $plan, ?int $age = null, bool $filterByAge = false): array
    {
        $options = [];

        foreach (self::report($plan)['coverages'] as $columnKey => $coverage) {
            if ($coverage['defects'] !== []) {
                continue;
            }

            if ($filterByAge && self::rangeForAge($coverage, $age) === null) {
                continue;
            }

            $options[$columnKey] = $coverage['label'].' · '.self::rangesLine($coverage);
        }

        return $options;
    }

    /**
     * Ubica a las personas indicadas en la cobertura. Solo se asigna a quien
     * tenga una edad que caiga en un rango tarifado de esa cobertura; el resto
     * vuelve en `rejected` con el motivo, y no se toca.
     *
     * @param  array<int, int|string>  $populationIds
     * @return array{assigned: int, rejected: list<array{name: string, reason: string}>}
     *
     * @throws InvalidArgumentException si la cobertura no es de esta pre-afiliación o está mal armada
     */
    public static function assign(PlanGenerator $plan, array $populationIds, string $columnKey, ?string $assignedBy): array
    {
        $columnKeys = PlanGeneratorPreAffiliationSession::selectedColumnKeys();

        if (! in_array($columnKey, $columnKeys, true)) {
            throw new InvalidArgumentException('La cobertura elegida no forma parte de esta pre-afiliación.');
        }

        $coverage = self::coverages($plan, $columnKeys)[$columnKey] ?? null;

        if ($coverage === null) {
            throw new InvalidArgumentException(self::PLAN_FAILURE_PREFIX.': la cobertura elegida ya no existe en la matriz del plan.');
        }

        if ($coverage['defects'] !== []) {
            throw new InvalidArgumentException(self::PLAN_FAILURE_PREFIX.': '.self::capitalize($coverage['defects'][0]));
        }

        $ids = self::normalizeIds($populationIds);

        if ($ids === []) {
            return ['assigned' => 0, 'rejected' => []];
        }

        $result = DB::transaction(function () use ($plan, $ids, $columnKey, $coverage, $assignedBy): array {
            $people = PlanGeneratorPopulation::query()
                ->where('plan_generator_id', $plan->getKey())
                ->whereKey($ids)
                ->lockForUpdate()
                ->get(['id', 'first_name', 'last_name', 'age', 'column_key']);

            $accepted = [];
            $rejected = [];

            foreach ($people as $person) {
                $name = trim($person->last_name.' '.$person->first_name);
                $age = self::parseAge($person->age);

                if ($age === null) {
                    $rejected[] = ['name' => $name, 'reason' => 'no tiene una edad válida en el padrón'];

                    continue;
                }

                if (self::rangeForAge($coverage, $age) === null) {
                    $rejected[] = [
                        'name' => $name,
                        'reason' => 'tiene '.$age.' años y '.$coverage['label'].' solo cubre '.self::rangesLine($coverage),
                    ];

                    continue;
                }

                $accepted[] = $person->getKey();
            }

            $missing = count($ids) - $people->count();

            if ($missing > 0) {
                $rejected[] = ['name' => $missing.' registro(s)', 'reason' => 'ya no están en el padrón de este plan'];
            }

            if ($accepted !== []) {
                PlanGeneratorPopulation::query()
                    ->where('plan_generator_id', $plan->getKey())
                    ->whereKey($accepted)
                    ->update([
                        'column_key' => $columnKey,
                        'coverage_assigned_by' => $assignedBy,
                        'coverage_assigned_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            return ['assigned' => count($accepted), 'rejected' => $rejected, 'accepted_ids' => $accepted];
        });

        self::flush();

        SecurityAudit::log('AUDIT_BUSINESS_PLAN_GENERATOR_POPULATION_COVERAGE_ASSIGNED', 'business.plan-generators.population.assign-coverage', [
            'plan_generator_id' => $plan->getKey(),
            'column_key' => $columnKey,
            'coverage_label' => $coverage['label'],
            'assigned_ids' => $result['accepted_ids'],
            'rejected_count' => count($result['rejected']),
        ]);

        return ['assigned' => $result['assigned'], 'rejected' => $result['rejected']];
    }

    /**
     * Devuelve a las personas indicadas a «Sin asignar».
     *
     * @param  array<int, int|string>  $populationIds
     */
    public static function unassign(PlanGenerator $plan, array $populationIds): int
    {
        $ids = self::normalizeIds($populationIds);

        if ($ids === []) {
            return 0;
        }

        $cleared = PlanGeneratorPopulation::query()
            ->where('plan_generator_id', $plan->getKey())
            ->whereKey($ids)
            ->whereNotNull('column_key')
            ->update([
                'column_key' => null,
                'coverage_assigned_by' => null,
                'coverage_assigned_at' => null,
                'updated_at' => now(),
            ]);

        self::flush();

        SecurityAudit::log('AUDIT_BUSINESS_PLAN_GENERATOR_POPULATION_COVERAGE_CLEARED', 'business.plan-generators.population.clear-coverage', [
            'plan_generator_id' => $plan->getKey(),
            'population_ids' => $ids,
            'cleared' => $cleared,
        ]);

        return $cleared;
    }

    /**
     * Lo que se escribe al crear la afiliación, armado desde la base y no desde
     * la sesión: una fila de «Plan(es) Afiliado(s)» por cobertura y rango con la
     * población real, y plan, cobertura y tarifa de cada afiliado.
     *
     * @param  array{plan: \App\Models\Plan, coverage_ids: array<string, int>, age_range_ids: array<string, int>}  $catalog
     * @return array{
     *     affiliates: array<int, array{plan_id: int, coverage_id: int, fee: float}>,
     *     plan_rows: list<array{plan_id: int, coverage_id: int, age_range_id: int, total_persons: int, fee: float, subtotal_anual: float, subtotal_biannual: float, subtotal_quarterly: float, subtotal_monthly: float}>,
     *     total_persons: int,
     *     annual: float,
     *     signature: string
     * }
     *
     * @throws RuntimeException si el padrón no está listo o el catálogo no tiene la cobertura o el rango
     */
    public static function affiliationPlacements(PlanGenerator $plan, array $catalog): array
    {
        self::flush();
        $report = self::report($plan);

        if (($reason = self::blockedReason($report)) !== null) {
            throw new RuntimeException($reason);
        }

        if ($report['total'] === 0 || count($report['placements']) !== $report['total']) {
            throw new RuntimeException('El padrón no tiene a todas sus personas ubicadas en una cobertura.');
        }

        $planId = (int) $catalog['plan']->getKey();
        $planRows = [];

        foreach ($report['by_coverage'] as $columnKey => $amounts) {
            if ($amounts['persons'] === 0) {
                continue;
            }

            $coverageId = $catalog['coverage_ids'][$columnKey] ?? null;

            if ($coverageId === null) {
                throw new RuntimeException(self::PLAN_FAILURE_PREFIX.': la cobertura '
                    .$report['coverages'][$columnKey]['label'].' no quedó publicada en el catálogo de planes.');
            }

            foreach ($amounts['ranges'] as $rangeLabel => $range) {
                $ageRangeId = $catalog['age_range_ids'][$rangeLabel] ?? null;

                if ($ageRangeId === null) {
                    throw new RuntimeException(self::PLAN_FAILURE_PREFIX.': el rango «'.$rangeLabel
                        .'» no quedó publicado en el catálogo de planes.');
                }

                $annual = round($range['fee'] * $range['persons'], 2);

                $planRows[] = [
                    'plan_id' => $planId,
                    'coverage_id' => (int) $coverageId,
                    'age_range_id' => (int) $ageRangeId,
                    'total_persons' => $range['persons'],
                    'fee' => $range['fee'],
                    'subtotal_anual' => $annual,
                    'subtotal_biannual' => round($annual / 2, 2),
                    'subtotal_quarterly' => round($annual / 4, 2),
                    'subtotal_monthly' => round($annual / 12, 2),
                ];
            }
        }

        $affiliates = [];

        foreach ($report['placements'] as $populationId => $placement) {
            $affiliates[$populationId] = [
                'plan_id' => $planId,
                'coverage_id' => (int) $catalog['coverage_ids'][$placement['column_key']],
                'fee' => $placement['fee'],
            ];
        }

        return [
            'affiliates' => $affiliates,
            'plan_rows' => $planRows,
            'total_persons' => count($affiliates),
            'annual' => round(array_sum(array_column($report['by_coverage'], 'annual')), 2),
            'signature' => $report['signature'],
        ];
    }

    /**
     * @param  array<string, array{ranges: list<array{label: string, min: int, max: int, fee: float}>}>  $coverages
     */
    private static function fitsAnyCoverage(array $coverages, int $age): bool
    {
        foreach ($coverages as $coverage) {
            if (self::rangeForAge($coverage, $age) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return list<int>
     */
    private static function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $ids),
            static fn (int $id): bool => $id > 0,
        )));
    }

    /**
     * @param  list<string>  $names
     */
    private static function namesLine(array $names): string
    {
        return implode(', ', array_slice($names, 0, 3)).(count($names) > 3 ? ', …' : '');
    }

    private static function capitalize(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1, 'UTF-8'), 'UTF-8').mb_substr($text, 1, null, 'UTF-8');
    }
}
