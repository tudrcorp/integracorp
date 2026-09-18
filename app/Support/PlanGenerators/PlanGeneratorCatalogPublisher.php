<?php

declare(strict_types=1);

namespace App\Support\PlanGenerators;

use App\Enums\PlanPricingMode;
use App\Models\AgeRange;
use App\Models\Benefit;
use App\Models\Coverage;
use App\Models\Plan;
use App\Models\PlanGenerator;
use App\Models\PlanGeneratorColumn;
use App\Models\PlanGeneratorRateRow;
use App\Support\Plans\PlanCodeGenerator;
use App\Support\Plans\PlanStructurePersistence;
use Illuminate\Support\Facades\DB;

/**
 * Publica la matriz de un plan generado en el catálogo de planes.
 *
 * Sin esto, una afiliación nacida del generador quedaba con `plan_id`,
 * `coverage_id` y `age_range_id` en cero: las columnas Plan, Cobertura y Rango
 * de Edad de «Plan(es) Afiliado(s)» salían vacías porque el plan que el
 * analista armó en la cotización no existía en `plans`.
 *
 * Decisiones que conviene tener presentes:
 *
 *   - **Tipo DRESS-TAILOR**, que es el valor que escriben los formularios de
 *     plan del repo (`PlanForm`, `PlanWizardForm`) y el que llevan los demás
 *     planes a la medida. En la base conviven además `DRESS-TAYLOR` y
 *     `DRESS-TYLOR`; son grafías sueltas del mismo tipo y no se usan acá.
 *   - **Modo COBERTURAS** siempre: la matriz del generador tiene columnas y cada
 *     beneficio declara su tope por columna, que es exactamente ese modo.
 *   - **Un plan de catálogo por plan generado**, guardado en
 *     `plan_generators.catalog_plan_id`. Publicar de nuevo actualiza el mismo
 *     plan en vez de duplicarlo, y la correspondencia columna ↔ cobertura y
 *     rango ↔ `age_ranges` queda guardada para que republicar no desordene los
 *     precios.
 *   - La escritura la hace `PlanStructurePersistence`, el mismo código del
 *     asistente de planes de Negocios, para no tener dos verdades sobre cómo se
 *     retiran tarifas y se desvinculan coberturas.
 *
 * Una pérdida conocida: el catálogo no sabe decir «este beneficio no aplica en
 * esta cobertura» —no existe la fila y al reimportar vuelve como incluido sin
 * tope—. Se omiten las celdas no tildadas, que es lo más cercano.
 */
final class PlanGeneratorCatalogPublisher
{
    public const PLAN_TYPE = 'DRESS-TAILOR';

    private const DEFAULT_BUSINESS_UNIT_ID = 1;

    /**
     * Crea o actualiza el plan de catálogo del plan generado y devuelve las
     * referencias que necesitan las afiliaciones.
     *
     * @return array{
     *     plan: Plan,
     *     coverage_ids: array<string, int>,
     *     age_range_ids: array<string, int>
     * }
     */
    public static function publish(PlanGenerator $generator, ?string $createdBy = null): array
    {
        return DB::transaction(static function () use ($generator, $createdBy): array {
            $generator->loadMissing(['columns', 'rows.cells', 'rateRows.cells']);

            $matrix = PlanGeneratorPersistence::formStateFromModel($generator);
            $columns = PlanGeneratorMatrixState::normalizeColumns($matrix['columns']);
            $rows = (array) $matrix['rows'];
            $rateRows = array_values((array) $matrix['rate_rows']);

            $plan = self::resolvePlan($generator, $createdBy);

            $coverageIds = self::syncCoverageLinks($generator, $plan, $columns, $rows, $createdBy);
            $ageRangeIds = self::syncAgeRangeLinks($generator, $plan, $rateRows, $createdBy);

            PlanStructurePersistence::persist($plan, [
                'pricing_mode' => PlanPricingMode::Coberturas->value,
                'plan_coverages' => self::coverageRows($columns, $coverageIds, $rows),
                'plan_benefits' => self::benefitRows($columns, $rows, $createdBy),
                'plan_age_ranges' => self::ageRangeRows($columns, $rateRows, $ageRangeIds),
            ]);

            $generator->forceFill(['catalog_plan_id' => $plan->getKey()])->save();

            return [
                'plan' => $plan->refresh(),
                'coverage_ids' => $coverageIds,
                'age_range_ids' => $ageRangeIds,
            ];
        });
    }

