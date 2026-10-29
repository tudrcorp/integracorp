<?php

declare(strict_types=1);

namespace App\Support\Quotes;

use App\Models\AgeRange;
use App\Models\IndividualQuote;
use App\Models\IndividualQuoteUpgrade;
use App\Models\Upgrade;
use App\Support\PlanGenerators\PlanGeneratorUpgrades;
use App\Support\SecurityAudit;
use App\Support\Upgrades\UpgradeCardCatalog;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Upgrades de una cotización individual (Negocios → Cotizaciones individuales).
 *
 * La cotización no conoce edades, solo cuántas personas hay en cada rango de edad del plan. Se
 * usan las mismas reglas del generador de planes ({@see PlanGeneratorUpgrades}): el precio de
 * cada rango es el del rango del upgrade que lo contiene (el más alto si cruza dos); un rango
 * cubierto en parte solo se cobra si el analista declara cuántas personas califican, con nota;
 * un rango sin ninguna edad cubierta no aplica. En «múltiples planes» la población de rangos
 * iguales se suma: el precio del upgrade depende solo de la edad.
 *
 * El costo del upgrade se suma aparte de las tarifas del plan: no modifica
 * `detail_individual_quotes`. Todo se calcula en el servidor; del navegador solo llega qué
 * upgrade y cobertura, qué rangos se excluyen y las inclusiones manuales.
 */
final class IndividualQuoteUpgrades
{
    public const MULTIPLE_PLAN = 'CM';

    /**
     * Rangos de la cotización con su población, desde el estado del formulario.
     *
     * @param  array<int|string, mixed>  $detailsQuote  filas de «details_quote» (un plan)
     * @param  array<int|string, mixed>  $detailsQuoteMultiple  filas de «details_quote_multiple»
     * @return list<array{age_range_label: string, population: int}>
     */
    public static function rateRows(mixed $plan, array $detailsQuote, array $detailsQuoteMultiple): array
    {
        $isMultiple = (string) $plan === self::MULTIPLE_PLAN;
        $singlePlanId = ! $isMultiple && is_numeric($plan) ? (int) $plan : null;
        $source = $isMultiple ? $detailsQuoteMultiple : $detailsQuote;
        $pairs = [];

        foreach ($source as $row) {
            if (! is_array($row)) {
                continue;
            }

            $planId = $isMultiple ? (is_numeric($row['plan_id'] ?? null) ? (int) $row['plan_id'] : null) : $singlePlanId;
            $rangeId = is_numeric($row['age_range_id'] ?? null) ? (int) $row['age_range_id'] : null;
            $persons = is_numeric($row['total_persons'] ?? null) ? (int) $row['total_persons'] : 0;

            if ($planId === null || $rangeId === null || $persons < 1) {
                continue;
            }

            $pairs[] = [$planId, $rangeId, $persons];
        }

        if ($pairs === []) {
            return [];
        }

        $ranges = AgeRange::query()
            ->whereIn('id', array_values(array_unique(array_column($pairs, 1))))
            ->get(['id', 'plan_id', 'range'])
            ->keyBy('id');

        $rows = [];

        foreach ($pairs as [$planId, $rangeId, $persons]) {
            $range = $ranges->get($rangeId);

            // Un rango de otro plan (estado manipulado) no cuenta.
            if ($range === null || (int) $range->plan_id !== $planId) {
                continue;
            }

            $label = trim((string) $range->range);
            $key = PlanGeneratorUpgrades::rangeKey($label);

            if ($key === '') {
                continue;
            }

            $rows[$key] ??= ['age_range_label' => $label, 'population' => 0];
            $rows[$key]['population'] += $persons;
        }

        return array_values($rows);
    }

    /**
     * Cálculo completo (sin escribir nada): cada upgrade frente a los rangos y su población.
     *
     * @param  array<int|string, mixed>  $state  filas del repetidor «upgrades»
     * @param  list<array{age_range_label: string, population: int}>  $rateRows
     * @param  list<array<string, mixed>>  $storedLines
     * @return array{upgrades: list<array<string, mixed>>, annual_total: float, population: int, errors: list<string>}
     */
    public static function quote(array $state, array $rateRows, array $storedLines = []): array
    {
        $state = array_values(array_filter($state, 'is_array'));

        if ($state === []) {
            return ['upgrades' => [], 'annual_total' => 0.0, 'population' => 0, 'errors' => []];
        }

        $resolved = PlanGeneratorUpgrades::resolveLinesFrom($storedLines, $state);
        $quote = PlanGeneratorUpgrades::quote($resolved['lines'], $rateRows);
        $quote['errors'] = array_values(array_unique([...$resolved['errors'], ...$quote['errors']]));

        if ($state !== [] && $rateRows === []) {
            $quote['errors'][] = 'Indique los rangos de edad y la cantidad de personas antes de agregar upgrades.';
        }

        return $quote;
    }

