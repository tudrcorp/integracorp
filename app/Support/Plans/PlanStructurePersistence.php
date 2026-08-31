<?php

declare(strict_types=1);

namespace App\Support\Plans;

use App\Enums\PlanPricingMode;
use App\Models\AgeRange;
use App\Models\Benefit;
use App\Models\BenefitCoverage;
use App\Models\BenefitPlan;
use App\Models\Coverage;
use App\Models\Fee;
use App\Models\Plan;
use App\Models\WhiteCompanyFee;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Escribe la estructura de un plan armado con el asistente de Negocios.
 *
 * Dos modos, según `plans.pricing_mode`:
 *
 *   COBERTURAS — coberturas propias del plan; cada beneficio declara un costo
 *                límite por cobertura (`benefit_coverages.limit`, NULL cuando no
 *                tiene límite) y cada rango de edad una tarifa por cobertura
 *                (`fees` con `coverage_id`).
 *   PAQUETE    — sin coberturas; los beneficios van como un todo y cada rango de
 *                edad lleva una sola tarifa (`fees` con `coverage_id` nulo).
 *
 * Todo corre dentro de una transacción: un plan a medio escribir dejaría
 * cotizaciones y afiliaciones tomando precios que no existen.
 *
 * Sobre los borrados: nada que pueda estar referenciado se elimina a ciegas.
 * Una cobertura que sale del plan se desvincula (`plan_id` a NULL) en vez de
 * borrarse, igual que hacía PlanCreationPersistence, y una tarifa con precio
 * negociado por una empresa aliada nunca se borra —se marca INACTIVO—, porque
 * `white_company_fees.fee_id` la referencia y a su vez
 * `affiliations.white_company_fee_id` apunta a esa negociación.
 */
final class PlanStructurePersistence
{
    /**
     * @param  array<string, mixed>  $formData
     */
    public static function persist(Plan $plan, array $formData): void
    {
        DB::transaction(static function () use ($plan, $formData): void {
            $mode = PlanPricingMode::fromStored($formData['pricing_mode'] ?? null) ?? PlanPricingMode::Coberturas;

            if ($mode === PlanPricingMode::Paquete) {
                self::persistBenefitPackage($plan, $formData);

                return;
            }

            self::persistCoverageMatrix($plan, $formData);
        });
    }

    /**
     * Reconstruye el estado del asistente a partir de lo persistido, para poder
     * reeditar un plan sin perder la correspondencia entre celdas y coberturas.
     *
     * @return array<string, mixed>
     */
    public static function hydrate(Plan $plan): array
    {
        $mode = $plan->pricingMode();

        $base = [
            'pricing_mode' => $mode->value,
            'structure_version' => (int) ($plan->structure_version ?? Plan::STRUCTURE_VERSION_WIZARD),
        ];

        $benefitIds = BenefitPlan::query()
            ->where('plan_id', $plan->id)
            ->pluck('benefit_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($mode === PlanPricingMode::Paquete) {
            return $base + [
                'package_benefit_ids' => $benefitIds,
                'package_age_ranges' => self::hydratePackageAgeRanges($plan),
            ];
        }

        $coverages = Coverage::query()
            ->where('plan_id', $plan->id)
            ->orderBy('price')
            ->get();

        $columns = $coverages
            ->map(static fn (Coverage $coverage): array => [
                'key' => PlanStructureMatrix::keyForPersistedCoverage((int) $coverage->id),
                'price' => (float) $coverage->price,
                'id' => (int) $coverage->id,
            ])
            ->all();

        return $base + [
            'plan_coverages' => array_map(
                static fn (array $column): array => [
                    'id' => $column['id'],
                    'coverage_key' => $column['key'],
                    'price' => $column['price'],
                ],
                $columns,
            ),
            'plan_benefits' => self::hydrateBenefits($plan, $benefitIds, $columns),
            'plan_age_ranges' => self::hydrateCoverageAgeRanges($plan, $columns),
        ];
    }