    private static function resolvePlan(PlanGenerator $generator, ?string $createdBy): Plan
    {
        $plan = filled($generator->catalog_plan_id)
            ? Plan::query()->find($generator->catalog_plan_id)
            : null;

        if ($plan === null) {
            $plan = new Plan;
            $plan->code = self::nextFreePlanCode();
            $plan->business_unit_id = self::DEFAULT_BUSINESS_UNIT_ID;
            $plan->created_by = $createdBy ?? 'sistema';
        }

        $plan->description = mb_strtoupper(trim((string) $generator->name));
        $plan->type = self::PLAN_TYPE;
        $plan->status = 'ACTIVO';
        $plan->pricing_mode = PlanPricingMode::Coberturas->value;
        // Un plan a la medida no entra al cotizador general: nace de una
        // cotización ya emitida a un cliente concreto.
        $plan->is_quotable = false;
        $plan->quotable_in = null;
        $plan->structure_version = Plan::STRUCTURE_VERSION_WIZARD;
        $plan->save();

        return $plan;
    }

    /**
     * Código de plan libre.
     *
     * `PlanCodeGenerator::next()` es `max(id) + 1` y `plans.code` no tiene
     * índice único, así que dos publicaciones simultáneas pueden pedir el mismo
     * código. Acá se verifica y se desempata con un sufijo: este módulo crea
     * planes de forma automática al cerrar afiliaciones, mucho más seguido que
     * el asistente de planes, y un código repetido confunde al analista que
     * busca el plan por código.
     */
    private static function nextFreePlanCode(): string
    {
        $code = PlanCodeGenerator::next();

        if (! Plan::query()->where('code', $code)->exists()) {
            return $code;
        }

        for ($suffix = 2; $suffix <= 99; $suffix++) {
            $candidate = $code.'-'.$suffix;

            if (! Plan::query()->where('code', $candidate)->exists()) {
                return $candidate;
            }
        }

        return $code.'-'.now()->format('YmdHis');
    }

    /**
     * Cobertura de cada columna. Se crean acá —y no dentro de
     * `PlanStructurePersistence`— para conocer el id y poder guardarlo en la
     * columna: es lo que permite republicar sin duplicar.
     *
     * @param  list<array<string, mixed>>  $columns
     * @param  array<string, mixed>  $rows
     * @return array<string, int>
     */
    private static function syncCoverageLinks(
        PlanGenerator $generator,
        Plan $plan,
        array $columns,
        array $rows,
        ?string $createdBy,
    ): array {
        $stored = $generator->columns->keyBy('column_key');
        $ids = [];

        foreach ($columns as $column) {
            $columnKey = (string) $column['column_key'];
            /** @var PlanGeneratorColumn|null $storedColumn */
            $storedColumn = $stored->get($columnKey);

            $coverage = filled($storedColumn?->coverage_id)
                ? Coverage::query()->find($storedColumn->coverage_id)
                : null;

            if ($coverage === null) {
                $coverage = new Coverage;
                $coverage->created_by = $createdBy ?? 'sistema';
            }

            $coverage->plan_id = $plan->getKey();
            $coverage->price = self::coveragePriceForColumn($column, $rows);
            $coverage->status = 'ACTIVO';
            $coverage->save();

            $ids[$columnKey] = (int) $coverage->getKey();

            $storedColumn?->forceFill(['coverage_id' => $coverage->getKey()])->save();
        }

        return $ids;
    }