    /**
     * Guarda los upgrades de la cotización. Lanza con el primer problema y no escribe nada.
     *
     * @param  array<int|string, mixed>  $state
     * @param  list<array{age_range_label: string, population: int}>  $rateRows
     *
     * @throws InvalidArgumentException
     */
    public static function persist(IndividualQuote $quote, array $state, array $rateRows): void
    {
        $stored = $quote->upgrades()->get()->map(fn (IndividualQuoteUpgrade $line): array => $line->toLine())->values()->all();
        $result = self::quote($state, $rateRows, $stored);

        if ($result['errors'] !== []) {
            throw new InvalidArgumentException(implode(' ', $result['errors']));
        }

        $quote->upgrades()->delete();
        $actor = trim((string) (Auth::user()?->name ?? '')) ?: 'system';

        foreach ($result['upgrades'] as $index => $line) {
            IndividualQuoteUpgrade::query()->create([
                'individual_quote_id' => $quote->getKey(),
                'line_key' => (string) $line['line_key'],
                'upgrade_id' => $line['upgrade_id'] ?? null,
                'upgrade_coverage_id' => $line['upgrade_coverage_id'] ?? null,
                'upgrade_code' => $line['upgrade_code'] ?? null,
                'upgrade_name' => (string) $line['upgrade_name'],
                'coverage_amount' => $line['coverage_amount'] ?? null,
                'age_prices' => array_values((array) $line['age_prices']),
                'services' => array_values((array) ($line['services'] ?? [])),
                'excluded_ranges' => PlanGeneratorUpgrades::normalizeExcluded($line['excluded_ranges'] ?? []),
                'manual_ranges' => PlanGeneratorUpgrades::normalizeManual($line['manual_ranges'] ?? []),
                'rows' => array_map(static fn (array $row): array => [
                    'label' => $row['label'],
                    'population' => $row['population'],
                    'charged_persons' => $row['charged_persons'],
                    'price' => $row['price'],
                    'status' => $row['status'],
                    'covered' => $row['covered'],
                    'subtotal' => $row['subtotal'],
                ], (array) $line['rows']),
                'persons' => (int) $line['persons'],
                'annual_amount' => round((float) $line['annual'], 2),
                'sort_order' => $index,
                'created_by' => $line['created_by'] ?? $actor,
            ]);
        }

        if ($result['upgrades'] !== []) {
            SecurityAudit::log('AUDIT_BUSINESS_INDIVIDUAL_QUOTE_UPGRADES', 'business.individual-quotes.upgrades', [
                'individual_quote_id' => $quote->getKey(),
                'code' => $quote->code,
                'upgrades' => array_map(static fn (array $line): array => [
                    'upgrade' => $line['upgrade_name'],
                    'coverage_id' => $line['upgrade_coverage_id'] ?? null,
                    'persons' => $line['persons'],
                    'annual' => $line['annual'],
                    'manual_ranges' => count((array) ($line['manual_ranges'] ?? [])),
                ], $result['upgrades']),
            ]);
        }
    }

    /**
     * Resumen de lo guardado, para la ficha, el PDF y la página del cliente.
     *
     * Los upgrades no se reparten en cuotas: se pagan completos en el primer pago
     * (`UpgradeInstallments`).
     *
     * @return array{lines: list<array{name: string, coverage: string, persons: int, annual: float, rows: list<array<string, mixed>>, services: list<array<string, mixed>>, logo: array{image: ?string, color: string, initials: string}}>, annual_total: float}
     */
    public static function summary(IndividualQuote $quote): array
    {
        $upgrades = $quote->relationLoaded('upgrades') ? $quote->upgrades : $quote->upgrades()->get();
        $catalog = Upgrade::query()
            ->whereIn('id', $upgrades->pluck('upgrade_id')->filter()->unique()->values()->all())
            ->get()
            ->keyBy('id');
        $lines = [];
        $total = 0.0;

        foreach ($upgrades as $upgrade) {
            $annual = round((float) $upgrade->annual_amount, 2);
            $total += $annual;
            $lines[] = [
                'name' => (string) $upgrade->upgrade_name,
                'coverage' => PlanGeneratorUpgrades::coverageLabel($upgrade->toLine()),
                'persons' => (int) $upgrade->persons,
                'annual' => $annual,
                'rows' => array_values((array) $upgrade->rows),
                'services' => array_values((array) $upgrade->services),
                'logo' => self::logo((int) $upgrade->upgrade_id, (string) $upgrade->upgrade_name, $catalog->get($upgrade->upgrade_id)),
            ];
        }

        $total = round($total, 2);

        return ['lines' => $lines, 'annual_total' => $total];
    }

    /**
     * Logo pequeño del upgrade para el PDF: su imagen del catálogo (ruta local) o, si no tiene,
     * una insignia con el color de su tarjeta y sus iniciales.
     *
     * @return array{image: ?string, color: string, initials: string}
     */
    public static function logo(int $upgradeId, string $name, ?Upgrade $upgrade = null): array
    {
        return [
            'image' => $upgrade !== null && Upgrade::hasImageColumn() ? $upgrade->imagePath() : null,
            'color' => UpgradeCardCatalog::fallbackGradient(max(0, $upgradeId))[0],
            'initials' => UpgradeCardCatalog::initials($name),
        ];
    }
}