    /**
     * @param  list<int>  $benefitIds
     * @param  list<array{key: string, price: float, id: int}>  $columns
     * @return list<array<string, mixed>>
     */
    private static function hydrateBenefits(Plan $plan, array $benefitIds, array $columns): array
    {
        $limits = BenefitCoverage::query()
            ->where('plan_id', $plan->id)
            ->get()
            ->keyBy(static fn (BenefitCoverage $row): string => $row->benefit_id.'-'.$row->coverage_id);

        $rows = [];

        foreach ($benefitIds as $benefitId) {
            $cells = [];

            foreach ($columns as $column) {
                $record = $limits->get($benefitId.'-'.$column['id']);

                $cells[] = [
                    'coverage_key' => $column['key'],
                    'coverage_price' => $column['price'],
                    'limit' => $record?->limit,
                ];
            }

            $rows[] = [
                'benefit_id' => $benefitId,
                'limits' => $cells,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{key: string, price: float, id: int}>  $columns
     * @return list<array<string, mixed>>
     */
    private static function hydrateCoverageAgeRanges(Plan $plan, array $columns): array
    {
        $fees = Fee::query()
            ->where('plan_id', $plan->id)
            ->whereNotNull('coverage_id')
            ->get()
            ->keyBy(static fn (Fee $fee): string => $fee->age_range_id.'-'.$fee->coverage_id);

        $rows = [];

        foreach (self::activeAgeRanges($plan) as $ageRange) {
            $cells = [];

            foreach ($columns as $column) {
                $fee = $fees->get($ageRange->id.'-'.$column['id']);

                $cells[] = [
                    'coverage_key' => $column['key'],
                    'coverage_price' => $column['price'],
                    'rate' => $fee?->price,
                ];
            }

            $rows[] = [
                'id' => (int) $ageRange->id,
                'range' => (string) $ageRange->range,
                'age_init' => $ageRange->age_init,
                'age_end' => $ageRange->age_end,
                'rates' => $cells,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function hydratePackageAgeRanges(Plan $plan): array
    {
        $fees = Fee::query()
            ->where('plan_id', $plan->id)
            ->whereNull('coverage_id')
            ->get()
            ->keyBy(static fn (Fee $fee): int => (int) $fee->age_range_id);

        $rows = [];

        foreach (self::activeAgeRanges($plan) as $ageRange) {
            $rows[] = [
                'id' => (int) $ageRange->id,
                'range' => (string) $ageRange->range,
                'age_init' => $ageRange->age_init,
                'age_end' => $ageRange->age_end,
                'rate' => $fees->get((int) $ageRange->id)?->price,
            ];
        }

        return $rows;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, AgeRange>
     */
    private static function activeAgeRanges(Plan $plan)
    {
        return AgeRange::query()
            ->where('plan_id', $plan->id)
            ->where(static fn ($query) => $query->where('status', 'ACTIVO')->orWhereNull('status'))
            ->orderBy('age_init')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    private static function persistCoverageMatrix(Plan $plan, array $formData): void
    {
        $coverageIdsByKey = self::syncCoverages($plan, $formData['plan_coverages'] ?? []);

        $benefitRows = self::rows($formData['plan_benefits'] ?? []);
        $benefitIds = [];

        foreach ($benefitRows as $row) {
            if (filled($row['benefit_id'] ?? null)) {
                $benefitIds[] = (int) $row['benefit_id'];
            }
        }

        self::syncBenefitPlans($plan, $benefitIds);
        self::syncBenefitCoverages($plan, $benefitRows, $coverageIdsByKey);

        $ageRangeIds = self::syncAgeRanges($plan, $formData['plan_age_ranges'] ?? []);
        self::syncCoverageFees($plan, $formData['plan_age_ranges'] ?? [], $ageRangeIds, $coverageIdsByKey);
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    private static function persistBenefitPackage(Plan $plan, array $formData): void
    {
        // Un paquete no tiene coberturas: se sueltan las que hubiera y se limpia
        // la matriz de límites, que deja de tener sentido.
        self::syncCoverages($plan, []);
        BenefitCoverage::query()->where('plan_id', $plan->id)->delete();

        $benefitIds = array_map(
            static fn (mixed $id): int => (int) $id,
            array_filter((array) ($formData['package_benefit_ids'] ?? []), static fn (mixed $id): bool => filled($id)),
        );

        if ($benefitIds === []) {
            foreach ((array) ($formData['package_benefits'] ?? []) as $row) {
                if (is_array($row) && filled($row['benefit_id'] ?? null)) {
                    $benefitIds[] = (int) $row['benefit_id'];
                }
            }
        }

        self::syncBenefitPlans($plan, $benefitIds);

        $ageRangeIds = self::syncAgeRanges($plan, $formData['package_age_ranges'] ?? []);
        self::syncFlatFees($plan, $formData['package_age_ranges'] ?? [], $ageRangeIds);
    }

    /**
     * Crea o actualiza las coberturas del plan y devuelve el mapa
     * clave del formulario -> id persistido, que es lo que después permite
     * escribir límites y tarifas de la matriz.
     *
     * @return array<string, int>
     */
    private static function syncCoverages(Plan $plan, mixed $coverageRows): array
    {
        $idsByKey = [];
        $keptIds = [];

        foreach (self::rows($coverageRows) as $index => $row) {
            $price = $row['price'] ?? null;

            if (! is_numeric($price)) {
                continue;
            }

            $coverage = filled($row['id'] ?? null)
                ? Coverage::query()->find($row['id'])
                : null;

            if ($coverage === null) {
                $coverage = new Coverage;
                $coverage->created_by = Auth::user()?->name ?? 'sistema';
            }

            $coverage->plan_id = $plan->id;
            $coverage->price = $price;
            $coverage->status = 'ACTIVO';
            $coverage->save();

            $key = self::coverageKeyFromRow($row, $index, (int) $coverage->id);

            $idsByKey[$key] = (int) $coverage->id;
            $keptIds[] = (int) $coverage->id;
        }

        self::detachRemovedCoverages($plan, $keptIds);

        return $idsByKey;
    }

    /**
     * @param  list<int>  $keptIds
     */
    private static function detachRemovedCoverages(Plan $plan, array $keptIds): void
    {
        $removed = Coverage::query()
            ->where('plan_id', $plan->id)
            ->when($keptIds !== [], static fn ($query) => $query->whereNotIn('id', $keptIds))
            ->get();

        foreach ($removed as $coverage) {
            self::retireFees(
                Fee::query()
                    ->where('plan_id', $plan->id)
                    ->where('coverage_id', $coverage->id),
            );

            BenefitCoverage::query()
                ->where('plan_id', $plan->id)
                ->where('coverage_id', $coverage->id)
                ->delete();

            $coverage->plan_id = null;
            $coverage->save();
        }
    }

    /**
     * @param  list<int>  $benefitIds
     */
    private static function syncBenefitPlans(Plan $plan, array $benefitIds): void
    {
        $benefitIds = array_values(array_unique($benefitIds));

        BenefitPlan::query()
            ->where('plan_id', $plan->id)
            ->when($benefitIds !== [], static fn ($query) => $query->whereNotIn('benefit_id', $benefitIds))
            ->delete();

        foreach ($benefitIds as $benefitId) {
            BenefitPlan::query()->updateOrCreate(
                [
                    'plan_id' => $plan->id,
                    'benefit_id' => $benefitId,
                ],
                [
                    'description' => (string) (Benefit::query()->find($benefitId)?->description ?? ''),
                ],
            );
        }
    }

    /**
     * Matriz de costos límite. Una celda vacía se guarda como NULL, que es
     * "este beneficio no tiene límite en esta cobertura" y no lo mismo que 0.
     *
     * @param  array<array-key, array<string, mixed>>  $benefitRows
     * @param  array<string, int>  $coverageIdsByKey
     */
    private static function syncBenefitCoverages(Plan $plan, array $benefitRows, array $coverageIdsByKey): void
    {
        $keptIds = [];

        foreach ($benefitRows as $benefitRow) {
            $benefitId = $benefitRow['benefit_id'] ?? null;

            if (blank($benefitId)) {
                continue;
            }

            $benefit = Benefit::query()->find($benefitId);

            if ($benefit === null) {
                continue;
            }

            foreach (self::rows($benefitRow['limits'] ?? []) as $cell) {
                $coverageId = $coverageIdsByKey[$cell['coverage_key'] ?? ''] ?? null;

                if ($coverageId === null) {
                    continue;
                }

                $limit = $cell['limit'] ?? null;

                $record = BenefitCoverage::query()->updateOrCreate(
                    [
                        'plan_id' => $plan->id,
                        'benefit_id' => (int) $benefit->id,
                        'coverage_id' => $coverageId,
                    ],
                    [
                        'limit' => is_numeric($limit) ? $limit : null,
                        'benefit_description' => (string) $benefit->description,
                        'coverage_price' => (string) (Coverage::query()->find($coverageId)?->price ?? '0'),
                        'price' => '1',
                    ],
                );

                $keptIds[] = (int) $record->id;
            }
        }

        BenefitCoverage::query()
            ->where('plan_id', $plan->id)
            ->when($keptIds !== [], static fn ($query) => $query->whereNotIn('id', $keptIds))
            ->delete();
    }

    /**
     * Los rangos de edad son propios del plan: el asistente siempre los crea,
     * nunca reutiliza los del catálogo, para que ajustar un rango no altere el
     * precio de otro plan.
     *
     * @return array<string, int>
     */
    private static function syncAgeRanges(Plan $plan, mixed $ageRangeRows): array
    {
        $idsByKey = [];
        $keptIds = [];

        foreach (self::rows($ageRangeRows) as $index => $row) {
            $label = trim((string) ($row['range'] ?? ''));

            if ($label === '') {
                continue;
            }

            $ageRange = filled($row['id'] ?? null)
                ? AgeRange::query()->find($row['id'])
                : null;

            if ($ageRange === null) {
                $ageRange = new AgeRange;
                $ageRange->created_by = Auth::user()?->name ?? 'sistema';
            }

            $ageRange->plan_id = $plan->id;
            $ageRange->coverage_id = null;
            $ageRange->range = $label;
            $ageRange->age_init = filled($row['age_init'] ?? null) ? (int) $row['age_init'] : null;
            $ageRange->age_end = filled($row['age_end'] ?? null) ? (int) $row['age_end'] : null;
            $ageRange->status = 'ACTIVO';
            $ageRange->save();

            $idsByKey[self::rowKey($index)] = (int) $ageRange->id;
            $keptIds[] = (int) $ageRange->id;
        }

        self::retireRemovedAgeRanges($plan, $keptIds);

        return $idsByKey;
    }

    /**
     * Un rango que sale del plan no se borra: puede estar referenciado por
     * afiliaciones y renovaciones históricas. Se marca INACTIVO y se retiran
     * sus tarifas, que es lo que deja de cobrarse.
     *
     * @param  list<int>  $keptIds
     */
    private static function retireRemovedAgeRanges(Plan $plan, array $keptIds): void
    {
        $removed = AgeRange::query()
            ->where('plan_id', $plan->id)
            ->when($keptIds !== [], static fn ($query) => $query->whereNotIn('id', $keptIds))
            ->get();

        foreach ($removed as $ageRange) {
            self::retireFees(
                Fee::query()
                    ->where('plan_id', $plan->id)
                    ->where('age_range_id', $ageRange->id),
            );

            $ageRange->status = 'INACTIVO';
            $ageRange->save();
        }
    }

    /**
     * Tarifas del modo COBERTURAS: una por (rango de edad, cobertura).
     *
     * @param  array<string, int>  $ageRangeIds
     * @param  array<string, int>  $coverageIdsByKey
     */
    private static function syncCoverageFees(Plan $plan, mixed $ageRangeRows, array $ageRangeIds, array $coverageIdsByKey): void
    {
        $keptIds = [];

        foreach (self::rows($ageRangeRows) as $index => $row) {
            $ageRangeId = $ageRangeIds[self::rowKey($index)] ?? null;

            if ($ageRangeId === null) {
                continue;
            }

            foreach (self::rows($row['rates'] ?? []) as $cell) {
                $coverageId = $coverageIdsByKey[$cell['coverage_key'] ?? ''] ?? null;
                $rate = $cell['rate'] ?? null;

                if ($coverageId === null || ! is_numeric($rate)) {
                    continue;
                }

                $keptIds[] = self::writeFee($plan, $ageRangeId, $coverageId, (string) $rate);
            }
        }

        self::retireFees(
            Fee::query()
                ->where('plan_id', $plan->id)
                ->when($keptIds !== [], static fn ($query) => $query->whereNotIn('id', $keptIds)),
        );
    }

    /**
     * Tarifas del modo PAQUETE: una por rango de edad, sin cobertura.
     *
     * @param  array<string, int>  $ageRangeIds
     */
    private static function syncFlatFees(Plan $plan, mixed $ageRangeRows, array $ageRangeIds): void
    {
        $keptIds = [];

        foreach (self::rows($ageRangeRows) as $index => $row) {
            $ageRangeId = $ageRangeIds[self::rowKey($index)] ?? null;
            $rate = $row['rate'] ?? null;

            if ($ageRangeId === null || ! is_numeric($rate)) {
                continue;
            }

            $keptIds[] = self::writeFee($plan, $ageRangeId, null, (string) $rate);
        }

        self::retireFees(
            Fee::query()
                ->where('plan_id', $plan->id)
                ->when($keptIds !== [], static fn ($query) => $query->whereNotIn('id', $keptIds)),
        );
    }

    private static function writeFee(Plan $plan, int $ageRangeId, ?int $coverageId, string $rate): int
    {
        $query = Fee::query()
            ->where('plan_id', $plan->id)
            ->where('age_range_id', $ageRangeId);

        $query = $coverageId === null
            ? $query->whereNull('coverage_id')
            : $query->where('coverage_id', $coverageId);

        $fee = $query->first() ?? new Fee;

        if (! $fee->exists) {
            $fee->code = PlanFeeCodeGenerator::next();
            $fee->created_by = Auth::user()?->name ?? 'sistema';
        }

        $fee->plan_id = $plan->id;
        $fee->age_range_id = $ageRangeId;
        $fee->coverage_id = $coverageId;
        $fee->price = $rate;
        $fee->range = (string) (AgeRange::query()->find($ageRangeId)?->range ?? '');
        $fee->coverage = $coverageId === null
            ? null
            : (string) (Coverage::query()->find($coverageId)?->price ?? '');
        $fee->status = 'ACTIVO';
        $fee->save();

        return (int) $fee->id;
    }

    /**
     * Retira tarifas que salieron del plan. Las que tienen una negociación de
     * empresa aliada encima no se borran nunca: se marcan INACTIVO, porque
     * `white_company_fees.fee_id` las referencia y hay afiliaciones apuntando a
     * esa negociación.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Fee>  $query
     */
    private static function retireFees($query): void
    {
        $fees = $query->get();

        if ($fees->isEmpty()) {
            return;
        }

        $negotiatedFeeIds = WhiteCompanyFee::query()
            ->whereIn('fee_id', $fees->pluck('id')->all())
            ->pluck('fee_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        foreach ($fees as $fee) {
            if (in_array((int) $fee->id, $negotiatedFeeIds, true)) {
                $fee->status = 'INACTIVO';
                $fee->save();

                continue;
            }

            $fee->delete();
        }
    }

    /**
     * Clave de una fila de repetidor. Filament las indexa con un uuid y las
     * hidrataciones con enteros: se normaliza a string para que el rango de
     * edad y sus tarifas se emparejen por la misma clave en ambos casos.
     */
    private static function rowKey(mixed $index): string
    {
        return (string) $index;
    }

    /**
     * Los repetidores de Filament entregan sus filas indexadas por una clave
     * propia; se conserva esa clave porque es la que enlaza cada fila con su
     * entidad persistida durante toda la edición.
     *
     * @return array<array-key, array<string, mixed>>
     */
    private static function rows(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_filter($value, static fn (mixed $row): bool => is_array($row));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function coverageKeyFromRow(array $row, mixed $index, int $coverageId): string
    {
        $key = $row['coverage_key'] ?? null;

        if (is_string($key) && $key !== '') {
            return $key;
        }

        if (is_string($index) && $index !== '') {
            return $index;
        }

        return PlanStructureMatrix::keyForPersistedCoverage($coverageId);
    }
}