    /**
     * @param  list<array<string, mixed>>  $rateRows
     * @return array<string, int>
     */
    private static function syncAgeRangeLinks(
        PlanGenerator $generator,
        Plan $plan,
        array $rateRows,
        ?string $createdBy,
    ): array {
        $stored = $generator->rateRows->keyBy('age_range_label');
        $ids = [];

        foreach ($rateRows as $rateRow) {
            $label = trim((string) ($rateRow['age_range_label'] ?? ''));

            if ($label === '') {
                continue;
            }

            /** @var PlanGeneratorRateRow|null $storedRow */
            $storedRow = $stored->get($label);

            $ageRange = filled($storedRow?->age_range_id)
                ? AgeRange::query()->find($storedRow->age_range_id)
                : null;

            if ($ageRange === null) {
                $ageRange = new AgeRange;
                $ageRange->created_by = $createdBy ?? 'sistema';
            }

            [$init, $end] = self::ageRangeBounds($label);

            $ageRange->plan_id = $plan->getKey();
            $ageRange->coverage_id = null;
            $ageRange->range = $label;
            $ageRange->age_init = $init;
            $ageRange->age_end = $end;
            $ageRange->status = 'ACTIVO';
            $ageRange->save();

            $ids[$label] = (int) $ageRange->getKey();

            $storedRow?->forceFill(['age_range_id' => $ageRange->getKey()])->save();
        }

        return $ids;
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     * @param  array<string, int>  $coverageIds
     * @param  array<string, mixed>  $rows
     * @return list<array<string, mixed>>
     */
    private static function coverageRows(array $columns, array $coverageIds, array $rows): array
    {
        $coverageRows = [];

        foreach ($columns as $column) {
            $columnKey = (string) $column['column_key'];

            $coverageRows[] = [
                'id' => $coverageIds[$columnKey] ?? null,
                'coverage_key' => $columnKey,
                'price' => self::coveragePriceForColumn($column, $rows),
            ];
        }

        return $coverageRows;
    }

    /**
     * Beneficios del plan con su tope por cobertura.
     *
     * Solo se declaran las celdas tildadas: una celda sin tildar significa «no
     * incluido en esa cobertura», y en el catálogo eso se representa por
     * ausencia de fila en `benefit_coverages`.
     *
     * @param  list<array<string, mixed>>  $columns
     * @param  array<string, mixed>  $rows
     * @return list<array<string, mixed>>
     */
    private static function benefitRows(array $columns, array $rows, ?string $createdBy): array
    {
        $benefitRows = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = mb_strtoupper(trim((string) ($row['benefit_label'] ?? '')));

            if ($label === '') {
                continue;
            }

            $limits = [];

            foreach ($columns as $column) {
                $columnKey = (string) $column['column_key'];
                $cell = (array) ($row['cells'][$columnKey] ?? []);

                if (! (bool) ($cell['is_selected'] ?? false)) {
                    continue;
                }

                $amount = PlanGeneratorGroupTotalCalculator::parseAmount($cell['coverage_amount'] ?? null);

                $limits[] = [
                    'coverage_key' => $columnKey,
                    // Cero es «sin tope» en este módulo, igual que NULL: así lo
                    // interpreta PlanGeneratorPreviewBuilder al pintar la celda.
                    'limit' => $amount > 0 ? $amount : null,
                ];
            }

            if ($limits === []) {
                continue;
            }

            $benefitRows[] = [
                'benefit_id' => self::benefitIdFor($label, $createdBy),
                'limits' => $limits,
            ];
        }

        return $benefitRows;
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     * @param  list<array<string, mixed>>  $rateRows
     * @param  array<string, int>  $ageRangeIds
     * @return list<array<string, mixed>>
     */
    private static function ageRangeRows(array $columns, array $rateRows, array $ageRangeIds): array
    {
        $ranges = [];

        foreach ($rateRows as $rateRow) {
            $label = trim((string) ($rateRow['age_range_label'] ?? ''));

            if ($label === '' || ! isset($ageRangeIds[$label])) {
                continue;
            }

            [$init, $end] = self::ageRangeBounds($label);
            $rates = [];

            foreach ($columns as $column) {
                $columnKey = (string) $column['column_key'];
                $rate = PlanGeneratorGroupTotalCalculator::parseAmount(
                    data_get($rateRow, "cells.{$columnKey}.rate_amount"),
                );

                if ($rate <= 0) {
                    continue;
                }

                $rates[] = [
                    'coverage_key' => $columnKey,
                    'rate' => $rate,
                ];
            }

            $ranges[] = [
                'id' => $ageRangeIds[$label],
                'range' => $label,
                'age_init' => $init,
                'age_end' => $end,
                'rates' => $rates,
            ];
        }

        return $ranges;
    }

    private static function benefitIdFor(string $description, ?string $createdBy): int
    {
        $benefit = Benefit::query()->firstOrCreate(
            ['description' => $description],
            [
                'code' => 'TDEC-BN-'.str_pad((string) ((Benefit::max('id') ?? 0) + 1), 4, '0', STR_PAD_LEFT),
                'status' => 'ACTIVO',
                'created_by' => $createdBy ?? 'sistema',
            ],
        );

        return (int) $benefit->getKey();
    }

    /**
     * Monto de la cobertura de una columna.
     *
     * `coverages.price` es obligatorio y numérico, pero la columna de la matriz
     * solo tiene un encabezado comercial. Se lee el monto abreviado de ese
     * encabezado —es el inverso de `PlanGeneratorStructureImporter::columnLabel()`,
     * que lo escribe— y, si no trae ninguno, se toma el mayor tope de cobertura
     * declarado en esa columna. Cero como último recurso, que en este módulo ya
     * significa «sin tope».
     *
     * @param  array<string, mixed>  $column
     * @param  array<string, mixed>  $rows
     */
    public static function coveragePriceForColumn(array $column, array $rows): float
    {
        $fromLabel = self::parseAbbreviatedAmount((string) ($column['header_label'] ?? ''));

        if ($fromLabel !== null) {
            return $fromLabel;
        }

        $columnKey = (string) ($column['column_key'] ?? '');
        $highestLimit = 0.0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $cell = (array) ($row['cells'][$columnKey] ?? []);

            if (! (bool) ($cell['is_selected'] ?? false)) {
                continue;
            }

            $highestLimit = max(
                $highestLimit,
                PlanGeneratorGroupTotalCalculator::parseAmount($cell['coverage_amount'] ?? null),
            );
        }

        return $highestLimit;
    }

    /**
     * Inverso de `PlanGeneratorStructureImporter::abbreviateAmount()`.
     *
     * «2k» → 2000, «PLAN I 5K» → 5000, «IDEAL 1.5K» → 1500, «AP 500» → 500.
     * Un encabezado sin monto («GRUPO EMPRESARIAL») devuelve null.
     */
    public static function parseAbbreviatedAmount(string $label): ?float
    {
        // Último número del encabezado, con o sin sufijo K: el monto siempre va
        // al final («PLAN I 5K»), y el «I» de «PLAN I» no debe confundirse.
        if (preg_match_all('/(\d+(?:[.,]\d+)?)\s*([kK])?\b/u', $label, $matches, PREG_SET_ORDER) === 0) {
            return null;
        }

        $last = $matches[array_key_last($matches)];
        $amount = (float) str_replace(',', '.', $last[1]);

        if (filled($last[2] ?? null)) {
            $amount *= 1000;
        }

        return $amount > 0 ? $amount : null;
    }

    /**
     * Límites de un rango etario escrito a mano: «18 - 64» → [18, 64].
     *
     * @return array{0: int|null, 1: int|null}
     */
    public static function ageRangeBounds(string $label): array
    {
        if (preg_match_all('/\d+/', $label, $matches) === 0) {
            return [null, null];
        }

        $numbers = array_map('intval', $matches[0]);

        return [
            $numbers[0] ?? null,
            count($numbers) > 1 ? $numbers[count($numbers) - 1] : null,
        ];
    }
}
